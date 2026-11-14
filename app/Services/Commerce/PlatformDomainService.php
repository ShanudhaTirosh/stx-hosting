<?php

namespace App\Services\Commerce;

use App\Exceptions\PlatformRestrictionException;
use App\Models\Application;
use App\Models\CustomDomain;
use App\Models\Team;
use Illuminate\Support\Str;

/**
 * Rules for which hostnames a client app may use:
 *   - <slug>.<apps_domain>, our wildcard, as long as nobody else holds it and it is not reserved
 *   - a custom domain the team has proven it owns (CustomDomainService)
 *   - hostnames Coolify generates itself (sslip.io)
 * Anything else is refused, so nobody can point an app at a domain they do not control.
 */
class PlatformDomainService
{
    public static function basePlatformDomain(): ?string
    {
        try {
            $settings = instanceSettings();
            if ($settings && filled($settings->base_platform_domain)) {
                return strtolower(trim($settings->base_platform_domain, " ./"));
            }
        } catch (\Throwable) {
        }

        $domain = env('PLATFORM_DOMAIN') ?: env('DOMAIN') ?: config('hosting.base_domain');

        return filled($domain) ? strtolower(trim((string) $domain, " ./")) : null;
    }

    public static function appsDomain(): ?string
    {
        try {
            $settings = instanceSettings();
            if ($settings && filled($settings->apps_domain)) {
                return strtolower(trim($settings->apps_domain, " ./"));
            }
        } catch (\Throwable) {
        }

        $domain = strtolower(trim((string) config('hosting.apps_domain'), " ./"));
        if ($domain !== '') {
            return $domain;
        }

        $base = self::basePlatformDomain();
        if ($base) {
            return "app.{$base}";
        }

        return config('hosting.default_domain', 'stxapp.io');
    }

