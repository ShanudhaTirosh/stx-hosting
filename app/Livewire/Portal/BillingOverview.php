<?php

namespace App\Livewire\Portal;

use App\Models\Commerce\Invoice;
use Livewire\Component;

class BillingOverview extends Component
{
    public function render()
    {
        $team = currentTeam();
        $invoices = collect();

        if ($team) {
            $invoices = Invoice::with(['items', 'proofs'])
                ->where('team_id', $team->id)
                ->latest()
                ->get();
        }

        return view('livewire.portal.billing-overview', [
            'invoices' => $invoices,
        ]);
    }
}
