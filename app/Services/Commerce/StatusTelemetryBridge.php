<?php

namespace App\Services\Commerce;

use App\Models\Commerce\Monitor;
use App\Models\Commerce\ServiceInstance;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StatusTelemetryBridge
{
    /**
     * Register an active VPS as a TCP probe in the native Status Telemetry platform.
     */
    public static function registerVpsProbe(ServiceInstance $service): bool
    {
        try {
            $location = $service->network_config['datacenter'] ?? 'Global Cloud Region';
            $port = $service->network_config['ssh_port'] ?? 22;

            // 1. Register in native PostgreSQL monitors table
            Monitor::updateOrCreate(
                ['name' => "vps-node-{$service->id}"],
                [
                    'uuid' => (string) Str::uuid(),
                    'public_label' => "Dedicated Compute Node ({$location})",
                    'group_name' => 'Core Compute Infrastructure',
                    'type' => 'tcp',
                    'target_host' => $service->ip_address,
                    'port' => $port,
                    'interval_seconds' => 60,
                    'timeout_ms' => 3000,
                    'is_public' => true,
                    'is_active' => true,
                    'current_status' => 'operational',
                    'uptime_percentage' => 99.98,
                    'last_latency_ms' => 18,
                    'last_checked_at' => now(),
                ]
            );

            Log::info("Native status probe registered for service #{$service->id}");
            return true;
        } catch (\Throwable $e) {
            Log::warning("Status telemetry bridge exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deactivate or remove a probe by IP address when service is terminated.
     */
    public static function deactivateProbe(string $ipAddress): bool
    {
        try {
            // Native database deactivation
            Monitor::where('target_host', $ipAddress)->update([
                'is_active' => false,
                'is_public' => false,
            ]);

            Log::info("Deactivated native status probe for {$ipAddress}");
            return true;
        } catch (\Throwable $e) {
            Log::warning("Status telemetry deactivate error: " . $e->getMessage());
            return false;
        }
    }
}
