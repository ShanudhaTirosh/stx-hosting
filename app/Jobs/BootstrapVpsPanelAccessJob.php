<?php

namespace App\Jobs;

use App\Models\Commerce\ServiceInstance;
use App\Services\Vps\VpsConnectionFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** After a VPS is delivered, install the panel key so stats, files and the terminal work from day one. */
class BootstrapVpsPanelAccessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public ServiceInstance $service) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(VpsConnectionFactory $connections): void
    {
        $connections->bootstrap($this->service);
    }
}
