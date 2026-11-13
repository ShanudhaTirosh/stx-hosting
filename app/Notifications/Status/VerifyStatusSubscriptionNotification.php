<?php

namespace App\Notifications\Status;

use App\Models\Commerce\StatusSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyStatusSubscriptionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public StatusSubscription $subscription)
    {
        $this->onQueue('high');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = route('status.verify', ['token' => $this->subscription->verification_token]);

        return (new MailMessage)
            ->subject('Confirm your STX Status subscription')
            ->greeting('Hello!')
            ->line('You requested to receive email alerts when incidents or maintenance events affect STX platform services.')
            ->line('Please confirm your subscription by clicking the button below:')
            ->action('Confirm Status Subscription', $verificationUrl)
            ->line('If you did not request this subscription, no further action is required.');
    }
}

