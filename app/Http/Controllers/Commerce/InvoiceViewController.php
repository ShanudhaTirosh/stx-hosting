<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\Invoice;

class InvoiceViewController extends Controller
{
    public function show(string $uuid)
    {
        $invoice = Invoice::with(['order.items', 'items', 'user', 'team'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        // Staff (root team) or a member of the invoice's own team. Never use
        // User::isAdmin() here: it is true for every client in their own team.
        $this->authorize('view', $invoice);

        return view('invoices.show', [
            'invoice' => $invoice,
        ]);
    }
}
