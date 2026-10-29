<?php

namespace App\Livewire\Portal;

use App\Models\Commerce\ServiceInstance;
use Livewire\Component;

class ServicesList extends Component
{
    public function render()
    {
        $team = currentTeam();
        $services = collect();

        if ($team) {
            $services = ServiceInstance::with(['productVariant.product', 'credentials'])
                ->where('team_id', $team->id)
                ->latest()
                ->get();
        }

        return view('livewire.portal.services-list', [
            'services' => $services,
        ]);
    }
}