    /** @return array<int, string> lower-case hostnames from a comma separated fqdn value */
    public static function hostsOf(?string $fqdn): array
    {
        return collect(explode(',', (string) $fqdn))
            ->map(function ($part) {
                $part = trim($part);
                if ($part === '') {
                    return null;
                }
                if (! str_contains($part, '://')) {
                    $part = 'http://'.$part;
                }

                return strtolower((string) parse_url($part, PHP_URL_HOST));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function isReserved(string $label): bool
    {
        return in_array(strtolower($label), array_map('strtolower', config('hosting.reserved_subdomains', [])), true);
    }

    public static function isPlatformHost(string $host): bool
    {
        $apps = self::appsDomain();

        return $apps !== null && ($host === $apps || str_ends_with($host, '.'.$apps));
    }

    public static function isGeneratedHost(string $host): bool
    {
        foreach (config('hosting.generated_host_suffixes', []) as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Throws when the team may not use one of the hostnames.
     */
    public static function assertAllowed(Team $team, ?string $fqdn, ?int $ignoreApplicationId = null): void
    {
        foreach (self::hostsOf($fqdn) as $host) {
            if (self::isGeneratedHost($host)) {
                continue;
            }

            if (self::isPlatformHost($host)) {
                $label = Str::before(substr($host, 0, -strlen('.'.self::appsDomain())), '.');

                if ($host === self::appsDomain() || self::isReserved($label)) {
                    throw new PlatformRestrictionException("The name '{$host}' is reserved. Pick another name.");
                }

                if (self::takenByOther($host, $ignoreApplicationId)) {
                    throw new PlatformRestrictionException("The address '{$host}' is already in use. Pick another name.");
                }

                continue;
            }

            if (! self::teamOwns($team, $host)) {
                throw new PlatformRestrictionException(
                    "You have not proven you own '{$host}'. Add it under Domains, create the DNS record shown there, and verify it first."
                );
            }
        }
    }

    public static function teamOwns(Team $team, string $host): bool
    {
        return CustomDomain::verified()
            ->where('team_id', $team->id)
            ->get()
            ->contains(fn (CustomDomain $d) => $host === $d->domain || str_ends_with($host, '.'.$d->domain));
    }

    private static function takenByOther(string $host, ?int $ignoreApplicationId = null): bool
    {
        $takenInApps = Application::withoutGlobalScopes()
            ->where('fqdn', 'like', '%'.$host.'%')
            ->when($ignoreApplicationId, fn ($q) => $q->where('id', '!=', $ignoreApplicationId))
            ->get(['id', 'fqdn'])
            ->contains(fn ($app) => in_array($host, self::hostsOf($app->fqdn), true));

        if ($takenInApps) {
            return true;
        }

        // Check ServiceApplication models (used in multi-container compose services)
        $takenInServices = \App\Models\ServiceApplication::withoutGlobalScopes()
            ->where('fqdn', 'like', '%'.$host.'%')
            ->get(['id', 'fqdn'])
            ->contains(fn ($svcApp) => in_array($host, self::hostsOf($svcApp->fqdn), true));

        if ($takenInServices) {
            return true;
        }

        // Check ServiceInstance models (used in commercial portal provisioning)
        $takenInInstance = \App\Models\Commerce\ServiceInstance::where('hostname', $host)
            ->orWhereJsonContains('network_config->custom_domains', $host)
            ->exists();

        if ($takenInInstance) {
            return true;
        }

        // Check assigned secondary endpoints in multi-container services (e.g. studio.app.domain, s3.app.domain)
        return \App\Models\Commerce\ServiceInstance::all()->contains(function ($instance) use ($host) {
            $assigned = data_get($instance->network_config, 'assigned_endpoints', []);
            if (! is_array($assigned)) {
                return false;
            }
            foreach ($assigned as $endpoint) {
                if (($endpoint['host'] ?? null) === $host) {
                    return true;
                }
            }

            return false;
        });
    }

    /** Check whether a given subdomain is completely available under the apps wildcard domain. */
    public static function isSubdomainAvailable(string $label, ?int $ignoreApplicationId = null): bool
    {
        $apps = self::appsDomain();
        if ($apps === null) {
            return true;
        }

        $cleanLabel = strtolower(trim($label));
        if (self::isReserved($cleanLabel)) {
            return false;
        }

        $fullHost = "{$cleanLabel}.{$apps}";

        return ! self::takenByOther($fullHost, $ignoreApplicationId);
    }

    /**
     * Compute the next available slug using clean sequential naming (e.g. my-app-2, my-app-3)
     * rather than noisy random hashes.
     */
    public static function nextAvailableSlug(string $name, ?int $ignoreApplicationId = null, ?string $customSlug = null): string
    {
        $base = Str::slug($customSlug ?: $name);
        if (blank($base)) {
            $base = 'app';
        }

        $apps = self::appsDomain();
        if ($apps === null) {
            return $base;
        }

        if (self::isSubdomainAvailable($base, $ignoreApplicationId)) {
            return $base;
        }

        // First attempt clean sequential incrementation: my-app-2, my-app-3, ...
        for ($i = 2; $i <= 50; $i++) {
            $candidate = "{$base}-{$i}";
            if (self::isSubdomainAvailable($candidate, $ignoreApplicationId)) {
                return $candidate;
            }
        }

        // Fallback to random 4-char suffix if many exist
        for ($i = 0; $i < 20; $i++) {
            $candidate = $base.'-'.Str::lower(Str::random(4));
            if (self::isSubdomainAvailable($candidate, $ignoreApplicationId)) {
                return $candidate;
            }
        }

        return $base.'-'.Str::lower(Str::random(8));
    }

    /** A free, valid label for <label>.<apps_domain> built from the app name or an optional custom slug. */
    public static function slugFor(string $name, ?int $ignoreApplicationId = null, ?string $customSlug = null): string
    {
        return self::nextAvailableSlug($name, $ignoreApplicationId, $customSlug);
    }

    /**
     * Give a new client app its <name>.<apps_domain> address, unless it already has a real domain.
     */
    public static function assignSubdomain(Application $application): void
    {
        $apps = self::appsDomain();
        $team = $application->team();

        if ($apps === null || $team === null || (int) $team->id === 0) {
            return;
        }

        if ($application->build_pack === 'dockercompose') {
            return; // compose apps set domains per service
        }

        $hosts = self::hostsOf($application->fqdn);
        $onlyGenerated = $hosts === [] || collect($hosts)->every(fn ($h) => self::isGeneratedHost($h));

        if (! $onlyGenerated) {
            return;
        }

        $slug = self::slugFor((string) $application->name, $application->id);

        $application->forceFill(['fqdn' => "https://{$slug}.{$apps}"])->saveQuietly();
    }

    /**
     * Assign automatic HTTPS subdomains to exposable services of a multi-container template.
     *
     * @param \App\Models\Commerce\ServiceInstance|\App\Models\Service $service
     * @param string $templateName
     * @param string|null $customSlug
     * @param array<int, string> $selectedServiceNames List of service_name keys the user opted into exposing (empty means all suggested)
     * @return array<string, array{host: string, url: string, friendly_name: string, access_type: string, is_default: bool, internal_port: int}>
     */
    public static function assignTemplateSubdomains(
        mixed $service,
        string $templateName,
        ?string $customSlug = null,
        array $selectedServiceNames = []
    ): array {
        $apps = self::appsDomain() ?: config('hosting.default_domain', 'stxapp.io');
        $appName = $service->name ?? $templateName;
        $slug = self::slugFor($appName, null, $customSlug);

        $exposable = \App\Services\TemplateAccessService::getExposableServices($templateName);
        $assigned = [];
        $envRewrites = [];

        foreach ($exposable as $endpoint) {
            $serviceName = $endpoint['service_name'];
            $isDefault = (bool) ($endpoint['is_default'] ?? false);
            $prefix = trim((string) ($endpoint['subdomain_prefix'] ?? ''));
            $accessType = $endpoint['access_type'] ?? 'public';
            $friendlyName = $endpoint['friendly_name'] ?? $serviceName;

            // If user passed specific selections, respect it; otherwise check 'suggested'
            if (! empty($selectedServiceNames)) {
                if (! in_array($serviceName, $selectedServiceNames, true) && ! $isDefault) {
                    continue;
                }
            } elseif (! ($endpoint['suggested'] ?? true) && ! $isDefault) {
                continue;
            }

            $host = $isDefault || $prefix === ''
                ? "{$slug}.{$apps}"
                : "{$prefix}.{$slug}.{$apps}";

            $url = "https://{$host}";

            $assigned[$serviceName] = [
                'host' => $host,
                'url' => $url,
                'friendly_name' => $friendlyName,
                'access_type' => $accessType,
                'is_default' => $isDefault,
                'internal_port' => $endpoint['internal_port'] ?? 80,
            ];

            // Collect environment variable rewrites
            foreach ($endpoint['env_bindings'] ?? [] as $envKey) {
                $envRewrites[$envKey] = $url;
            }
        }

        // If $service is a ServiceInstance, update its network_config & hostname
        if ($service instanceof \App\Models\Commerce\ServiceInstance) {
            $defaultEndpoint = collect($assigned)->firstWhere('is_default', true) ?? reset($assigned);
            $primaryHost = $defaultEndpoint['host'] ?? "{$slug}.{$apps}";

            $config = $service->network_config ?? [];
            $config['assigned_endpoints'] = $assigned;
            $config['env_vars'] = array_merge($config['env_vars'] ?? [], $envRewrites);

            $service->update([
                'hostname' => $primaryHost,
                'network_config' => $config,
            ]);
        }

        // If $service is a Coolify Service model, update its ServiceApplications
        if ($service instanceof \App\Models\Service) {
            foreach ($assigned as $sName => $info) {
                $svcApp = $service->applications()->where('name', $sName)->first();
                if ($svcApp) {
                    $svcApp->update(['fqdn' => $info['url']]);
                }
            }
        }

        return $assigned;
    }
}
