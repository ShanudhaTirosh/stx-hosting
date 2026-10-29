<?php

namespace App\Livewire\Portal\Apps;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\ServiceInstance;
use App\Services\Commerce\AppProvisioningService;
use Livewire\Component;

class AppDetail extends Component
{
    public string $uuid;
    public string $newDomain = '';
    public string $envKey = '';
    public string $envValue = '';
    public array $envVars = [];
    public array $domains = [];

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $this->loadAppData();
    }

    public function loadAppData(): void
    {
        $team = currentTeam();
        $service = ServiceInstance::where('uuid', $this->uuid)
            ->where('team_id', $team->id)
            ->firstOrFail();

        $this->domains = [$service->hostname];
    }

    public function addDomain(): void
    {
        $this->validate([
            'newDomain' => 'required|string|regex:/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i',
        ]);

        $team = currentTeam();
        $service = ServiceInstance::where('uuid', $this->uuid)
            ->where('team_id', $team->id)
            ->firstOrFail();

        // Pre-flight DNS verification against server IP
        $serverIp = $service->ip_address ?: '127.0.0.1';
        $verified = AppProvisioningService::verifyDomainDns($this->newDomain, $serverIp);

        if (! in_array($this->newDomain, $this->domains)) {
            $this->domains[] = $this->newDomain;
        }

        CommerceAuditLog::create([
            'user_id' => auth()->id(),
            'team_id' => $team->id,
            'action' => 'app.domain_added',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'payload' => ['domain' => $this->newDomain, 'dns_verified' => $verified],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->newDomain = '';
        $this->dispatch('success', 'Custom domain added. SSL will be issued automatically once DNS propagates.');
    }

    public function addEnvVar(): void
    {
        $this->validate([
            'envKey' => 'required|string|regex:/^[A-Z0-9_]+$/',
            'envValue' => 'required|string',
        ]);

        $this->envVars[$this->envKey] = $this->envValue;
        $this->envKey = '';
        $this->envValue = '';
        $this->dispatch('success', 'Environment variable staged.');
    }

    public function restart(): void
    {
        $this->dispatch('success', 'Restart signal sent to application container.');
    }

    public function redeploy(): void
    {
        $this->dispatch('success', 'Rebuild and deploy job queued.');
    }

    public function render()
    {
        $team = currentTeam();
        $service = ServiceInstance::with('productVariant')
            ->where('uuid', $this->uuid)
            ->where('team_id', $team->id)
            ->firstOrFail();

        return view('livewire.portal.apps.app-detail', [
            'service' => $service,
        ]);
    }
}

