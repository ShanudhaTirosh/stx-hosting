<?php

namespace App\Notifications\Commerce;

use App\Models\Commerce\Invoice;
use App\Services\Notifications\EmailTemplates;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the client when their payment proof has been received. */
class PaymentProofReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public Invoice $invoice)
    {
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
        return EmailTemplates::message('proof_received', [
            'name' => $notifiable->name ?? 'there',
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => Money::format((int) $this->invoice->total_cents, $this->invoice->currency),
        ]);
    }
}
