<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\Invoice;
use Illuminate\Http\Request;

class InvoiceViewController extends Controller
{
    public function show(string $uuid)
    {
        $invoice = Invoice::with(['order.items', 'items', 'user', 'team'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        // Multi-tenant check
        $user = auth()->user();
        if ($user && ! $user->isAdmin()) {
            if (! $user->teams->contains('id', $invoice->team_id)) {
                abort(403, 'Unauthorized access to this invoice.');
            }
        }

        return view('invoices.show', [
            'invoice' => $invoice,
        ]);
    }
}
