<?php

namespace App\Notifications\Commerce;

use App\Models\Commerce\ServiceInstance;
use App\Services\Notifications\EmailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceDeliveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public ServiceInstance $service
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
        $port = (int) ($this->service->network_config['ssh_port'] ?? 22);

        return EmailTemplates::message('service_delivered', [
            'name' => $notifiable->name ?? 'there',
            'hostname' => (string) $this->service->hostname,
            'ip_address' => (string) $this->service->ip_address,
            'ssh_port' => (string) $port,
            'os_image' => (string) $this->service->os_image,
            // Shown in a code span, where backslash escapes would print literally: our own text, so passed through as is.
            'ssh_command' => EmailTemplates::raw('ssh root@'.$this->service->ip_address.($port !== 22 ? " -p {$port}" : '')),
            'service_uuid' => (string) $this->service->uuid,
        ]);
    }
}
