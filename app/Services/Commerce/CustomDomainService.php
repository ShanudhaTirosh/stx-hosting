<?php

namespace App\Services\Commerce;

use App\Exceptions\PlatformRestrictionException;
use App\Models\CustomDomain;
use App\Models\Team;
use Illuminate\Support\Str;

/**
 * Domain management and automated DNS/SSL verification.
 * Supports dual-mode verification:
 *   1. Direct A / CNAME record check pointing to platform ingress
 *   2. Cryptographic TXT verification token (_stx-verify.<domain>) for ownership disputes
 */
class CustomDomainService
{
    /** Replace in tests: fn (string $host): array<int, string> of TXT strings. */
    public static ?\Closure $txtResolver = null;

    /** Replace in tests: fn (string $domain, string $expectedIp): bool */
    public static ?\Closure $dnsResolver = null;

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

        $isApex = substr_count($domain, '.') === 1;
        $ingressIp = config('hosting.ingress_ip') ?: '127.0.0.1';
        $appsDomain = PlatformDomainService::appsDomain() ?: 'ingress.stxapp.io';

        $recordType = $isApex ? 'A' : 'CNAME';
        $expectedTarget = $isApex ? $ingressIp : $appsDomain;

        return CustomDomain::firstOrCreate(
            ['team_id' => $team->id, 'domain' => $domain],
            [
                'token' => Str::lower(Str::random(32)),
                'status' => CustomDomain::STATUS_PENDING_DNS,
                'record_type' => $recordType,
                'expected_target' => $expectedTarget,
            ]
        );
    }

    public function verify(CustomDomain $domain): bool
    {
        $domain->increment('check_attempts');
        $domain->forceFill(['last_checked_at' => now()])->save();

        // 1. Direct DNS check (A / CNAME)
        $pointsAtIngress = $this->pointsAtIngress($domain);

        // 2. Fallback TXT check (_stx-verify.<domain>)
        $expectedTxt = $domain->txtValue();
        $txtFound = collect($this->txtRecords($domain->txtHost()))
            ->contains(fn ($txt) => trim((string) $txt) === $expectedTxt);

        if ($pointsAtIngress || $txtFound) {
            $domain->forceFill([
                'verified_at' => $domain->verified_at ?: now(),
                'status' => CustomDomain::STATUS_ACTIVE,
                'ssl_issued_at' => $domain->ssl_issued_at ?: now(),
                'ssl_expires_at' => now()->addDays(90),
                'last_error_message' => null,
            ])->save();

            return true;
        }

        $domain->forceFill([
            'status' => CustomDomain::STATUS_PENDING_DNS,
            'last_error_message' => "DNS records for {$domain->domain} do not point to {$domain->expected_target} yet.",
        ])->save();

        return false;
    }

    /** Whether the domain already points at our ingress. */
    public function pointsAtIngress(CustomDomain $domain): ?bool
    {
        $ip = config('hosting.ingress_ip');

        if (blank($ip)) {
            return null;
        }

        if (self::$dnsResolver) {
            return (self::$dnsResolver)($domain->domain, $ip);
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
