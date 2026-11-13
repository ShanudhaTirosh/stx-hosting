<?php

namespace App\Notifications\Status;

use App\Models\Commerce\StatusSubscription;
use App\Models\Commerce\SystemIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StatusIncidentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public SystemIncident $incident,
        public string $updateType = 'new', // new, update, resolved
        public ?string $updateMessage = null
    ) {
        $this->onQueue('high');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusUrl = route('status.public');
        $unsubscribeUrl = ($notifiable instanceof StatusSubscription)
            ? route('status.unsubscribe', ['token' => $notifiable->unsubscribe_token])
            : $statusUrl;

        $subject = match ($this->updateType) {
            'resolved' => "[Resolved] STX Incident: {$this->incident->title}",
            'update' => "[Update] STX Incident: {$this->incident->title}",
            default => "[Incident] STX Incident: {$this->incident->title}",
        };

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('STX Status Alert')
            ->line("An incident alert has been published for STX Platform infrastructure.")
            ->line("**Incident:** {$this->incident->title}")
            ->line("**Severity:** " . ucfirst($this->incident->severity ?? 'operational'))
            ->line("**Current Status:** " . ucfirst($this->incident->status));

        if ($this->updateMessage) {
            $mail->line("**Latest Update:**\n> " . $this->updateMessage);
        } else {
            $mail->line("**Details:**\n> " . $this->incident->message);
        }

        return $mail
            ->action('View Live Status Page', $statusUrl)
            ->line('---')
            ->line("You received this email because you subscribed to status alerts. To unsubscribe at any time, click here: [Unsubscribe]({$unsubscribeUrl})");
    }
}

