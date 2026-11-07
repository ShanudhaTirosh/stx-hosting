<?php

namespace App\Services\Commerce;

use App\Exceptions\PlatformRestrictionException;
use App\Models\CustomDomain;
use App\Models\Team;
use Illuminate\Support\Str;

/**
 * Domain ownership by DNS TXT record: _stx-verify.<domain> must contain stx-verify=<token>.
 * Only a verified domain can be put on an application (PlatformDomainService).
 */
class CustomDomainService
{
    /** Replace in tests: fn (string $host): array<int, string> of TXT strings. */
    public static ?\Closure $txtResolver = null;

    public static function normalize(string $input): string
    {
        $host = strtolower(trim($input));
        $host = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host);
        $host = trim(explode('/', $host)[0], '. ');
        $host = explode(':', $host)[0];

        if (strlen($host) > 253 || ! preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) {
            throw new PlatformRestrictionException('That does not look like a valid domain name, for example example.com.');
        }

        return $host;
    }

    public function request(Team $team, string $input): CustomDomain
    {
        $domain = self::normalize($input);

        if (PlatformDomainService::isPlatformHost($domain) || PlatformDomainService::isGeneratedHost($domain)) {
            throw new PlatformRestrictionException('That address already belongs to the platform. Every app gets one automatically.');
        }

        $takenByOtherTeam = CustomDomain::verified()
            ->where('team_id', '!=', $team->id)
            ->get()
            ->contains(fn (CustomDomain $d) => $domain === $d->domain || str_ends_with($domain, '.'.$d->domain) || str_ends_with($d->domain, '.'.$domain));

        if ($takenByOtherTeam) {
            throw new PlatformRestrictionException('That domain is already verified by another account.');
        }

        return CustomDomain::firstOrCreate(
            ['team_id' => $team->id, 'domain' => $domain],
            ['token' => Str::lower(Str::random(32))],
        );
    }

    public function verify(CustomDomain $domain): bool
    {
        $domain->forceFill(['last_checked_at' => now()])->save();

        $expected = $domain->txtValue();
        $found = collect($this->txtRecords($domain->txtHost()))
            ->contains(fn ($txt) => trim((string) $txt) === $expected);

        if ($found && ! $domain->isVerified()) {
            $domain->forceFill(['verified_at' => now()])->save();
        }

        return $found;
    }

    /** Whether the domain already points at our ingress (informational: needed for HTTPS to work). */
    public function pointsAtIngress(CustomDomain $domain): ?bool
    {
        $ip = config('hosting.ingress_ip');

        if (blank($ip)) {
            return null;
        }

        return AppProvisioningService::verifyDomainDns($domain->domain, $ip);
    }

    /** @return array<int, string> */
    private function txtRecords(string $host): array
    {
        if (self::$txtResolver) {
            return (self::$txtResolver)($host);
        }

        $records = @dns_get_record($host, DNS_TXT) ?: [];

        return collect($records)->map(fn ($r) => (string) ($r['txt'] ?? ''))->all();
    }
}
