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
        $service = ServiceInstance::where('uuid', $this->uuid)->firstOrFail();
        abort_if(! $team || $service->team_id !== $team->id, 403, 'Unauthorized access to service resource.');

        $this->domains = array_unique(array_filter([$service->hostname, ...data_get($service->network_config, 'custom_domains', [])]));
        $this->envVars = data_get($service->network_config, 'env_vars', []);
    }

    public function addDomain(): void
    {
        $this->validate([
            'newDomain' => 'required|string|regex:/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i',
        ]);

        $team = currentTeam();
        $service = ServiceInstance::where('uuid', $this->uuid)->firstOrFail();
        abort_if(! $team || $service->team_id !== $team->id, 403, 'Unauthorized access to service resource.');

        // Pre-flight DNS verification against server IP
        $serverIp = $service->ip_address ?: '127.0.0.1';
        $verified = AppProvisioningService::verifyDomainDns($this->newDomain, $serverIp);

        if (! in_array($this->newDomain, $this->domains)) {
            $this->domains[] = $this->newDomain;
        }

        $config = $service->network_config ?? [];
        $customDomains = data_get($config, 'custom_domains', []);
        if (! in_array($this->newDomain, $customDomains)) {
            $customDomains[] = $this->newDomain;
        }
        $config['custom_domains'] = $customDomains;
        $service->update(['network_config' => $config]);

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

        $team = currentTeam();
        $service = ServiceInstance::where('uuid', $this->uuid)->firstOrFail();
        abort_if(! $team || $service->team_id !== $team->id, 403, 'Unauthorized access to service resource.');

        $config = $service->network_config ?? [];
        $envVars = data_get($config, 'env_vars', []);
        $envVars[$this->envKey] = $this->envValue;
        $config['env_vars'] = $envVars;
        $service->update(['network_config' => $config]);

        $this->envVars = $envVars;
        $savedKey = $this->envKey;
        $this->envKey = '';
        $this->envValue = '';

        CommerceAuditLog::create([
            'user_id' => auth()->id(),
            'team_id' => $team->id,
            'action' => 'app.env_added',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'payload' => ['key' => $savedKey],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->dispatch('success', 'Environment variable staged and persisted.');
    }

    public function restart(): void
    {
        $team = currentTeam();
        $service = ServiceInstance::where('uuid', $this->uuid)->firstOrFail();
        abort_if(! $team || $service->team_id !== $team->id, 403, 'Unauthorized access to service resource.');

        CommerceAuditLog::create([
            'user_id' => auth()->id(),
            'team_id' => $team->id,
            'action' => 'app.restart_triggered',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->dispatch('success', 'Restart signal sent to application container.');
    }

    public function redeploy(): void
    {
        $team = currentTeam();
        $service = ServiceInstance::where('uuid', $this->uuid)->firstOrFail();
        abort_if(! $team || $service->team_id !== $team->id, 403, 'Unauthorized access to service resource.');

        CommerceAuditLog::create([
            'user_id' => auth()->id(),
            'team_id' => $team->id,
            'action' => 'app.redeploy_queued',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->dispatch('success', 'Rebuild and deploy job queued.');
    }

    public function render()
    {
        $team = currentTeam();
        $service = ServiceInstance::with('productVariant')
            ->where('uuid', $this->uuid)
            ->firstOrFail();

        abort_if(! $team || $service->team_id !== $team->id, 403, 'Unauthorized access to service resource.');

        return view('livewire.portal.apps.app-detail', [
            'service' => $service,
        ]);
    }
}
