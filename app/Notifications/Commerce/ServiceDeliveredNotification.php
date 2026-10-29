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
            ->subject("Your VPS is Live & Ready! - STX Hosting")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your VPS instance ({$this->service->hostname}) has been provisioned and is active!")
            ->line("Server Details:")
            ->line("• IPv4 Address: {$this->service->ip_address}")
            ->line("• SSH Port: {$port}")
            ->line("• Operating System: {$this->service->os_image}")
            ->line("• Connection Command: ssh root@{$this->service->ip_address}{$portFlag}")
            ->action('Access Portal & View Root Password', url("/portal/services/{$this->service->uuid}"))
            ->line('For security, your temporary root password is encrypted. You can securely reveal and copy it in your portal after confirming your account password.')
            ->line('Enjoy your new high-performance cloud server!');
    }
}
