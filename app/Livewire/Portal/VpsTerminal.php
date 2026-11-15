<?php

namespace App\Livewire\Portal;

use App\Livewire\Project\Shared\Terminal;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\ServiceInstance;
use App\Services\TerminalSessionService;
use Livewire\Attributes\On;

/**
 * The same in-browser terminal used for servers (same page script, same terminal server), pointed at a
 * customer's own VPS. The page asks for a session with `start-vps-terminal`; we check the VPS belongs to the
 * signed-in user's team, issue a 60 second single-use token, and the terminal server redeems it.
 */
class VpsTerminal extends Terminal
{
    public string $serviceUuid = '';

    public function mount(string $serviceUuid = ''): void
    {
        $this->serviceUuid = $serviceUuid;
        $this->variant = 'application';
    }

    #[On('start-vps-terminal')]
    public function startVpsTerminal(): void
    {
        $service = ServiceInstance::where('uuid', $this->serviceUuid)
            ->where('service_type', 'vps')
            ->where('status', 'active')
            ->first();

        if (! $service || ! auth()->user()?->canPerformInTeam($service->team_id, 'servers.access')) {
            $this->dispatch(self::SESSION_FAILED_EVENT, message: self::NOT_ALLOWED_MESSAGE)->self();

            return;
        }

        if (! $service->panel_private_key_id) {
            $this->dispatch(self::SESSION_FAILED_EVENT, message: 'Enable panel access for this server first.')->self();

            return;
        }

        $token = app(TerminalSessionService::class)->issueForVps(auth()->user(), $service);

        CommerceAuditLog::record('vps.terminal_opened', ServiceInstance::class, $service->id, ['ip' => $service->ip_address]);

        $this->hasShell = true;
        $this->dispatch('send-terminal-token', $token);
    }

    /** This component is not driven by server/container selection. */
    #[On('send-terminal-command')]
    public function sendTerminalCommand($isContainer, $identifier, $serverUuid, ?string $requestedMode = null): void
    {
        // intentionally empty
    }
}
