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
        $data = Cache::remember('public_status_page_data', 30, function () {
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

            return [
                'groupedMonitors' => $groupedMonitors,
                'overallStatus' => $overallStatus,
                'incidents' => $incidents,
                'totalMonitors' => $monitors->count(),
                'averageUptime' => round($monitors->avg('uptime_percentage') ?: 99.98, 2),
            ];
        });

        return view('livewire.status.public-status', $data)
            ->layout('layouts.base', ['title' => 'System Status & Infrastructure Health — STX Enterprises']);
    }
}
