<?php

namespace App\Jobs\Commerce;

use App\Models\Commerce\Monitor;
use App\Models\Commerce\MonitorCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExecuteStatusProbesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $monitors = Monitor::where('is_active', true)->get();

        foreach ($monitors as $monitor) {
            $this->probeMonitor($monitor);
        }
    }

    protected function probeMonitor(Monitor $monitor): void
    {
        $startTime = microtime(true);
        $status = 'down';
        $errorMessage = null;
        $latencyMs = 0;

        try {
            if ($monitor->type === 'http' || str_starts_with($monitor->target_host, 'http')) {
                $response = Http::timeout(3)->get($monitor->target_host);
                $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
                if ($response->successful()) {
                    $status = ($latencyMs > 800) ? 'degraded' : 'up';
                } else {
                    $status = 'down';
                    $errorMessage = "HTTP status {$response->status()}";
                }
            } else {
                // TCP socket probe
                $timeoutSec = ($monitor->timeout_ms ?: 3000) / 1000.0;
                $socket = @fsockopen($monitor->target_host, $monitor->port ?: 22, $errno, $errstr, $timeoutSec);
                $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

                if ($socket) {
                    fclose($socket);
                    $status = ($latencyMs > 400) ? 'degraded' : 'up';
                } else {
                    $status = 'down';
                    $errorMessage = $errstr ?: "Connection timed out ($errno)";
                }
            }
        } catch (\Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            $status = 'down';
            $errorMessage = $e->getMessage();
        }

        // Save check history
        MonitorCheck::create([
            'monitor_id' => $monitor->id,
            'status' => $status,
            'response_time_ms' => $latencyMs,
            'error_message' => $errorMessage,
            'checked_at' => now(),
        ]);

        // Update monitor live record
        $monitor->update([
            'current_status' => ($status === 'up') ? 'operational' : ($status === 'degraded' ? 'degraded' : 'down'),
            'last_latency_ms' => max(1, $latencyMs),
            'last_checked_at' => now(),
        ]);
    }
}

