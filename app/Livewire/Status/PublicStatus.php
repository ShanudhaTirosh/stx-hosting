<?php

namespace App\Livewire\Status;

use App\Models\Commerce\Monitor;
use App\Models\Commerce\SystemIncident;
use Illuminate\Support\Facades\Cache;
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

        $activeIncidents = SystemIncident::with('updates')
            ->where('status', '!=', 'resolved')
            ->latest('created_at')
            ->get();

        $resolvedIncidents = SystemIncident::with('updates')
            ->where('status', 'resolved')
            ->latest('created_at')
            ->take(5)
            ->get();

        $hasOutage = $monitors->contains('current_status', 'down');
        $hasDegraded = $monitors->contains('current_status', 'degraded');
        $hasMaintenance = $activeIncidents->contains('incident_type', 'maintenance');

        $overallStatus = 'operational';
        if ($hasOutage) {
            $overallStatus = 'major_outage';
        } elseif ($hasDegraded) {
            $overallStatus = 'degraded_performance';
        } elseif ($hasMaintenance) {
            $overallStatus = 'maintenance';
        }

        $averageUptime = round($monitors->avg('uptime_percentage') ?: 99.99, 2);

        return view('livewire.status.public-status', [
            'groupedMonitors' => $groupedMonitors,
            'activeIncidents' => $activeIncidents,
            'resolvedIncidents' => $resolvedIncidents,
            'overallStatus' => $overallStatus,
            'totalMonitors' => $monitors->count(),
            'averageUptime' => $averageUptime,
        ])->layout('layouts.simple', ['title' => 'System Status & Infrastructure Telemetry — STX Enterprises']);
    }
}
