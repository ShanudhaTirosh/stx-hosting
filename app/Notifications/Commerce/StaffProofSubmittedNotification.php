<?php

namespace App\Notifications\Commerce;

use App\Models\Commerce\Invoice;
use App\Models\Commerce\PaymentProof;
use App\Services\Notifications\EmailTemplates;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to platform staff when a proof enters the verification queue. */
class StaffProofSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public Invoice $invoice, public PaymentProof $proof)
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
        return EmailTemplates::message('staff_proof_submitted', [
            'name' => $notifiable->name ?? 'team',
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => Money::format((int) $this->invoice->total_cents, $this->invoice->currency),
            'method' => $this->proof->payment_method,
            'attempt' => (string) $this->proof->attempt_no,
        ]);
    }
}
