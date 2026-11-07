<?php

namespace App\Jobs;

use App\Models\Commerce\ServiceInstance;
use App\Services\Vps\VpsConnectionFactory;
use App\Services\Vps\VpsStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CollectVpsStatsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 40;

    public int $uniqueFor = 55;

    public function __construct(public ServiceInstance $service) {}

    public function uniqueId(): string
    {
        return 'vps-stats-'.$this->service->id;
    }

    public function handle(VpsConnectionFactory $connections): void
    {
        try {
            $shell = $connections->connect($this->service);
        } catch (\Throwable $e) {
            return; // unreachable servers simply have no new sample
        }

        try {
            VpsStats::collect($this->service, $shell);
        } finally {
            $shell->disconnect();
        }
    }
}
