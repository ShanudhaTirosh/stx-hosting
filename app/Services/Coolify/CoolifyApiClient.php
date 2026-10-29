<?php

namespace App\Services\Coolify;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoolifyApiClient
{
    protected string $baseUrl;
    protected string $apiToken;

    public function __construct(?string $baseUrl = null, ?string $apiToken = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('services.coolify.url', env('COOLIFY_API_URL', 'http://127.0.0.1:8000/api/v1')), '/');
        $this->apiToken = $apiToken ?? config('services.coolify.token', env('COOLIFY_API_TOKEN', ''));
    }

    /**
     * Create an authorized HTTP request instance.
     */
    protected function client()
    {
        $client = Http::baseUrl($this->baseUrl)
            ->timeout(15)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

        if (! empty($this->apiToken)) {
            $client->withToken($this->apiToken);
        }

        return $client;
    }

    /**
     * Deploy an application or service by UUID.
     */
    public function deploy(string $uuid, bool $force = false): array
    {
        try {
            $res = $this->client()->post('/deploy', [
                'uuid' => $uuid,
                'force' => $force,
            ]);

            return $res->json() ?? [];
        } catch (\Throwable $e) {
            Log::error("CoolifyApiClient::deploy exception: {$e->getMessage()}");
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get deployment details and logs by deployment UUID.
     */
    public function getDeployment(string $uuid): array
    {
        try {
            $res = $this->client()->get("/deployments/{$uuid}");
            return $res->json() ?? [];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Start, stop, or restart an application.
     */
    public function controlApplication(string $uuid, string $action): array
    {
        try {
            $res = $this->client()->post("/applications/{$uuid}/{$action}");
            return $res->json() ?? [];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Start, stop, or restart a database.
     */
    public function controlDatabase(string $uuid, string $action): array
    {
        try {
            $res = $this->client()->post("/databases/{$uuid}/{$action}");
            return $res->json() ?? [];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Fetch application details.
     */
    public function getApplication(string $uuid): array
    {
        try {
            $res = $this->client()->get("/applications/{$uuid}");
            return $res->json() ?? [];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Update application environment variables and domains.
     */
    public function updateApplication(string $uuid, array $payload): array
    {
        try {
            $res = $this->client()->patch("/applications/{$uuid}", $payload);
            return $res->json() ?? [];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

