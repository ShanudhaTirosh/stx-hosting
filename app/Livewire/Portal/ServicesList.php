<?php

namespace App\Livewire\Portal;

use App\Models\Commerce\ServiceInstance;
use App\Services\Commerce\VpsFulfillmentService;
use Livewire\Component;

class ServicesList extends Component
{
    public function render()
    {
        $team = currentTeam();
        $services = collect();
        $pendingVpsOrders = collect();

        if ($team) {
            $services = ServiceInstance::with(['productVariant.product', 'credentials'])
                ->where('team_id', $team->id)
                ->latest()
                ->get();

            $pendingVpsOrders = VpsFulfillmentService::pendingQuery()
                ->where('team_id', $team->id)
                ->with(['items.productVariant.product', 'invoice'])
                ->latest('updated_at')
                ->get();
        }

        return view('livewire.portal.services-list', [
            'services' => $services,
            'pendingVpsOrders' => $pendingVpsOrders,
        ]);
    }
}

