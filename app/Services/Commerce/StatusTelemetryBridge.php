<?php

namespace App\Services\Commerce;

use App\Models\Commerce\ServiceInstance;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StatusTelemetryBridge
{
    /**
     * Register an active VPS as a TCP port 22 probe in the Status platform.
     */
    public static function registerVpsProbe(ServiceInstance $service): bool
    {
        $statusBaseUrl = config('services.status.url', env('STATUS_APP_URL', 'http://localhost:3003'));
        $adminPassword = config('services.status.admin_password', env('STATUS_ADMIN_PASSWORD', 'changeme'));

        try {
            // 1. Authenticate with Status admin API to obtain session cookie
            $loginRes = Http::timeout(3)->post("{$statusBaseUrl}/api/admin/auth/login", [
                'password' => $adminPassword,
            ]);

            if (! $loginRes->successful()) {
                Log::warning("Status telemetry authentication failed: " . $loginRes->body());
                return false;
            }

            $cookies = $loginRes->cookies();

            // 2. Register monitor probe
            $monitorRes = Http::timeout(3)
                ->withCookies($cookies->toArray(), parse_url($statusBaseUrl, PHP_URL_HOST))
                ->post("{$statusBaseUrl}/api/admin/monitors", [
                    'name' => "VPS: {$service->hostname} ({$service->ip_address})",
                    'type' => 'tcp',
                    'host' => $service->ip_address,
                    'port' => 22,
                    'interval_seconds' => 60,
                    'timeout_ms' => 5000,
                    'enabled' => true,
                ]);

            if ($monitorRes->successful()) {
                Log::info("Registered status probe for service #{$service->id} ({$service->ip_address})");
                return true;
            }

            Log::warning("Failed to register status probe: " . $monitorRes->body());
            return false;
        } catch (\Throwable $e) {
            Log::warning("Status telemetry bridge exception: " . $e->getMessage());
            return false;
        }
    }
}

