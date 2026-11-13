<?php

namespace App\Notifications\Commerce;

use App\Services\Notifications\EmailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceReactivatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public string $serviceName
    ) {
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
        return EmailTemplates::message('service_reactivated', [
            'name' => $notifiable->name ?? 'there',
            'service_name' => $this->serviceName,
        ]);
    }
}

