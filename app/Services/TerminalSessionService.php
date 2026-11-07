<?php

namespace App\Services;

use App\Helpers\SshMultiplexingHelper;
use App\Models\Commerce\ServiceInstance;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Models\User;
use App\Support\ValidationPatterns;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TerminalSessionService
{
    private const TOKEN_TTL_SECONDS = 60;

    /**
     * @param  'shell'|'attach'  $mode  Container terminal mode: docker exec or docker attach.
     */
    public function issue(User $user, Server $server, ?string $container = null, string $mode = 'shell'): string
    {
        $token = Str::random(64);

        Cache::put($this->cacheKey($token), [
            'user_id' => $user->id,
            'team_id' => $user->currentTeam()->id,
            'server_uuid' => $server->uuid,
            'container' => $container,
            'mode' => $mode === 'attach' ? 'attach' : 'shell',
        ], now()->addSeconds(self::TOKEN_TTL_SECONDS));

        return $token;
    }

    public function redeem(User $user, string $token): string
    {
        $payload = Cache::lock($this->cacheKey($token).':lock', 5)
            ->get(fn () => Cache::pull($this->cacheKey($token)));

        if (is_array($payload) && isset($payload['vps_service_uuid'])) {
            return $this->redeemVps($user, $payload);
        }

        if (! is_array($payload)
            || data_get($payload, 'user_id') !== $user->id
            || data_get($payload, 'team_id') !== $user->currentTeam()->id
            || ! is_string(data_get($payload, 'server_uuid'))
            || ! array_key_exists('container', $payload)) {
            throw new AccessDeniedHttpException('Invalid or expired terminal token.');
        }

        $server = Server::ownedByCurrentTeam()
            ->whereUuid(data_get($payload, 'server_uuid'))
            ->with('privateKey', 'settings')
            ->firstOrFail();

        if (! $server->isTerminalEnabled()
            || $server->isForceDisabled()
            || ! $server->privateKey
            || $server->privateKey->team_id !== $user->currentTeam()->id) {
            throw new AccessDeniedHttpException('Terminal target is not authorized.');
        }

        $command = $this->shellCommand();
        $container = $payload['container'];

        if ($container !== null) {
            if (! is_string($container)
                || ! ValidationPatterns::isValidContainerName($container)
                || getContainerStatus($server, $container) !== 'running') {
                throw new AccessDeniedHttpException('Terminal container is not authorized.');
            }

            $command = $this->containerCommand($server, $container, data_get($payload, 'mode') === 'attach' ? 'attach' : 'shell');
        }

        return SshMultiplexingHelper::generateSshCommand(
            $server,
            $command,
            commandTimeout: (int) config('constants.terminal.command_timeout')
        );
    }

    /**
     * Token for a shell on a customer's own VPS. Same rules as server tokens: 60 seconds, one use, bound to the
     * signed-in user and their current team.
     */
    public function issueForVps(User $user, ServiceInstance $service): string
    {
        $token = Str::random(64);

        Cache::put($this->cacheKey($token), [
            'user_id' => $user->id,
            'team_id' => $user->currentTeam()->id,
            'vps_service_uuid' => $service->uuid,
        ], now()->addSeconds(self::TOKEN_TTL_SECONDS));

        return $token;
    }

    /**
     * The terminal server only runs key based ssh with a fixed option set against hosts we list for the
     * user, so the command has exactly the shape it already accepts for team servers. The login uses the
     * panel key, never the customer's password, and nothing secret goes to the browser.
     */
    private function redeemVps(User $user, array $payload): string
    {
        $teamId = $user->currentTeam()->id;

        if (data_get($payload, 'user_id') !== $user->id || data_get($payload, 'team_id') !== $teamId) {
            throw new AccessDeniedHttpException('Invalid or expired terminal token.');
        }

        $service = ServiceInstance::where('uuid', data_get($payload, 'vps_service_uuid'))
            ->where('team_id', $teamId)
            ->where('service_type', 'vps')
            ->where('status', 'active')
            ->first();

        $key = $service?->panel_private_key_id ? PrivateKey::find($service->panel_private_key_id) : null;

        if (! $service || ! $key || blank($service->ip_address)) {
            throw new AccessDeniedHttpException('Terminal target is not authorized.');
        }

        $key->storeInFileSystem();

        $port = (int) ($service->credentials()->value('ssh_port') ?: 22);
        $timeout = (int) config('constants.terminal.command_timeout');

        $options = '-i '.escapeshellarg($key->getKeyLocation()).' '
            .'-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null '
            .'-o PasswordAuthentication=no '
            .'-o ConnectTimeout=10 '
            .'-o ServerAliveInterval=20 '
            .'-o RequestTTY=no '
            .'-o LogLevel=ERROR '
            .'-p '.escapeshellarg((string) $port).' ';

        $delimiter = base64_encode(\Illuminate\Support\Facades\Hash::make('vps-'.$service->uuid.Str::random(8)));
        $remote = 'sh -c "if command -v bash >/dev/null 2>&1; then exec bash -se; else exec sh -se; fi"';

        return ($timeout > 0 ? "timeout {$timeout} ssh " : 'ssh ').$options
            .escapeshellarg('root').'@'.escapeshellarg((string) $service->ip_address)
            ." '{$remote}' << \\{$delimiter}".PHP_EOL
            .$this->shellCommand().PHP_EOL
            .$delimiter;
    }

    /**
     * Build the docker command that runs on the remote server for the given mode.
     *
     * @param  'shell'|'attach'  $mode
     */
    public function containerCommand(Server $server, string $container, string $mode): string
    {
        $escapedContainer = escapeshellarg($container);
        // Add sudo for non-root users to access the Docker socket.
        $sudo = $server->isNonRoot() ? 'sudo ' : '';

        if ($mode === 'attach') {
            $detachKeys = config('constants.terminal.detach_keys');
            $historyLines = (int) config('constants.terminal.console_history_lines');

            // Print recent output first so the console is not blank, then attach for live output.
            $primeHistory = $historyLines > 0
                ? "{$sudo}docker logs --tail {$historyLines} {$escapedContainer} 2>&1; "
                : '';

            // Attach to the container's main process. --detach-keys lets the user leave with
            // Ctrl-P, Ctrl-Q and --sig-proxy=false stops the client forwarding signals to the app.
            return "{$primeHistory}exec {$sudo}docker attach --detach-keys=\"{$detachKeys}\" --sig-proxy=false {$escapedContainer}";
        }

        return "{$sudo}docker exec -it {$escapedContainer} sh -c ".escapeshellarg($this->shellCommand());
    }

    private function shellCommand(): string
    {
        return 'PATH=$PATH:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin && '.
            'if [ -f ~/.profile ]; then . ~/.profile; fi && '.
            'if [ -n "$SHELL" ] && [ -x "$SHELL" ]; then exec $SHELL; else sh; fi';
    }

    private function cacheKey(string $token): string
    {
        return "terminal-session:{$token}";
    }
}
