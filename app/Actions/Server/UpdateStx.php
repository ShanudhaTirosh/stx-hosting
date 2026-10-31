<?php

namespace App\Actions\Server;

use App\Jobs\DatabaseBackupJob;
use App\Models\Server;
use App\Models\StandalonePostgresql;
use App\Services\StxUpgradeStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateStx
{
    use AsAction;

    public ?Server $server = null;

    public ?string $latestVersion = null;

    public ?string $currentVersion = null;

    public function handle($manual_update = false)
    {
        if (isDev()) {
            $this->backupDatabase();
            Sleep::for(10)->seconds();

            return;
        }
        $settings = instanceSettings();
        $this->server = Server::find(0);
        if (! $this->server) {
            return;
        }

        // Fetch fresh version from CDN instead of using cache
        try {
            $cdnUrl = config('constants.stx.versions_url') ?: config('constants.coolify.versions_url');
            $response = cdn_http(tries: $manual_update ? 1 : 3)->get($cdnUrl);

            if ($response->successful()) {
                $versions = $response->json();
                $this->latestVersion = data_get($versions, 'stx.v4.version', data_get($versions, 'coolify.v4.version'));
            } else {
                $cacheVersion = get_latest_version_of_coolify();
                $this->latestVersion = $cacheVersion;
            }
        } catch (\Throwable $e) {
            $cacheVersion = get_latest_version_of_coolify();
            $this->latestVersion = $cacheVersion;
        }

        $this->currentVersion = config('constants.stx.version', config('constants.coolify.version'));

        $this->backupDatabase();
        $this->runUpdate();
    }

    private function backupDatabase()
    {
        try {
            $database = StandalonePostgresql::where('id', 0)->first();
            if ($database) {
                DatabaseBackupJob::dispatchSync($database);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to backup STX database: {$e->getMessage()}");
        }
    }

    private function runUpdate()
    {
        try {
            $upgradeStatus = StxUpgradeStatus::fromFile(
                instant_remote_process(['cat '.StxUpgradeStatus::FILE.' 2>/dev/null || cat '.StxUpgradeStatus::LEGACY_FILE.' 2>/dev/null || true'], $this->server, false) ?? '',
                $this->currentVersion ?? '0.0.0',
                $this->latestVersion ?? 'latest'
            );

            if ($upgradeStatus['status'] === 'in_progress') {
                throw new \Exception('Another STX Hosting upgrade is already running. Wait for it to finish.');
            }

            instant_remote_process([
                'curl -fsSL https://cdn.stxhosting.com/stx/upgrade.sh -o /tmp/upgrade.sh || curl -fsSL https://cdn.coollabs.io/coolify/upgrade.sh -o /tmp/upgrade.sh',
                'bash /tmp/upgrade.sh '.escapeshellarg($this->latestVersion ?? 'latest'),
            ], $this->server);
        } catch (\Throwable $e) {
            Log::error("STX upgrade failed: {$e->getMessage()}");
            throw $e;
        }
    }
}
