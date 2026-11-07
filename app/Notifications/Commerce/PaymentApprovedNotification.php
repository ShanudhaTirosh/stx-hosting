<?php

namespace App\Notifications\Commerce;

use App\Models\Commerce\Invoice;
use App\Models\Commerce\Order;
use App\Services\Commerce\InvoicePdfService;
use App\Services\Commerce\VpsFulfillmentService;
use App\Services\Notifications\EmailTemplates;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public Order $order,
        public Invoice $invoice
    ) {
        $this->onQueue('high');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Tell the customer what actually happens next for what they bought.
        $note = VpsFulfillmentService::unitsOrdered($this->order) > 0
            ? 'Your server is set up by hand. You will get another email with its address and login details, usually within 24 to 72 hours.'
            : 'Your plan is active. You can deploy from your client panel now.';

        $mail = EmailTemplates::message('payment_approved', [
            'name' => $notifiable->name ?? 'there',
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => Money::format((int) $this->invoice->total_cents, $this->invoice->currency),
            'fulfillment_note' => $note,
        ]);

        // Attach the PDF invoice when dompdf is installed; the email still goes out without it.
        try {
            $pdf = app(InvoicePdfService::class)->render($this->invoice);
            if ($pdf !== null) {
                $mail->attachData($pdf, $this->invoice->invoice_number.'.pdf', ['mime' => 'application/pdf']);
            }
        } catch (\Throwable $e) {
            logger()->warning("Could not attach invoice PDF: {$e->getMessage()}");
        }

        return $mail;
    }
}
