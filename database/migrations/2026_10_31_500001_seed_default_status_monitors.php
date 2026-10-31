<?php

use App\Models\Commerce\Monitor;
use App\Models\Commerce\SystemIncident;
use App\Models\Commerce\IncidentUpdate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Monitor::count() === 0) {
            $defaultMonitors = [
                [
                    'name' => 'Edge Ingress & Reverse Proxy',
                    'public_label' => 'Traefik Ingress Gateway',
                    'group_name' => 'Core Infrastructure',
                    'type' => 'http',
                    'target_url' => 'http://127.0.0.1:8000/api/v1/health',
                    'target_host' => '127.0.0.1',
                    'port' => 80,
                    'interval_seconds' => 60,
                    'timeout_ms' => 3000,
                    'is_public' => true,
                    'is_active' => true,
                    'current_status' => 'operational',
                    'uptime_percentage' => 99.99,
                    'last_latency_ms' => 8,
                    'display_order' => 1,
                ],
                [
                    'name' => 'Platform API Engine',
                    'public_label' => 'STX Cloud Control Plane',
                    'group_name' => 'Core Infrastructure',
                    'type' => 'http',
                    'target_url' => 'http://127.0.0.1:8000/status',
                    'target_host' => '127.0.0.1',
                    'port' => 8080,
                    'interval_seconds' => 60,
                    'timeout_ms' => 4000,
                    'is_public' => true,
                    'is_active' => true,
                    'current_status' => 'operational',
                    'uptime_percentage' => 100.00,
                    'last_latency_ms' => 14,
                    'display_order' => 2,
                ],
                [
                    'name' => 'Realtime WebSocket Fabric',
                    'public_label' => 'Reverb Event Broker',
                    'group_name' => 'Networking & Messaging',
                    'type' => 'tcp',
                    'target_url' => null,
                    'target_host' => '127.0.0.1',
                    'port' => 6001,
                    'interval_seconds' => 60,
                    'timeout_ms' => 2500,
                    'is_public' => true,
                    'is_active' => true,
                    'current_status' => 'operational',
                    'uptime_percentage' => 99.98,
                    'last_latency_ms' => 5,
                    'display_order' => 3,
                ],
                [
                    'name' => 'PostgreSQL Database Engine',
                    'public_label' => 'Primary Data Store',
                    'group_name' => 'Database Clusters',
                    'type' => 'tcp',
                    'target_url' => null,
                    'target_host' => 'coolify-db',
                    'port' => 5432,
                    'interval_seconds' => 60,
                    'timeout_ms' => 2000,
                    'is_public' => true,
                    'is_active' => true,
                    'current_status' => 'operational',
                    'uptime_percentage' => 100.00,
                    'last_latency_ms' => 3,
                    'display_order' => 4,
                ],
                [
                    'name' => 'Redis High-Speed Cache',
                    'public_label' => 'Memory Queue & Session Cache',
                    'group_name' => 'Database Clusters',
                    'type' => 'tcp',
                    'target_url' => null,
                    'target_host' => 'coolify-redis',
                    'port' => 6379,
                    'interval_seconds' => 60,
                    'timeout_ms' => 2000,
                    'is_public' => true,
                    'is_active' => true,
                    'current_status' => 'operational',
                    'uptime_percentage' => 100.00,
                    'last_latency_ms' => 2,
                    'display_order' => 5,
                ],
                [
                    'name' => 'Hetzner FSN1 Compute Node',
                    'public_label' => 'Dedicated Compute Hypervisor',
                    'group_name' => 'Compute Infrastructure',
                    'type' => 'tcp',
                    'target_url' => null,
                    'target_host' => '127.0.0.1',
                    'port' => 22,
                    'interval_seconds' => 60,
                    'timeout_ms' => 3000,
                    'is_public' => true,
                    'is_active' => true,
                    'current_status' => 'operational',
                    'uptime_percentage' => 99.95,
                    'last_latency_ms' => 19,
                    'display_order' => 6,
                ],
            ];

            foreach ($defaultMonitors as $m) {
                Monitor::create(array_merge($m, ['uuid' => (string) Str::uuid()]));
            }
        }

        if (SystemIncident::count() === 0) {
            $incident = SystemIncident::create([
                'uuid' => (string) Str::uuid(),
                'title' => 'Scheduled Platform Infrastructure Maintenance Completed',
                'incident_type' => 'maintenance',
                'impact_level' => 'none',
                'status' => 'resolved',
                'severity' => 'operational',
                'message' => 'Routine updates and network routing optimizations successfully completed across European and Asian nodes.',
                'resolved_at' => now()->subHours(2),
                'scheduled_start_at' => now()->subHours(4),
                'scheduled_end_at' => now()->subHours(2),
            ]);

            IncidentUpdate::create([
                'system_incident_id' => $incident->id,
                'status' => 'resolved',
                'message' => 'All nodes verified operating at 100% capacity with reduced latency across all transit providers.',
                'created_at' => now()->subHours(2),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
