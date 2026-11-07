<?php

namespace App\Notifications\Commerce;

use App\Services\Notifications\EmailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sends one email template with sample values to the owner, to check how it looks. */
class EmailTemplatePreviewNotification extends Notification
{
    use Queueable;

    public function __construct(public string $key) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = EmailTemplates::message($this->key, EmailTemplates::sampleVars($this->key));

        return $mail->subject('[Test] '.($mail->subject ?? ''));
    }
}
