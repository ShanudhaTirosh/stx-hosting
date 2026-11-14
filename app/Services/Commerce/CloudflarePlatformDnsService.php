<?php

namespace App\Services\Commerce;

use App\Exceptions\PlatformRestrictionException;
use App\Models\InstanceSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflarePlatformDnsService
{
    public static ?\Closure $mockHandler = null;

    protected int $timeout = 15;

    protected function http(string $token): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
        ])->timeout($this->timeout);
    }

    /**
     * Verify whether a Cloudflare API token is valid and active.
     *
     * @return array{valid: bool, status: string, message: string}
     */
    public function verifyToken(string $token): array
    {
        $token = trim($token);
        if (blank($token)) {
            return ['valid' => false, 'status' => 'missing', 'message' => 'Cloudflare API token cannot be empty.'];
        }

        if (self::$mockHandler) {
            $mockResult = (self::$mockHandler)('verify_token', ['token' => $token]);
            if ($mockResult !== null) {
                return $mockResult;
            }
        }

        try {
            $response = $this->http($token)->get('https://api.cloudflare.com/client/v4/user/tokens/verify');

            if ($response->successful() && $response->json('success') === true) {
                $status = $response->json('result.status', 'active');
                return [
                    'valid' => $status === 'active',
                    'status' => $status,
                    'message' => 'Token is valid and active.',
                ];
            }

            $errors = collect($response->json('errors', []))->pluck('message')->implode('; ');
            return [
                'valid' => false,
                'status' => 'invalid',
                'message' => $errors ?: 'Cloudflare rejected this API token. Please check permissions.',
            ];
        } catch (\Throwable $e) {
            Log::error("Cloudflare token verification error: {$e->getMessage()}");
            return [
                'valid' => false,
                'status' => 'error',
                'message' => 'Connection to Cloudflare API failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve all DNS zones accessible by this token.
     *
     * @return array<int, array{id: string, name: string, status: string}>
     */
    public function listZones(string $token): array
    {
        if (self::$mockHandler) {
            $mockResult = (self::$mockHandler)('list_zones', ['token' => $token]);
            if ($mockResult !== null) {
                return $mockResult;
            }
        }

        try {
            $response = $this->http($token)->get('https://api.cloudflare.com/client/v4/zones', [
                'per_page' => 50,
                'status' => 'active',
            ]);

            if ($response->successful() && $response->json('success') === true) {
                return collect($response->json('result', []))->map(fn ($z) => [
                    'id' => $z['id'],
                    'name' => strtolower($z['name']),
                    'status' => $z['status'] ?? 'active',
                ])->all();
            }

            return [];
        } catch (\Throwable $e) {
            Log::error("Cloudflare listZones error: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Automatically detect the Zone ID corresponding to a given base domain.
     */
    public function detectZone(string $token, string $domain): ?array
    {
        $domain = strtolower(trim($domain, " ./"));
        $zones = $this->listZones($token);

        foreach ($zones as $zone) {
            if ($zone['name'] === $domain || str_ends_with($domain, '.' . $zone['name'])) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Create or update a DNS record in Cloudflare.
     *
     * @return array{id: string, name: string, type: string, content: string, proxied: bool, action: string}
     */
    public function upsertRecord(
        string $token,
        string $zoneId,
        string $type,
        string $name,
        string $content,
        bool $proxied = true
    ): array {
        $name = strtolower(rtrim($name, '.'));

        if (self::$mockHandler) {
            $mockResult = (self::$mockHandler)('upsert_record', [
                'token' => $token,
                'zoneId' => $zoneId,
                'type' => $type,
                'name' => $name,
                'content' => $content,
                'proxied' => $proxied,
            ]);
            if ($mockResult !== null) {
                return $mockResult;
            }
        }

        // Check if record already exists
        $searchRes = $this->http($token)->get("https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records", [
            'type' => $type,
            'name' => $name,
        ]);

        $existing = null;
        if ($searchRes->successful() && $searchRes->json('success') === true) {
            $existing = collect($searchRes->json('result', []))->firstWhere('name', $name);
        }

        $payload = [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => 1, // Automatic TTL
            'proxied' => $proxied,
            'comment' => 'Managed automatically by STX Hosting Platform',
        ];

        if ($existing) {
            $recordId = $existing['id'];
            $res = $this->http($token)->put("https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records/{$recordId}", $payload);
            $action = 'updated';
        } else {
            $res = $this->http($token)->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records", $payload);
            $recordId = $res->json('result.id') ?? 'rec_' . md5($name);
            $action = 'created';
        }

        if (! $res->successful() || $res->json('success') !== true) {
            $msg = collect($res->json('errors', []))->pluck('message')->implode('; ');
            throw new PlatformRestrictionException("Cloudflare DNS update failed for {$name}: " . ($msg ?: $res->body()));
        }

        return [
            'id' => $recordId,
            'name' => $name,
            'type' => $type,
            'content' => $content,
            'proxied' => $proxied,
            'action' => $action,
        ];
    }

    /**
     * Automated provisioning of all platform DNS records:
     * - Root base domain (@)
     * - Wildcard apps subdomain (*.app.domain)
     * - mail, webmail, status, api
     *
     * @return array<string, array{name: string, type: string, content: string, proxied: bool, action: string}>
     */
    public function provisionPlatformDns(
        string $token,
        string $zoneId,
        string $ingressIp,
        string $baseDomain,
        string $appsDomain
    ): array {
        $baseDomain = strtolower(trim($baseDomain, " ./"));
        $appsDomain = strtolower(trim($appsDomain, " ./"));
        $results = [];

        // 1. Root A record: stxhostings.com
        $results['root'] = $this->upsertRecord(
            $token,
            $zoneId,
            'A',
            $baseDomain,
            $ingressIp,
            true
        );

        // 2. Wildcard A record: *.app.stxhostings.com
        $wildcardHost = "*." . $appsDomain;
        $results['wildcard_apps'] = $this->upsertRecord(
            $token,
            $zoneId,
            'A',
            $wildcardHost,
            $ingressIp,
            false // Must be unproxied (DNS only) if using custom SSL / Let's Encrypt directly on ingress
        );

        // 3. Apps Base A record: app.stxhostings.com
        $results['apps_base'] = $this->upsertRecord(
            $token,
            $zoneId,
            'A',
            $appsDomain,
            $ingressIp,
            true
        );

        // 4. Mail server (SMTP/IMAP direct ports require unproxied DNS)
        $mailHost = "mail.{$baseDomain}";
        $results['mail'] = $this->upsertRecord(
            $token,
            $zoneId,
            'A',
            $mailHost,
            $ingressIp,
            false
        );

        // 5. Webmail UI
        $webmailHost = "webmail.{$baseDomain}";
        $results['webmail'] = $this->upsertRecord(
            $token,
            $zoneId,
            'A',
            $webmailHost,
            $ingressIp,
            true
        );

        // 6. Public Telemetry Status Page
        $statusHost = "status.{$baseDomain}";
        $results['status'] = $this->upsertRecord(
            $token,
            $zoneId,
            'A',
            $statusHost,
            $ingressIp,
            true
        );

        // 7. API Gateway
        $apiHost = "api.{$baseDomain}";
        $results['api'] = $this->upsertRecord(
            $token,
            $zoneId,
            'A',
            $apiHost,
            $ingressIp,
            true
        );

        // Persist to InstanceSettings
        $settings = instanceSettings();
        if ($settings) {
            $settings->update([
                'base_platform_domain' => $baseDomain,
                'apps_domain' => $appsDomain,
                'cloudflare_api_token' => $token,
                'cloudflare_zone_id' => $zoneId,
                'platform_domains_initialized_at' => now(),
            ]);
        }

        return $results;
    }
}

