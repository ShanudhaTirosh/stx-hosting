<?php

namespace App\Notifications\Commerce;

use App\Models\Commerce\Invoice;
use App\Models\Commerce\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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

    public function toMail(object $notifiable): MailMessage
    {
        $amountFormatted = ($this->invoice->currency === 'LKR')
            ? 'LKR ' . number_format($this->invoice->total_cents / 100, 2)
            : '$' . number_format($this->invoice->total_cents / 100, 2);

        return (new MailMessage)
            ->subject("Order Confirmation & Invoice {$this->invoice->invoice_number} - STX Enterprises")
            ->view('emails.commerce.order-placed', [
                'order' => $this->order,
                'invoice' => $this->invoice,
                'amountFormatted' => $amountFormatted,
                'notifiable' => $notifiable,
            ]);
    }
}

