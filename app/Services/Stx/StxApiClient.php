<?php

namespace App\Services\Stx;

use App\Services\Coolify\CoolifyApiClient;

class StxApiClient extends CoolifyApiClient
{
    public function __construct(?string $baseUrl = null, ?string $apiToken = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('services.stx.url', config('services.coolify.url', env('STX_API_URL', env('COOLIFY_API_URL', 'http://127.0.0.1:8000/api/v1')))), '/');
        $this->apiToken = $apiToken ?? config('services.stx.token', config('services.coolify.token', env('STX_API_TOKEN', env('COOLIFY_API_TOKEN', ''))));
    }
}
