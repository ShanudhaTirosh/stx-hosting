<?php

namespace App\Services\Commerce;

use App\Models\Commerce\Invoice;

/**
 * Renders an invoice to PDF with dompdf (composer require dompdf/dompdf).
 * Returns null when the library is not installed, so emails still go out
 * without an attachment instead of failing.
 */
class InvoicePdfService
{
    public function available(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    public function render(Invoice $invoice): ?string
    {
        if (! $this->available()) {
            return null;
        }

        $invoice->loadMissing(['items', 'order', 'user', 'team']);

        $html = view('invoices.pdf', [
            'invoice' => $invoice,
            'business' => config('commerce.business'),
        ])->render();

        $options = new \Dompdf\Options;
        $options->set('isRemoteEnabled', false); // never fetch remote resources while rendering
        $options->set('isPhpEnabled', false);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
