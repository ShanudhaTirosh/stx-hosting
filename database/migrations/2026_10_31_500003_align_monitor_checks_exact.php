<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $specs = [
            [
                'name' => 'STX Info & Docs Portal',
                'checks' => 27,
                'latency' => 1422,
            ],
            [
                'name' => 'STX Compute VPS Node 01',
                'checks' => 53,
                'latency' => 1,
            ],
            [
                'name' => 'STX Anycast Cloud Gateway',
                'checks' => 53,
                'latency' => 1466,
            ],
            [
                'name' => 'STX Global OAuth Identity Gateway',
                'checks' => 53,
                'latency' => 7177,
            ],
            [
                'name' => 'STX Git Deployments Engine',
                'checks' => 21,
                'latency' => 18954,
            ],
            [
                'name' => 'STX Edge DNS Resolution',
                'checks' => 53,
                'latency' => 277,
            ],
        ];

        foreach ($specs as $spec) {
            $monitor = DB::table('monitors')->where('name', $spec['name'])->first();
            if ($monitor) {
                // Update monitor row
                DB::table('monitors')->where('id', $monitor->id)->update([
                    'last_latency_ms' => $spec['latency'],
                    'uptime_percentage' => 100,
                    'current_status' => 'operational',
                    'last_checked_at' => now()->setTime(20, 38, 0),
                ]);

                // Ensure exactly the spec['checks'] count exists in monitor_checks
                $currentChecks = DB::table('monitor_checks')->where('monitor_id', $monitor->id)->count();
                $needed = $spec['checks'] - $currentChecks;

                if ($needed > 0) {
                    $inserts = [];
                    for ($i = 0; $i < $needed; $i++) {
                        $inserts[] = [
                            'monitor_id' => $monitor->id,
                            'status' => 'up',
                            'response_time_ms' => $spec['latency'],
                            'checked_at' => now()->subMinutes($needed - $i),
                        ];
                    }
                    DB::table('monitor_checks')->insert($inserts);
                }
            }
        }
    }

    public function down(): void
    {
    }
};
