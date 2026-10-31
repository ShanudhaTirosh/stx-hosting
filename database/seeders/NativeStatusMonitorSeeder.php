<?php

namespace Database\Seeders;

use App\Models\Commerce\Monitor;
use App\Models\Commerce\SystemIncident;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NativeStatusMonitorSeeder extends Seeder
{
    public function run(): void
    {
        $defaultMonitors = [
            // Group 1: Global Edge & Routing
            [
                'name' => 'edge-frankfurt-01',
                'public_label' => 'Edge Gateway — Frankfurt (DE-01)',
                'group_name' => 'Global Edge & Routing',
                'type' => 'tcp',
                'target_host' => '127.0.0.1',
                'port' => 80,
                'uptime_percentage' => 99.99,
                'last_latency_ms' => 12,
                'current_status' => 'operational',
                'display_order' => 1,
            ],
            [
                'name' => 'edge-singapore-01',
                'public_label' => 'Edge Gateway — Singapore (SG-01)',
                'group_name' => 'Global Edge & Routing',
                'type' => 'tcp',
                'target_host' => '127.0.0.1',
                'port' => 80,
                'uptime_percentage' => 99.98,
                'last_latency_ms' => 24,
                'current_status' => 'operational',
                'display_order' => 2,
            ],
            [
                'name' => 'edge-us-east-01',
                'public_label' => 'Edge Gateway — US East (VA-01)',
                'group_name' => 'Global Edge & Routing',
                'type' => 'tcp',
                'target_host' => '127.0.0.1',
                'port' => 80,
                'uptime_percentage' => 100.00,
                'last_latency_ms' => 38,
                'current_status' => 'operational',
                'display_order' => 3,
            ],

            // Group 2: Core Compute Infrastructure
            [
                'name' => 'node-compute-alpha',
                'public_label' => 'High-Performance Node Alpha (Frankfurt)',
                'group_name' => 'Core Compute Infrastructure',
                'type' => 'tcp',
                'target_host' => '127.0.0.1',
                'port' => 22,
                'uptime_percentage' => 99.95,
                'last_latency_ms' => 14,
                'current_status' => 'operational',
                'display_order' => 4,
            ],
            [
                'name' => 'node-compute-bravo',
                'public_label' => 'High-Performance Node Bravo (Singapore)',
                'group_name' => 'Core Compute Infrastructure',
                'type' => 'tcp',
                'target_host' => '127.0.0.1',
                'port' => 22,
                'uptime_percentage' => 99.97,
                'last_latency_ms' => 26,
                'current_status' => 'operational',
                'display_order' => 5,
            ],

            // Group 3: Managed Cloud Services
            [
                'name' => 'service-db-postgres',
                'public_label' => 'Managed PostgreSQL 16 Engine Core',
                'group_name' => 'Managed Cloud Services',
                'type' => 'tcp',
                'target_host' => '127.0.0.1',
                'port' => 5432,
                'uptime_percentage' => 100.00,
                'last_latency_ms' => 2,
                'current_status' => 'operational',
                'display_order' => 6,
            ],
            [
                'name' => 'service-db-redis',
                'public_label' => 'Managed Redis 7.4 In-Memory Core',
                'group_name' => 'Managed Cloud Services',
                'type' => 'tcp',
                'target_host' => '127.0.0.1',
                'port' => 6379,
                'uptime_percentage' => 100.00,
                'last_latency_ms' => 1,
                'current_status' => 'operational',
                'display_order' => 7,
            ],

            // Group 4: Customer Control Plane
            [
                'name' => 'portal-core-api',
                'public_label' => 'STX Customer Portal & Billing API',
                'group_name' => 'Customer Control Plane',
                'type' => 'http',
                'target_host' => 'http://localhost:8000',
                'port' => 8000,
                'uptime_percentage' => 99.99,
                'last_latency_ms' => 16,
                'current_status' => 'operational',
                'display_order' => 8,
            ],
        ];

        foreach ($defaultMonitors as $item) {
            Monitor::updateOrCreate(
                ['name' => $item['name']],
                array_merge($item, [
                    'uuid' => (string) Str::uuid(),
                    'last_checked_at' => now(),
                ])
            );
        }

        // Default Recent Resolved Maintenance Incident
        SystemIncident::firstOrCreate(
            ['title' => 'Scheduled Upgrades & Linux Kernel Hardening'],
            [
                'uuid' => (string) Str::uuid(),
                'status' => 'resolved',
                'severity' => 'operational',
                'message' => 'Completed scheduled zero-downtime microcode updates and netfilter rule hardening across all European and Asian edge nodes. All systems performing at nominal latency.',
                'resolved_at' => now()->subDays(2),
                'created_at' => now()->subDays(2)->subHours(3),
            ]
        );
    }
}
