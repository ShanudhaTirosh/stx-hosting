<?php

namespace App\Livewire\Status;

use App\Models\Commerce\Monitor;
use Livewire\Component;

class MonitorDetail extends Component
{
    public string $uuid;
    public string $timeWindow = '24h'; // 60m, 24h, 7d, 90d

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function setTimeWindow(string $window): void
    {
        $this->timeWindow = in_array($window, ['60m', '24h', '7d', '90d']) ? $window : '24h';
    }

    public function render()
    {
        $monitor = Monitor::where('uuid', $this->uuid)
            ->where('is_public', true)
            ->firstOrFail();

        // Calculate query range
        $since = match ($this->timeWindow) {
            '60m' => now()->subMinutes(60),
            '24h' => now()->subHours(24),
            '7d'  => now()->subDays(7),
            '90d' => now()->subDays(90),
            default => now()->subHours(24),
        };

        $recentChecks = $monitor->checks()
            ->where('checked_at', '>=', $since)
            ->orderBy('checked_at', 'desc')
            ->limit(50)
            ->get();

        $avgLatency = $recentChecks->avg('response_time_ms') ?: $monitor->last_latency_ms;
        $maxLatency = $recentChecks->max('response_time_ms') ?: $monitor->last_latency_ms;
        $minLatency = $recentChecks->min('response_time_ms') ?: $monitor->last_latency_ms;

        return view('livewire.status.monitor-detail', [
            'monitor' => $monitor,
            'recentChecks' => $recentChecks,
            'avgLatency' => round($avgLatency, 1),
            'maxLatency' => round($maxLatency, 1),
            'minLatency' => round($minLatency, 1),
        ])->layout('layouts.status', [
            'title' => "{$monitor->name} Telemetry & Latency Metrics — STX Status",
        ]);
    }
}

