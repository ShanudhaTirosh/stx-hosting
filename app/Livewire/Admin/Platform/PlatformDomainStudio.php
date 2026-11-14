<?php

namespace App\Livewire\Admin\Platform;

use App\Exceptions\PlatformRestrictionException;
use App\Models\InstanceSettings;
use App\Services\Commerce\CloudflarePlatformDnsService;
use App\Services\Commerce\PlatformDomainService;
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

    public function mount(): void
    {
        $settings = instanceSettings();

        $this->baseDomain = (string) ($settings?->base_platform_domain ?: PlatformDomainService::basePlatformDomain() ?: 'stxhostings.com');
        $this->appsDomain = (string) ($settings?->apps_domain ?: PlatformDomainService::appsDomain() ?: 'app.stxhostings.com');
        $this->ingressIp = (string) (config('hosting.ingress_ip') ?: $settings?->public_ipv4 ?: request()->server('SERVER_ADDR', '127.0.0.1'));
        $this->cloudflareToken = (string) ($settings?->cloudflare_api_token ?: '');
        $this->cloudflareZoneId = $settings?->cloudflare_zone_id;
        $this->lastInitializedAt = $settings?->platform_domains_initialized_at?->toDayDateTimeString();

        if (filled($this->cloudflareToken)) {
            $this->testToken(silent: true);
        }
    }

    public function testToken(bool $silent = false): void
    {
        $this->validate([
            'cloudflareToken' => 'required|string',
        ]);

        $service = app(CloudflarePlatformDnsService::class);
        $result = $service->verifyToken($this->cloudflareToken);

        if ($result['valid']) {
            $this->tokenStatus = 'valid';
            $this->tokenMessage = 'Token verified with Cloudflare API.';
            $this->availableZones = $service->listZones($this->cloudflareToken);

            $zone = $service->detectZone($this->cloudflareToken, $this->baseDomain);
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
        $this->baseDomain = strtolower(trim($this->baseDomain, " ./"));
        if (empty($this->appsDomain) || str_ends_with($this->appsDomain, '.stxhostings.com') || str_starts_with($this->appsDomain, 'app.')) {
            $this->appsDomain = "app.{$this->baseDomain}";
        }

        if ($this->tokenStatus === 'valid') {
            $zone = app(CloudflarePlatformDnsService::class)->detectZone($this->cloudflareToken, $this->baseDomain);
            $this->cloudflareZoneId = $zone ? $zone['id'] : null;
        }
    }

    public function provisionDns(): void
    {
        $this->validate([
            'baseDomain' => 'required|string|regex:/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i',
            'appsDomain' => 'required|string|regex:/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i',
            'ingressIp' => 'required|ip',
            'cloudflareToken' => 'required|string',
        ]);

        $service = app(CloudflarePlatformDnsService::class);

        // 1. Verify token
        $tokenRes = $service->verifyToken($this->cloudflareToken);
        if (! $tokenRes['valid']) {
            $this->addError('cloudflareToken', $tokenRes['message']);
            return;
        }

        // 2. Detect Zone ID
        $zone = $service->detectZone($this->cloudflareToken, $this->baseDomain);
        if (! $zone) {
            $this->addError('baseDomain', "No Cloudflare DNS Zone found matching domain '{$this->baseDomain}'. Please ensure this domain is added to your Cloudflare account.");
            return;
        }

        $this->cloudflareZoneId = $zone['id'];

        try {
            // 3. Provision DNS records in Cloudflare
            $this->recordsStatus = $service->provisionPlatformDns(
                $this->cloudflareToken,
                $this->cloudflareZoneId,
                $this->ingressIp,
                $this->baseDomain,
                $this->appsDomain
            );

            $this->lastInitializedAt = now()->toDayDateTimeString();
            $this->tokenStatus = 'valid';

            $this->dispatch('success', "Platform domains for '{$this->baseDomain}' and wildcard '*.{$this->appsDomain}' provisioned successfully in Cloudflare!");
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Provisioning error: ' . $e->getMessage());
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
            $settings->update([
                'base_platform_domain' => strtolower(trim($this->baseDomain, " ./")),
                'apps_domain' => strtolower(trim($this->appsDomain, " ./")),
                'cloudflare_api_token' => $this->cloudflareToken ?: null,
                'cloudflare_zone_id' => $this->cloudflareZoneId ?: null,
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

