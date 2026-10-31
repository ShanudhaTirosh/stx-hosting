<?php

use App\Models\Commerce\Monitor;
use App\Models\Commerce\MonitorCheck;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $specMonitors = [
            [
                'name' => 'STX Info & Docs Portal',
                'type' => 'https',
                'target_url' => 'https://info.shanutechx.com',
                'target_host' => 'info.shanutechx.com',
                'port' => 443,
                'group_name' => 'Web Services',
                'current_status' => 'operational',
                'last_latency_ms' => 554,
                'uptime_percentage' => 100.0,
                'display_order' => 1,
            ],
            [
                'name' => 'STX Compute VPS Node 01',
                'type' => 'tcp',
                'target_url' => null,
                'target_host' => 'vps01.shanutechx.com',
                'port' => 22,
                'group_name' => 'Compute Infrastructure',
                'current_status' => 'operational',
                'last_latency_ms' => 1,
                'uptime_percentage' => 100.0,
                'display_order' => 2,
            ],
            [
                'name' => 'STX Anycast Cloud Gateway',
                'type' => 'https',
                'target_url' => 'https://one.one.one.one',
                'target_host' => 'one.one.one.one',
                'port' => 443,
                'group_name' => 'Edge & Network',
                'current_status' => 'operational',
                'last_latency_ms' => 335,
                'uptime_percentage' => 100.0,
                'display_order' => 3,
            ],
            [
                'name' => 'STX Global OAuth Identity Gateway',
                'type' => 'https',
                'target_url' => 'https://accounts.google.com',
                'target_host' => 'accounts.google.com',
                'port' => 443,
                'group_name' => 'Identity & Auth',
                'current_status' => 'operational',
                'last_latency_ms' => 6791,
                'uptime_percentage' => 100.0,
                'display_order' => 4,
            ],
            [
                'name' => 'STX Git Deployments Engine',
                'type' => 'https',
                'target_url' => 'https://github.com',
                'target_host' => 'github.com',
                'port' => 443,
                'group_name' => 'Build Fabric',
                'current_status' => 'operational',
                'last_latency_ms' => 5543,
                'uptime_percentage' => 100.0,
                'display_order' => 5,
            ],
            [
                'name' => 'STX Edge DNS Resolution',
                'type' => 'https',
                'target_url' => 'https://dns.google/resolve?name=info.shanutechx.com',
                'target_host' => 'dns.google',
                'port' => 443,
                'group_name' => 'DNS & Routing',
                'current_status' => 'operational',
                'last_latency_ms' => 2467,
                'uptime_percentage' => 100.0,
                'display_order' => 6,
            ],
        ];

        MonitorCheck::truncate();
        Monitor::truncate();

        foreach ($specMonitors as $spec) {
            $m = Monitor::create(array_merge($spec, [
                'public_label' => $spec['name'],
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'is_public' => true,
                'is_active' => true,
                'last_checked_at' => now()->subMinutes(1),
            ]));

            // Seed checks
            for ($i = 2; $i >= 1; $i--) {
                MonitorCheck::create([
                    'monitor_id' => $m->id,
                    'status' => 'up',
                    'response_time_ms' => $m->last_latency_ms,
                    'checked_at' => now()->subMinutes($i * 5),
                ]);
            }
        }
    }

    public function down(): void
    {
    }
};

