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
    public static function appsDomain(): ?string
    {
        $domain = strtolower(trim((string) config('hosting.apps_domain'), " ./"));

        return $domain !== '' ? $domain : null;
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

    private static function takenByOther(string $host, ?int $ignoreApplicationId): bool
    {
        return Application::withoutGlobalScopes()
            ->where('fqdn', 'like', '%'.$host.'%')
            ->when($ignoreApplicationId, fn ($q) => $q->where('id', '!=', $ignoreApplicationId))
            ->get(['id', 'fqdn'])
            ->contains(fn ($app) => in_array($host, self::hostsOf($app->fqdn), true));
    }

    /** A free, valid label for <label>.<apps_domain> built from the app name. */
    public static function slugFor(string $name, ?int $ignoreApplicationId = null): string
    {
        $slug = Str::of(Str::slug($name))->limit(40, '')->trim('-')->toString();

        if (strlen($slug) < 3) {
            $slug = 'app-'.Str::lower(Str::random(5));
        }

        $apps = self::appsDomain();
        $candidate = $slug;

        for ($i = 0; $i < 20; $i++) {
            $reserved = self::isReserved($candidate);
            if (! $reserved && ($apps === null || ! self::takenByOther($candidate.'.'.$apps, $ignoreApplicationId))) {
                return $candidate;
            }
            $candidate = $slug.'-'.Str::lower(Str::random(4));
        }

        return $slug.'-'.Str::lower(Str::random(8));
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
}
