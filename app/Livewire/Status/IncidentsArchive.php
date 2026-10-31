<?php

namespace App\Livewire\Status;

use App\Models\Commerce\SystemIncident;
use Livewire\Component;

class IncidentsArchive extends Component
{
    public string $activeTab = 'all'; // all, active, maintenance, resolved

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['all', 'active', 'maintenance', 'resolved']) ? $tab : 'all';
    }

    public function render()
    {
        $query = SystemIncident::with('updates')
            ->orderBy('created_at', 'desc');

        if ($this->activeTab === 'active') {
            $query->where('status', '!=', 'resolved')
                  ->where('incident_type', 'incident');
        } elseif ($this->activeTab === 'maintenance') {
            $query->where('incident_type', 'maintenance');
        } elseif ($this->activeTab === 'resolved') {
            $query->where('status', 'resolved');
        }

        $incidents = $query->get();

        return view('livewire.status.incidents-archive', [
            'incidents' => $incidents,
        ])->layout('layouts.status', [
            'title' => 'Incident History & Scheduled Maintenance Timeline — STX Status',
        ]);
    }
}

