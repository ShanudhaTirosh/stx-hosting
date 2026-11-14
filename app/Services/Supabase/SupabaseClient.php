<?php

namespace App\Services\Supabase;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseClient
{
    protected ?string $url;
    protected ?string $anonKey;
    protected ?string $serviceRoleKey;

    public function __construct()
    {
        $this->url = rtrim((string) config('supabase.url'), '/');
        $this->anonKey = config('supabase.key');
        $this->serviceRoleKey = config('supabase.service_role_key');
    }

    public function isConfigured(): bool
    {
        return filled($this->url) && (filled($this->anonKey) || filled($this->serviceRoleKey));
    }

    /**
     * Get a configured HTTP client with appropriate authorization headers.
     */
    protected function http(bool $useServiceRole = true): \Illuminate\Http\Client\PendingRequest
    {
        $key = ($useServiceRole && filled($this->serviceRoleKey)) ? $this->serviceRoleKey : $this->anonKey;

        return Http::withHeaders([
            'apikey' => $key,
            'Authorization' => "Bearer {$key}",
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
        ])->timeout(15);
    }

    /**
     * Query a PostgREST table.
     */
    public function from(string $table): SupabaseQueryBuilder
    {
        return new SupabaseQueryBuilder($this, $table);
    }

    /**
     * Direct REST request against PostgREST endpoint.
     */
    public function request(string $method, string $path, array $data = [], array $queryParams = [], bool $useServiceRole = true): ?Response
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $endpoint = "{$this->url}/rest/v1/{$path}";

        try {
            return $this->http($useServiceRole)->send($method, $endpoint, [
                'query' => $queryParams,
                'json' => empty($data) ? null : $data,
            ]);
        } catch (\Throwable $e) {
            Log::error("Supabase REST exception on {$path}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Invoke a Supabase Edge Function.
     */
    public function invokeFunction(string $functionName, array $payload = [], bool $useServiceRole = true): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $endpoint = "{$this->url}/functions/v1/{$functionName}";

        try {
            $response = $this->http($useServiceRole)->post($endpoint, $payload);

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::error("Supabase Edge Function invocation exception for {$functionName}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Upload a file to a Supabase Storage bucket.
     */
    public function uploadFile(string $bucket, string $path, string $contents, string $mimeType = 'application/octet-stream', bool $upsert = true): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $endpoint = "{$this->url}/storage/v1/object/{$bucket}/{$path}";
        $key = $this->serviceRoleKey ?: $this->anonKey;

        try {
            $response = Http::withHeaders([
                'apikey' => $key,
                'Authorization' => "Bearer {$key}",
                'Content-Type' => $mimeType,
                'x-upsert' => $upsert ? 'true' : 'false',
            ])->timeout(30)->withBody($contents, $mimeType)->post($endpoint);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("Supabase storage upload exception on {$bucket}/{$path}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Get a public or signed URL for a file in Supabase Storage.
     */
    public function getStorageUrl(string $bucket, string $path, int $expiresInSeconds = 3600): string
    {
        return "{$this->url}/storage/v1/object/public/{$bucket}/{$path}";
    }

    /**
     * Generate a time-limited signed URL for a private file in Supabase Storage.
     */
    public function createSignedUrl(string $bucket, string $path, int $expiresInSeconds = 900): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $endpoint = "{$this->url}/storage/v1/object/sign/{$bucket}/{$path}";
        $key = $this->serviceRoleKey ?: $this->anonKey;

        try {
            $response = Http::withHeaders([
                'apikey' => $key,
                'Authorization' => "Bearer {$key}",
                'Content-Type' => 'application/json',
            ])->timeout(10)->post($endpoint, [
                'expiresIn' => $expiresInSeconds,
            ]);

            if ($response->successful()) {
                $signedPath = $response->json('signedURL');
                if ($signedPath) {
                    return str_starts_with($signedPath, 'http') ? $signedPath : "{$this->url}/storage/v1{$signedPath}";
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::error("Supabase signed URL exception for {$bucket}/{$path}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Broadcast an ephemeral realtime event via Supabase Realtime HTTP endpoint.
     */
    public function broadcastRealtime(string $channel, string $event, array $payload): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $endpoint = "{$this->url}/realtime/v1/api/broadcast";
        $key = $this->serviceRoleKey ?: $this->anonKey;

        try {
            $response = Http::withHeaders([
                'apikey' => $key,
                'Authorization' => "Bearer {$key}",
                'Content-Type' => 'application/json',
            ])->timeout(5)->post($endpoint, [
                'messages' => [
                    [
                        'topic' => "realtime:{$channel}",
                        'event' => $event,
                        'payload' => $payload,
                    ],
                ],
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("Supabase realtime broadcast exception on channel {$channel}: {$e->getMessage()}");

            return false;
        }
    }
}

