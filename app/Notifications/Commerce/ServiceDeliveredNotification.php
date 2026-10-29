<?php

namespace App\Notifications\Commerce;

use App\Models\Commerce\ServiceInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceDeliveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ServiceInstance $service
    ) {
        $this->onQueue('high');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $port = $this->service->network_config['ssh_port'] ?? 22;
        $portFlag = ($port != 22) ? " -p {$port}" : "";

        return (new MailMessage)
            ->subject("Your VPS is Live & Ready! - STX Enterprises")
            ->view('emails.commerce.service-delivered', [
                'service' => $this->service,
                'sshPort' => $port,
                'notifiable' => $notifiable,
            ]);
    }
}

