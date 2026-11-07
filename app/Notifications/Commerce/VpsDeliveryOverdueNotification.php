<?php

namespace App\Notifications\Commerce;

use App\Models\Commerce\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Staff reminder: a paid VPS has been waiting for delivery for 24, 48 or 72 hours. */
class VpsDeliveryOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public int $hours)
    {
        $this->onQueue('high');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $late = $this->hours >= 72;

        return (new MailMessage)
            ->subject(($late ? 'OVERDUE: ' : 'Reminder: ').'a paid VPS has waited '.$this->hours.' hours')
            ->line(($late ? 'This order is past the 72 hour delivery promise.' : "This order has been waiting {$this->hours} hours."))
            ->line('Customer: '.($this->order->user?->email ?? 'unknown'))
            ->line('Order: '.$this->order->uuid)
            ->action('Open the provisioning queue', url('/admin/operations/provisioning'));
    }
}
