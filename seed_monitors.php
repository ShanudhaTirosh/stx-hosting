
use App\Models\Commerce\Monitor;
use App\Models\Commerce\MonitorCheck;
use Illuminate\Support\Str;

\DB::table('monitor_checks')->delete();
\DB::table('monitors')->delete();

\ = [
    [
        'name' => 'STX Info & Docs Portal',
        'type' => 'https',
        'target_url' => 'https://info.shanutechx.com',
        'target_host' => 'info.shanutechx.com',
        'port' => 443,
        'group_name' => 'Web Services',
        'status' => 'operational',
        'current_status' => 'operational',
        'last_latency_ms' => 554,
        'uptime_percentage' => 100.0,
        'expected_status_code' => 200,
        'display_order' => 1,
        'is_public' => true,
        'is_active' => true,
        'last_checked_at' => now()->subMinutes(1),
    ],
    [
        'name' => 'STX Compute VPS Node 01',
        'type' => 'tcp',
        'target_url' => null,
        'target_host' => 'vps01.shanutechx.com',
        'port' => 22,
        'group_name' => 'Compute Infrastructure',
        'status' => 'operational',
        'current_status' => 'operational',
        'last_latency_ms' => 1,
        'uptime_percentage' => 100.0,
        'expected_status_code' => null,
        'display_order' => 2,
        'is_public' => true,
        'is_active' => true,
        'last_checked_at' => now()->subMinutes(1),
    ],
    [
        'name' => 'STX Anycast Cloud Gateway',
        'type' => 'https',
        'target_url' => 'https://one.one.one.one',
        'target_host' => 'one.one.one.one',
        'port' => 443,
        'group_name' => 'Edge & Network',
        'status' => 'operational',
        'current_status' => 'operational',
        'last_latency_ms' => 335,
        'uptime_percentage' => 100.0,
        'expected_status_code' => 200,
        'display_order' => 3,
        'is_public' => true,
        'is_active' => true,
        'last_checked_at' => now()->subMinutes(1),
    ],
    [
        'name' => 'STX Global OAuth Identity Gateway',
        'type' => 'https',
        'target_url' => 'https://accounts.google.com',
        'target_host' => 'accounts.google.com',
        'port' => 443,
        'group_name' => 'Identity & Auth',
        'status' => 'operational',
        'current_status' => 'operational',
        'last_latency_ms' => 679,
        'uptime_percentage' => 100.0,
        'expected_status_code' => 200,
        'display_order' => 4,
        'is_public' => true,
        'is_active' => true,
        'last_checked_at' => now()->subMinutes(2),
    ],
    [
        'name' => 'STX Git Deployments Engine',
        'type' => 'https',
        'target_url' => 'https://github.com',
        'target_host' => 'github.com',
        'port' => 443,
        'group_name' => 'Build Fabric',
        'status' => 'operational',
        'current_status' => 'operational',
        'last_latency_ms' => 554,
        'uptime_percentage' => 100.0,
        'expected_status_code' => 200,
        'display_order' => 5,
        'is_public' => true,
        'is_active' => true,
        'last_checked_at' => now()->subMinutes(2),
    ],
    [
        'name' => 'STX Edge DNS Resolution',
        'type' => 'https',
        'target_url' => 'https://dns.google/resolve?name=info.shanutechx.com',
        'target_host' => 'dns.google',
        'port' => 443,
        'group_name' => 'DNS & Routing',
        'status' => 'operational',
        'current_status' => 'operational',
        'last_latency_ms' => 246,
        'uptime_percentage' => 100.0,
        'expected_status_code' => 200,
        'display_order' => 6,
        'is_public' => true,
        'is_active' => true,
        'last_checked_at' => now()->subMinutes(2),
    ],
];

foreach (\ as \) {
    \ = Monitor::create(\);
    
    // Seed some recent check probes
    for (\ = 10; \ >= 0; \--) {
        MonitorCheck::create([
            'monitor_id' => \->id,
            'status' => 'operational',
            'status_code' => 200,
            'response_time_ms' => \->last_latency_ms,
            'checked_at' => now()->subMinutes(\ * 5),
        ]);
    }
}

echo 'SEEDED ' . count(\) . ' MONITORS';
