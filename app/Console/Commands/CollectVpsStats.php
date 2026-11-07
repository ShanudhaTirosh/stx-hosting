<?php

namespace App\Console\Commands;

use App\Jobs\CollectVpsStatsJob;
use App\Models\Commerce\ServiceInstance;
use App\Models\Commerce\VpsStatSample;
use Illuminate\Console\Command;

class CollectVpsStats extends Command
{
    protected $signature = 'vps:collect-stats';

    protected $description = 'Queue a usage sample for every active VPS the panel can reach, and prune old samples';

    public function handle(): int
    {
        $count = 0;

        ServiceInstance::where('service_type', 'vps')
            ->where('status', 'active')
            ->whereNotNull('panel_private_key_id')
            ->each(function (ServiceInstance $service) use (&$count) {
                CollectVpsStatsJob::dispatch($service);
                $count++;
            });

        VpsStatSample::where('collected_at', '<', now()->subDays(35))->delete();

        $this->info("Queued {$count} VPS sample(s).");

        return self::SUCCESS;
    }
}
