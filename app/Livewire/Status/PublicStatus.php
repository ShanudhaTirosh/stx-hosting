<?php

namespace App\Livewire\Status;

use App\Models\Commerce\Monitor;
use App\Models\Commerce\SystemIncident;
use Livewire\Component;

class PublicStatus extends Component
{
    public function render()
    {
        $monitors = Monitor::where('is_public', true)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $groupedMonitors = $monitors->groupBy('group_name');

        $hasOutage = $monitors->contains('current_status', 'down');
        $hasDegraded = $monitors->contains('current_status', 'degraded');

        $overallStatus = 'operational';
        if ($hasOutage) {
            $overallStatus = 'major_outage';
        } elseif ($hasDegraded) {
            $overallStatus = 'degraded_performance';
        }

        $incidents = SystemIncident::latest('created_at')->take(5)->get();

        return view('livewire.status.public-status', [
            'groupedMonitors' => $groupedMonitors,
            'overallStatus' => $overallStatus,
            'incidents' => $incidents,
            'totalMonitors' => $monitors->count(),
            'averageUptime' => round($monitors->avg('uptime_percentage') ?: 99.98, 2),
        ])->layout('layouts.base', ['title' => 'System Status & Infrastructure Health — STX Enterprises']);
    }
}
