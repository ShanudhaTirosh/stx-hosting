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
            ->subject("Order Confirmation & Invoice {$this->invoice->invoice_number} - STX Hosting")
            ->greeting("Hello {$notifiable->name},")
            ->line("Thank you for your order with STX Hosting! Your order #{$this->order->id} has been registered.")
            ->line("Invoice Reference: {$this->invoice->invoice_number}")
            ->line("Total Due: {$amountFormatted}")
            ->line("To activate your service, please make your bank transfer / deposit and upload your receipt in your customer portal:")
            ->line("Bank: Commercial Bank / Sampath Bank | Account: STX HOSTING (PVT) LTD | Acc No: 8012 3456 7890 | Branch: Colombo")
            ->action('View Invoice & Upload Receipt', url('/portal/billing'))
            ->line('Our operations team verifies receipts and provisions nodes within our 24 to 72 hour SLA.');
    }
}
