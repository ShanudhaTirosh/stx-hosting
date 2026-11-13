<?php

namespace App\Notifications\Commerce;

use App\Models\Commerce\Invoice;
use App\Models\Commerce\Order;
use App\Services\Commerce\InvoicePdfService;
use App\Services\Notifications\EmailTemplates;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpcomingRenewalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public Order $order,
        public Invoice $invoice,
        public string $productName = 'Hosting Plan'
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
        $mail = EmailTemplates::message('upcoming_renewal', [
            'name' => $notifiable->name ?? 'there',
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => Money::format((int) $this->invoice->total_cents, $this->invoice->currency),
            'due_date' => $this->invoice->due_at?->format('d M Y') ?? '',
            'product_name' => $this->productName,
        ]);

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

