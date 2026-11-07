<?php

namespace App\Notifications\Commerce;

use App\Models\ChatMessage;
use App\Services\Notifications\EmailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Email fallback: staff replied while the customer was not looking at the conversation. */
class ChatReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public ChatMessage $message)
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
        return EmailTemplates::message('ticket_reply', [
            'name' => $notifiable->name ?? 'there',
            'subject' => $this->message->thread->subject,
            'message_excerpt' => Str::limit(str_replace(["\r", "\n"], ' ', $this->message->body), 400),
            'thread_uuid' => $this->message->thread->uuid,
        ]);
    }
}
