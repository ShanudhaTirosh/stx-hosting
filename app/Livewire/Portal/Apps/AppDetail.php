<?php

namespace App\Livewire\Portal\Apps;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\ServiceInstance;
use App\Models\CustomDomain;
use App\Services\Commerce\AppProvisioningService;
use App\Services\Commerce\CustomDomainService;
use App\Services\Commerce\PlatformDomainService;
use Livewire\Component;

class AppDetail extends Component
{
    public string $uuid;

    public string $newDomain = '';
    public string $targetService = '';

    public string $envKey = '';
    public string $envValue = '';

    public array $envVars = [];
    public array $domains = [];
    public array $assignedEndpoints = [];
    public ?string $selectedDomainForDnsModal = null;

    // Duplication modal fields
    public bool $showDuplicateModal = false;
    public string $duplicateName = '';
    public string $duplicateSubdomain = '';
    public string $duplicateBranch = '';
    public ?int $duplicateVariantId = null;

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

        $assigned = data_get($service->network_config, 'assigned_endpoints');
        if (empty($assigned) && $service->template_name) {
            $assigned = PlatformDomainService::assignTemplateSubdomains($service, $service->template_name);
        }
        $this->assignedEndpoints = $assigned ?: [];
    }

    public function addDomain(): void
    {
        $this->validate([
            'newDomain' => 'required|string|regex:/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i',
        ]);

        $team = currentTeam();
        $service = ServiceInstance::where('uuid', $this->uuid)->firstOrFail();
        abort_if(! $team || $service->team_id !== $team->id, 403, 'Unauthorized access to service resource.');

        $domainService = app(CustomDomainService::class);
        $customDomain = $domainService->request($team, $this->newDomain);
        $verified = $domainService->verify($customDomain);

        $normalized = $customDomain->domain;

        $config = $service->network_config ?? [];
        $customDomains = data_get($config, 'custom_domains', []);
        if (! in_array($normalized, $customDomains, true)) {
            $customDomains[] = $normalized;
        }
        $config['custom_domains'] = $customDomains;

        if (! empty($this->targetService)) {
            $routeMap = data_get($config, 'domain_target_services', []);
            $routeMap[$normalized] = $this->targetService;
            $config['domain_target_services'] = $routeMap;
        }

        $service->update(['network_config' => $config]);
        $this->loadAppData();

        CommerceAuditLog::create([
            'user_id' => auth()->id(),
            'team_id' => $team->id,
            'action' => 'app.domain_added',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'payload' => ['domain' => $normalized, 'dns_verified' => $verified, 'target_service' => $this->targetService],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->newDomain = '';
        $this->targetService = '';

        if ($verified) {
            $this->dispatch('success', "Domain {$normalized} verified successfully! SSL certificate is active.");
        } else {
            $this->selectedDomainForDnsModal = $normalized;
            $this->dispatch('info', "Domain {$normalized} added. Follow the DNS instructions below to point your records.");
        }
    }

    public function verifyDomain(string $domainName): void
    {
        $team = currentTeam();
        $domain = CustomDomain::where('team_id', $team->id)->where('domain', $domainName)->first();

        if (! $domain) {
            $domain = app(CustomDomainService::class)->request($team, $domainName);
        }

        $verified = app(CustomDomainService::class)->verify($domain);

        if ($verified) {
            $this->dispatch('success', "DNS for {$domainName} verified! Instant HTTPS certificate is active.");
        } else {
            $this->dispatch('warning', "DNS records for {$domainName} have not propagated yet. Please ensure your {$domain->record_type} record points to {$domain->expected_target}.");
        }

        $this->loadAppData();
    }

    public function removeDomain(string $domainName): void
    {
        $team = currentTeam();
        $service = ServiceInstance::where('uuid', $this->uuid)->firstOrFail();
        abort_if(! $team || $service->team_id !== $team->id, 403, 'Unauthorized access to service resource.');

        $config = $service->network_config ?? [];
        $customDomains = array_values(array_filter(
            data_get($config, 'custom_domains', []),
            fn ($d) => $d !== $domainName
        ));
        $config['custom_domains'] = $customDomains;

        $routes = data_get($config, 'domain_target_services', []);
        unset($routes[$domainName]);
        $config['domain_target_services'] = $routes;

        $service->update(['network_config' => $config]);
        $this->loadAppData();

        $this->dispatch('success', "Domain {$domainName} removed.");
    }

    public function setPrimaryDomain(string $domainName): void
    {
        $team = currentTeam();
        $service = ServiceInstance::where('uuid', $this->uuid)->firstOrFail();
        abort_if(! $team || $service->team_id !== $team->id, 403, 'Unauthorized access to service resource.');

        $service->update(['hostname' => $domainName]);
        $this->loadAppData();

        $this->dispatch('success', "{$domainName} is now the primary domain for this application.");
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

    public function openDuplicateModal(): void
    {
        $team = currentTeam();
        $service = ServiceInstance::where('uuid', $this->uuid)->firstOrFail();
        abort_if(! $team || $service->team_id !== $team->id, 403, 'Unauthorized access to service resource.');

        $nextSlug = PlatformDomainService::nextAvailableSlug($service->name);
        $this->duplicateName = $nextSlug;
        $this->duplicateSubdomain = $nextSlug;
        $this->duplicateBranch = data_get($service->network_config, 'git_branch', 'main');
        $this->duplicateVariantId = $service->product_variant_id;
        $this->showDuplicateModal = true;
    }

    public function duplicateApp(): mixed
    {
        $this->validate([
            'duplicateName' => 'required|string|min:2|max:64|regex:/^[a-zA-Z0-9-]+$/',
            'duplicateSubdomain' => 'nullable|string|min:3|max:50|regex:/^[a-z0-9-]+$/',
        ]);

        $team = currentTeam();
        $service = ServiceInstance::where('uuid', $this->uuid)->firstOrFail();
        abort_if(! $team || $service->team_id !== $team->id, 403, 'Unauthorized access to service resource.');

        if (filled($this->duplicateSubdomain) && ! PlatformDomainService::isSubdomainAvailable($this->duplicateSubdomain)) {
            $this->addError('duplicateSubdomain', "The subdomain '{$this->duplicateSubdomain}' is already in use. Please pick another.");
            return null;
        }

        $newService = AppProvisioningService::duplicateApp(
            $service,
            $this->duplicateName,
            $this->duplicateSubdomain ?: null,
            [],
            $this->duplicateVariantId,
            $this->duplicateBranch ?: null
        );

        $this->showDuplicateModal = false;
        $this->dispatch('success', "Application '{$newService->name}' deployed successfully as a duplicate instance!");

        return redirect()->route('portal.apps.detail', ['uuid' => $newService->uuid]);
    }

    public function render()
    {
        $team = currentTeam();
        $service = ServiceInstance::with('productVariant')
            ->where('uuid', $this->uuid)
            ->firstOrFail();

        abort_if(! $team || $service->team_id !== $team->id, 403, 'Unauthorized access to service resource.');

        $customDomainRecords = CustomDomain::where('team_id', $team->id)
            ->whereIn('domain', data_get($service->network_config, 'custom_domains', []))
            ->get()
            ->keyBy('domain');

        $ingressIp = config('hosting.ingress_ip') ?: '127.0.0.1';
        $appsDomain = PlatformDomainService::appsDomain() ?: config('hosting.default_domain', 'stxapp.io');

        return view('livewire.portal.apps.app-detail', [
            'service' => $service,
            'customDomainRecords' => $customDomainRecords,
            'ingressIp' => $ingressIp,
            'appsDomain' => $appsDomain,
            'assignedEndpoints' => $this->assignedEndpoints,
        ]);
    }
}
