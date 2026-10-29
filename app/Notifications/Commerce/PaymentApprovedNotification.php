<?php

namespace App\Notifications\Commerce;

use App\Models\Commerce\Invoice;
use App\Models\Commerce\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentApprovedNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject("Payment Approved for Invoice {$this->invoice->invoice_number} - STX Hosting")
            ->greeting("Hello {$notifiable->name},")
            ->line("Great news! Your payment receipt for Invoice {$this->invoice->invoice_number} has been verified and approved by our billing team.")
            ->line("Your order has been prioritized in our provisioning queue.")
            ->line("Our systems engineers will order and configure your VPS instance. You will receive an activation email as soon as your server credentials are ready in your portal.")
            ->action('Track Order Status', url('/portal/services'))
            ->line('Thank you for choosing STX Hosting.');
    }
}
