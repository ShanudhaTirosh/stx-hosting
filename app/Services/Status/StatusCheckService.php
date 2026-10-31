<?php

namespace App\Services\Status;

use App\Models\Commerce\Monitor;
use App\Models\Commerce\MonitorCheck;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StatusCheckService
{
    /**
     * Run check for all active monitors.
     */
    public function checkAll(): array
    {
        $monitors = Monitor::where('is_active', true)->get();
        $results = [];

        foreach ($monitors as $monitor) {
            $results[$monitor->id] = $this->check($monitor);
        }

        return $results;
    }

    /**
     * Run check for a single monitor.
     */
    public function check(Monitor $monitor): array
    {
        $start = microtime(true);
        $status = 'down';
        $errorMessage = null;
        $responseTimeMs = 0;

        try {
            switch (strtolower($monitor->type)) {
                case 'http':
                case 'https':
                    [$status, $responseTimeMs, $errorMessage] = $this->checkHttp($monitor);
                    break;
                case 'tcp':
                    [$status, $responseTimeMs, $errorMessage] = $this->checkTcp($monitor);
                    break;
                case 'ping':
                    [$status, $responseTimeMs, $errorMessage] = $this->checkPing($monitor);
                    break;
                default:
                    [$status, $responseTimeMs, $errorMessage] = $this->checkTcp($monitor);
            }
        } catch (\Throwable $e) {
            $status = 'down';
            $errorMessage = $e->getMessage();
            $responseTimeMs = (int) round((microtime(true) - $start) * 1000);
        }

        // Save check history
        MonitorCheck::create([
            'monitor_id' => $monitor->id,
            'status' => $status,
            'response_time_ms' => $responseTimeMs,
            'error_message' => $errorMessage,
            'checked_at' => now(),
        ]);

        // Keep rolling checks pruned to last 300 checks per monitor
        if (mt_rand(1, 20) === 1) {
            $oldIds = MonitorCheck::where('monitor_id', $monitor->id)
                ->orderBy('checked_at', 'desc')
                ->skip(300)
                ->take(100)
                ->pluck('id');
            if ($oldIds->isNotEmpty()) {
                MonitorCheck::whereIn('id', $oldIds)->delete();
            }
        }

        // Calculate rolling uptime percentage over last 100 checks
        $recentChecks = MonitorCheck::where('monitor_id', $monitor->id)
            ->latest('checked_at')
            ->take(100)
            ->get();

        $upCount = $recentChecks->filter(fn ($c) => $c->status === 'operational' || $c->status === 'up')->count();
        $totalChecks = $recentChecks->count();
        $uptimePct = $totalChecks > 0 ? round(($upCount / $totalChecks) * 100, 2) : 100.00;

        $previousStatus = $monitor->current_status;

        $monitor->update([
            'current_status' => $status,
            'last_latency_ms' => $responseTimeMs,
            'uptime_percentage' => $uptimePct,
            'last_checked_at' => now(),
        ]);

        if ($previousStatus !== $status) {
            $this->dispatchStatusChangeAlert($monitor, $previousStatus, $status, $errorMessage);
        }

        return [
            'monitor' => $monitor->name,
            'status' => $status,
            'latency_ms' => $responseTimeMs,
            'error' => $errorMessage,
        ];
    }

    /**
     * Perform HTTP/HTTPS probe.
     */
    protected function checkHttp(Monitor $monitor): array
    {
        $url = $monitor->target_url ?: ($monitor->target_host ? "https://{$monitor->target_host}" : null);
        if (!$url) {
            return ['down', 0, 'No target URL configured'];
        }

        $timeoutSeconds = max(1, ($monitor->timeout_ms ?: 5000) / 1000);
        $expectedStatus = $monitor->expected_status_code ?: 200;

        $start = microtime(true);
        $request = Http::timeout($timeoutSeconds)->withoutVerifying();

        if (!empty($monitor->custom_headers)) {
            $request = $request->withHeaders($monitor->custom_headers);
        }

        $response = $request->get($url);
        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        if ($response->status() !== $expectedStatus && !($expectedStatus === 200 && $response->successful())) {
            return ['down', $latencyMs, "HTTP status {$response->status()} did not match expected {$expectedStatus}"];
        }

        if ($monitor->keyword_search && !str_contains($response->body(), $monitor->keyword_search)) {
            return ['degraded', $latencyMs, "Keyword '{$monitor->keyword_search}' not found in response body"];
        }

        $status = ($latencyMs > 3000) ? 'degraded' : 'operational';
        return [$status, $latencyMs, null];
    }

    /**
     * Perform TCP Socket Probe.
     */
    protected function checkTcp(Monitor $monitor): array
    {
        $host = $monitor->target_host;
        $port = $monitor->port ?: 80;
        $timeoutSeconds = max(1.0, ($monitor->timeout_ms ?: 3000) / 1000);

        if (!$host) {
            return ['down', 0, 'No target host specified'];
        }

        $start = microtime(true);
        $errno = 0;
        $errstr = '';

        $fp = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);
        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        if (!$fp) {
            return ['down', $latencyMs, $errstr ?: "TCP connection to {$host}:{$port} failed (code {$errno})"];
        }

        fclose($fp);
        $status = ($latencyMs > 2000) ? 'degraded' : 'operational';
        return [$status, $latencyMs, null];
    }

    /**
     * Perform ICMP Ping Probe.
     */
    protected function checkPing(Monitor $monitor): array
    {
        $host = escapeshellarg($monitor->target_host);
        if (!$monitor->target_host) {
            return ['down', 0, 'No target host specified'];
        }

        $start = microtime(true);
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $cmd = $isWindows ? "ping -n 1 -w 2000 {$host}" : "ping -c 1 -W 2 {$host}";

        exec($cmd, $output, $returnCode);
        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        if ($returnCode !== 0) {
            return ['down', $latencyMs, "Ping to {$monitor->target_host} timed out or failed"];
        }

        $status = ($latencyMs > 1500) ? 'degraded' : 'operational';
        return [$status, $latencyMs, null];
    }

    /**
     * Dispatch notification alert on state transitions.
     */
    protected function dispatchStatusChangeAlert(Monitor $monitor, string $oldStatus, string $newStatus, ?string $error = null): void
    {
        Log::info("Status monitor state transition: {$monitor->name} changed from {$oldStatus} to {$newStatus}. Details: {$error}");
        // Notifications can be delivered via Discord, Telegram or Email based on commercial settings
    }
}

