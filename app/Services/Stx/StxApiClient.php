<?php

namespace App\Services\Stx;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StxApiClient
{
    protected string $baseUrl;

    protected string $apiToken;

    public function __construct(?string $baseUrl = null, ?string $apiToken = null)
    {
        $defaultUrl = config('services.stx.url', config('services.coolify.url', env('STX_API_URL', env('COOLIFY_API_URL', 'http://127.0.0.1:8000/api/v1'))));
        $this->baseUrl = rtrim($baseUrl ?? $defaultUrl, '/');
        $this->apiToken = $apiToken ?? config('services.stx.token', config('services.coolify.token', env('STX_API_TOKEN', env('COOLIFY_API_TOKEN', ''))));
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
            Log::error("StxApiClient deploy error: {$e->getMessage()}");

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Fetch resource details by UUID.
     */
    public function getResource(string $uuid): ?array
    {
        try {
            $res = $this->client()->get("/resources/{$uuid}");

            return $res->successful() ? $res->json() : null;
        } catch (\Throwable $e) {
            Log::error("StxApiClient getResource error: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Fetch application deployments.
     */
    public function getDeployments(string $uuid): array
    {
        try {
            $res = $this->client()->get("/applications/{$uuid}/deployments");

            return $res->successful() ? ($res->json() ?? []) : [];
        } catch (\Throwable $e) {
            Log::error("StxApiClient getDeployments error: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Execute a remote command via STX Hosting API.
     */
    public function executeCommand(string $serverUuid, string $command): ?Response
    {
        try {
            return $this->client()->post("/servers/{$serverUuid}/execute", [
                'command' => $command,
            ]);
        } catch (\Throwable $e) {
            Log::error("StxApiClient executeCommand error: {$e->getMessage()}");

            return null;
        }
    }
}
