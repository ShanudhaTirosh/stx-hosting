<?php

namespace App\Livewire\Status;

use App\Models\Commerce\Monitor;
use App\Models\Commerce\SystemIncident;
use Livewire\Component;

class PublicStatus extends Component
{
    public string $historyWindow = '60m'; // 60m, 24h, 30d, 90d
    public string $searchQuery = '';

    public function setHistoryWindow(string $window): void
    {
        $this->historyWindow = in_array($window, ['60m', '24h', '30d', '90d']) ? $window : '60m';
    }

    public function render()
    {
        $query = Monitor::withCount('checks')
            ->where('is_public', true)
            ->where('is_active', true)
            ->orderBy('display_order');

        if (!empty($this->searchQuery)) {
            $term = '%' . strtolower(trim($this->searchQuery)) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(COALESCE(target_host, \'\')) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(COALESCE(target_url, \'\')) LIKE ?', [$term]);
            });
        }

        $monitors = $query->get();

        $activeIncidents = SystemIncident::with('updates')
            ->where('status', '!=', 'resolved')
            ->latest('created_at')
            ->get();

        $hasOutage = $monitors->contains('current_status', 'down');
        $hasDegraded = $monitors->contains('current_status', 'degraded');
        $hasMaintenance = $activeIncidents->contains('incident_type', 'maintenance');

        $overallStatusText = 'All systems normal';
        $overallStatusColor = 'emerald';
        if ($hasOutage) {
            $overallStatusText = 'Major System Outage';
            $overallStatusColor = 'rose';
        } elseif ($hasDegraded) {
            $overallStatusText = 'Partial Degradation';
            $overallStatusColor = 'amber';
        } elseif ($hasMaintenance) {
            $overallStatusText = 'Scheduled Maintenance';
            $overallStatusColor = 'purple';
        }

        $windowLabel = match ($this->historyWindow) {
            '60m' => '60 MINUTES AGO',
            '24h' => '24 HOURS AGO',
            '30d' => '30 DAYS AGO',
            '90d' => '90 DAYS AGO',
            default => '60 MINUTES AGO',
        };

        return view('livewire.status.public-status', [
            'monitors' => $monitors,
            'activeIncidents' => $activeIncidents,
            'overallStatusText' => $overallStatusText,
            'overallStatusColor' => $overallStatusColor,
            'windowLabel' => $windowLabel,
        ])->layout('layouts.storefront', [
            'title' => 'STX Cloud & Infrastructure Health — Telemetry & Uptime',
        ]);
    }
}
