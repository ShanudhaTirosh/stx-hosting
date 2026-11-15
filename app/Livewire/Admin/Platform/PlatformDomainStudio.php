<?php

namespace App\Livewire\Admin\Platform;

use App\Models\Commerce\CommerceAuditLog;
use App\Services\Commerce\CloudflarePlatformDnsService;
use App\Services\Commerce\PlatformDomainService;
use App\Services\RbacService;
use Livewire\Component;

class PlatformDomainStudio extends Component
{
    public string $baseDomain = '';

    public string $appsDomain = '';

    public string $ingressIp = '';

    public string $cloudflareToken = '';

    public ?string $cloudflareZoneId = null;

    public string $tokenStatus = 'idle'; // 'idle', 'valid', 'invalid'

    public string $tokenMessage = '';

    public array $availableZones = [];

    public array $recordsStatus = [];

    public ?string $lastInitializedAt = null;

    public function boot(): void
    {
        if (! RbacService::isPlatformOwner(auth()->user())) {
            abort(403, 'Platform owner access required.');
        }
    }

    public function mount(): void
    {
        $settings = instanceSettings();

        $this->baseDomain = (string) ($settings?->base_platform_domain ?: PlatformDomainService::basePlatformDomain() ?: 'stxhostings.com');
        $this->appsDomain = (string) ($settings?->apps_domain ?: PlatformDomainService::appsDomain() ?: 'app.stxhostings.com');
        $this->ingressIp = (string) (config('hosting.ingress_ip') ?: $settings?->public_ipv4 ?: request()->server('SERVER_ADDR', '127.0.0.1'));
        $this->cloudflareToken = filled($settings?->cloudflare_api_token) ? '••••••••' : '';
        $this->cloudflareZoneId = $settings?->cloudflare_zone_id;
        $this->lastInitializedAt = $settings?->platform_domains_initialized_at?->toDayDateTimeString();

        if (filled($settings?->cloudflare_api_token)) {
            $this->testToken(silent: true);
        }
    }

    private function resolveCloudflareToken(): string
    {
        if ($this->cloudflareToken === '••••••••' || str_starts_with($this->cloudflareToken, '••••')) {
            return (string) (instanceSettings()?->cloudflare_api_token ?: '');
        }

        return trim($this->cloudflareToken);
    }

    public function testToken(bool $silent = false): void
    {
        $resolvedToken = $this->resolveCloudflareToken();
        if (empty($resolvedToken)) {
            $this->tokenStatus = 'invalid';
            $this->tokenMessage = 'Cloudflare API token cannot be empty.';

            return;
        }

        $service = app(CloudflarePlatformDnsService::class);
        $result = $service->verifyToken($resolvedToken);

        if ($result['valid']) {
            $this->tokenStatus = 'valid';
            $this->tokenMessage = 'Token verified with Cloudflare API.';
            $this->availableZones = $service->listZones($resolvedToken);

            $zone = $service->detectZone($resolvedToken, $this->baseDomain);
            if ($zone) {
                $this->cloudflareZoneId = $zone['id'];
            }

            if (! $silent) {
                $this->dispatch('success', 'Cloudflare API token verified successfully!');
            }
        } else {
            $this->tokenStatus = 'invalid';
            $this->tokenMessage = $result['message'];
            if (! $silent) {
                $this->dispatch('error', $result['message']);
            }
        }
    }

    public function updatedBaseDomain(): void
    {
        $this->baseDomain = strtolower(trim($this->baseDomain, ' ./'));
        if (empty($this->appsDomain) || str_ends_with($this->appsDomain, '.stxhostings.com') || str_starts_with($this->appsDomain, 'app.')) {
            $this->appsDomain = "app.{$this->baseDomain}";
        }

        if ($this->tokenStatus === 'valid') {
            $resolvedToken = $this->resolveCloudflareToken();
            $zone = app(CloudflarePlatformDnsService::class)->detectZone($resolvedToken, $this->baseDomain);
            $this->cloudflareZoneId = $zone ? $zone['id'] : null;
        }
    }

    public function provisionDns(): void
    {
        $this->validate([
            'baseDomain' => 'required|string|regex:/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i',
            'appsDomain' => 'required|string|regex:/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i',
            'ingressIp' => 'required|ip',
        ]);

        $resolvedToken = $this->resolveCloudflareToken();
        if (empty($resolvedToken)) {
            $this->addError('cloudflareToken', 'Cloudflare API token is required.');

            return;
        }

        $service = app(CloudflarePlatformDnsService::class);

        // 1. Verify token
        $tokenRes = $service->verifyToken($resolvedToken);
        if (! $tokenRes['valid']) {
            $this->addError('cloudflareToken', $tokenRes['message']);

            return;
        }

        // 2. Detect Zone ID
        $zone = $service->detectZone($resolvedToken, $this->baseDomain);
        if (! $zone) {
            $this->addError('baseDomain', "No Cloudflare DNS Zone found matching domain '{$this->baseDomain}'. Please ensure this domain is added to your Cloudflare account.");

            return;
        }

        $this->cloudflareZoneId = $zone['id'];

        try {
            // 3. Provision DNS records in Cloudflare
            $this->recordsStatus = $service->provisionPlatformDns(
                $resolvedToken,
                $this->cloudflareZoneId,
                $this->ingressIp,
                $this->baseDomain,
                $this->appsDomain
            );

            $this->lastInitializedAt = now()->toDayDateTimeString();
            $this->tokenStatus = 'valid';

            CommerceAuditLog::record('platform_domains.dns_provisioned', null, null, [
                'base_domain' => $this->baseDomain,
                'apps_domain' => $this->appsDomain,
                'zone_id' => $this->cloudflareZoneId,
                'ingress_ip' => $this->ingressIp,
            ]);

            $this->dispatch('success', "Platform domains for '{$this->baseDomain}' and wildcard '*.{$this->appsDomain}' provisioned successfully in Cloudflare!");
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Provisioning error: '.$e->getMessage());
        }
    }

    public function saveSettingsOnly(): void
    {
        $this->validate([
            'baseDomain' => 'required|string',
            'appsDomain' => 'required|string',
            'ingressIp' => 'required|ip',
        ]);

        $settings = instanceSettings();
        if ($settings) {
            $updateData = [
                'base_platform_domain' => strtolower(trim($this->baseDomain, ' ./')),
                'apps_domain' => strtolower(trim($this->appsDomain, ' ./')),
                'cloudflare_zone_id' => $this->cloudflareZoneId ?: null,
            ];

            if ($this->cloudflareToken !== '••••••••' && ! str_starts_with($this->cloudflareToken, '••••')) {
                $updateData['cloudflare_api_token'] = filled($this->cloudflareToken) ? trim($this->cloudflareToken) : null;
            }

            $settings->update($updateData);

            CommerceAuditLog::record('platform_domains.settings_saved', null, null, [
                'base_domain' => $this->baseDomain,
                'apps_domain' => $this->appsDomain,
                'zone_id' => $this->cloudflareZoneId,
            ]);
        }

        $this->dispatch('success', 'Domain settings saved successfully.');
    }

    public function render()
    {
        return view('livewire.admin.platform.platform-domain-studio')
            ->layout('layouts.app');
    }
}
