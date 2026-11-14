<?php

namespace App\Services\Commerce;

use App\Exceptions\PlatformRestrictionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WildDuckProvisioningService
{
    protected string $apiUrl;
    protected ?string $apiToken;
    protected int $timeout;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) config('mail.wildduck.api_url', env('WILDDUCK_API_URL', 'http://127.0.0.1:8080')), '/');
        $this->apiToken = config('mail.wildduck.api_token', env('WILDDUCK_API_TOKEN'));
        $this->timeout = (int) config('mail.wildduck.timeout', 15);
    }

    public function isConfigured(): bool
    {
        return filled($this->apiUrl) && filled($this->apiToken);
    }

    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'X-Access-Token' => $this->apiToken,
            'Content-Type' => 'application/json',
        ])->timeout($this->timeout);
    }

    /**
     * Register a new customer domain in WildDuck and generate its 2048-bit DKIM keypair.
     *
     * @return array{domain_id: string, dkim_selector: string, dkim_public_key: string}
     */
    public function registerDomain(string $domain): array
    {
        $domain = strtolower(trim($domain));

        if (! $this->isConfigured()) {
            // Mock in dev/testing environments without a live WildDuck daemon
            return [
                'domain_id' => 'mock_dom_'.md5($domain),
                'dkim_selector' => 'wildduck',
                'dkim_public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0mockKeyForDevelopmentTestingOnly...',
            ];
        }

        try {
            $response = $this->http()->post("{$this->apiUrl}/dkim", [
                'domain' => $domain,
                'selector' => 'wildduck',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'domain_id' => $data['id'] ?? (string) ($data['domain'] ?? md5($domain)),
                    'dkim_selector' => $data['selector'] ?? 'wildduck',
                    'dkim_public_key' => $data['publicKey'] ?? '',
                ];
            }

            Log::error('WildDuck DKIM domain registration failed', ['domain' => $domain, 'response' => $response->body()]);
            throw new PlatformRestrictionException('Could not register domain in mail server: '.($response->json('message') ?? $response->body()));
        } catch (\Throwable $e) {
            Log::error("WildDuck registerDomain exception: {$e->getMessage()}");
            throw new PlatformRestrictionException('Mail server connection error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete a domain's DKIM key from WildDuck.
     */
    public function deleteDomain(string $wildduckDomainId): bool
    {
        if (! $this->isConfigured()) {
            return true;
        }

        try {
            $response = $this->http()->delete("{$this->apiUrl}/dkim/{$wildduckDomainId}");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("WildDuck deleteDomain exception: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Create a user mailbox under a customer domain.
     *
     * @return array{wildduck_user_id: string, address: string}
     */
    public function createMailbox(string $domain, string $username, string $displayName, string $password, ?int $quotaMb = null): array
    {
        $domain = strtolower(trim($domain));
        $username = strtolower(trim($username));
        $fullAddress = "{$username}@{$domain}";

        if (! $this->isConfigured()) {
            return [
                'wildduck_user_id' => 'mock_user_'.md5($fullAddress),
                'address' => $fullAddress,
            ];
        }

        $payload = [
            'username' => $fullAddress,
            'password' => $password,
            'name' => $displayName,
            'address' => $fullAddress,
        ];

        if ($quotaMb && $quotaMb > 0) {
            $payload['quota'] = $quotaMb * 1024 * 1024; // Convert MB to Bytes
        }

        try {
            $response = $this->http()->post("{$this->apiUrl}/users", $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'wildduck_user_id' => $data['id'] ?? (string) $data['user'],
                    'address' => $fullAddress,
                ];
            }

            Log::error('WildDuck createMailbox failed', ['address' => $fullAddress, 'response' => $response->body()]);
            throw new PlatformRestrictionException('Could not create mailbox in mail server: '.($response->json('message') ?? $response->body()));
        } catch (\Throwable $e) {
            Log::error("WildDuck createMailbox exception: {$e->getMessage()}");
            throw new PlatformRestrictionException('Mail server creation error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Update an existing mailbox storage quota.
     */
    public function updateMailboxQuota(string $wildduckUserId, int $quotaMb): bool
    {
        if (! $this->isConfigured()) {
            return true;
        }

        try {
            $response = $this->http()->put("{$this->apiUrl}/users/{$wildduckUserId}", [
                'quota' => $quotaMb * 1024 * 1024,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("WildDuck updateMailboxQuota exception: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Reset or update an existing mailbox password.
     */
    public function resetMailboxPassword(string $wildduckUserId, string $newPassword): bool
    {
        if (! $this->isConfigured()) {
            return true;
        }

        try {
            $response = $this->http()->put("{$this->apiUrl}/users/{$wildduckUserId}", [
                'password' => $newPassword,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("WildDuck resetMailboxPassword exception: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Create an email alias for an existing user mailbox.
     */
    public function createAlias(string $wildduckUserId, string $aliasAddress): bool
    {
        $aliasAddress = strtolower(trim($aliasAddress));

        if (! $this->isConfigured()) {
            return true;
        }

        try {
            $response = $this->http()->post("{$this->apiUrl}/users/{$wildduckUserId}/addresses", [
                'address' => $aliasAddress,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("WildDuck createAlias exception: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Delete an email alias from an existing user mailbox.
     */
    public function deleteAlias(string $wildduckUserId, string $aliasAddress): bool
    {
        $aliasAddress = strtolower(trim($aliasAddress));

        if (! $this->isConfigured()) {
            return true;
        }

        try {
            $response = $this->http()->delete("{$this->apiUrl}/users/{$wildduckUserId}/addresses/{$aliasAddress}");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("WildDuck deleteAlias exception: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Enable or disable Catch-All email routing for a user on a given domain.
     */
    public function setCatchAll(string $wildduckUserId, string $domain, bool $enable): bool
    {
        $domain = strtolower(trim($domain));

        if (! $this->isConfigured()) {
            return true;
        }

        try {
            if ($enable) {
                $response = $this->http()->post("{$this->apiUrl}/users/{$wildduckUserId}/addresses", [
                    'address' => "*@{$domain}",
                    'allowWildcard' => true,
                ]);
            } else {
                $response = $this->http()->delete("{$this->apiUrl}/users/{$wildduckUserId}/addresses/*@{$domain}");
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("WildDuck setCatchAll exception: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Enable or suspend a mailbox account in WildDuck.
     */
    public function updateMailboxActive(string $wildduckUserId, bool $active): bool
    {
        if (! $this->isConfigured()) {
            return true;
        }

        try {
            $response = $this->http()->put("{$this->apiUrl}/users/{$wildduckUserId}", [
                'disabled' => ! $active,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("WildDuck updateMailboxActive exception: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Delete a mailbox from WildDuck.
     */
    public function deleteMailbox(string $wildduckUserId): bool
    {
        if (! $this->isConfigured()) {
            return true;
        }

        try {
            $response = $this->http()->delete("{$this->apiUrl}/users/{$wildduckUserId}");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("WildDuck deleteMailbox exception: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Retrieve live storage usage in bytes for a mailbox.
     */
    public function getMailboxUsageBytes(string $wildduckUserId): int
    {
        if (! $this->isConfigured()) {
            return 1024 * 1024 * 12; // 12 MB mock
        }

        try {
            $response = $this->http()->get("{$this->apiUrl}/users/{$wildduckUserId}");

            if ($response->successful()) {
                $user = $response->json();

                return (int) ($user['storage']['used'] ?? 0);
            }

            return 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Retrieve fleet-wide telemetry & health status from WildDuck and ZoneMTA.
     *
     * @return array{healthy: bool, latency_ms: int, version: string, storage_used_bytes: int, active_users: int, active_domains: int}
     */
    public function getFleetStats(): array
    {
        if (! $this->isConfigured()) {
            return [
                'healthy' => true,
                'latency_ms' => 4,
                'version' => '1.55.2 (Mock / Container)',
                'storage_used_bytes' => 1024 * 1024 * 1420, // 1.42 GB mock
                'active_users' => 14,
                'active_domains' => 5,
            ];
        }

        $startTime = microtime(true);
        try {
            $response = $this->http()->get("{$this->apiUrl}/health");
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $healthData = $response->json();

                // Fetch storage totals if available
                $storageResponse = $this->http()->get("{$this->apiUrl}/storage");
                $storageBytes = $storageResponse->successful() ? (int) ($storageResponse->json('used') ?? 0) : 0;

                return [
                    'healthy' => true,
                    'latency_ms' => $latencyMs,
                    'version' => $healthData['version'] ?? '1.55.2',
                    'storage_used_bytes' => $storageBytes,
                    'active_users' => (int) ($healthData['users'] ?? 0),
                    'active_domains' => (int) ($healthData['domains'] ?? 0),
                ];
            }

            return [
                'healthy' => false,
                'latency_ms' => $latencyMs,
                'version' => 'unknown',
                'storage_used_bytes' => 0,
                'active_users' => 0,
                'active_domains' => 0,
            ];
        } catch (\Throwable $e) {
            Log::warning("WildDuck getFleetStats failed: {$e->getMessage()}");

            return [
                'healthy' => false,
                'latency_ms' => 0,
                'version' => 'offline',
                'storage_used_bytes' => 0,
                'active_users' => 0,
                'active_domains' => 0,
            ];
        }
    }
}
