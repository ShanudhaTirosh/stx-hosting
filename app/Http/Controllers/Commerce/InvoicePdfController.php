<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\Invoice;
use App\Services\Commerce\InvoicePdfService;

class InvoicePdfController extends Controller
{
    public function show(string $uuid, InvoicePdfService $pdf)
    {
        $invoice = Invoice::where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $invoice);

        $bytes = $pdf->render($invoice);

        if ($bytes === null) {
            abort(503, 'PDF generation is not installed on this server. Use the printable invoice page instead.');
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->invoice_number.'.pdf"',
        ]);
    }
}
