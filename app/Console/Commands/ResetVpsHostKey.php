<?php

namespace App\Console\Commands;

use App\Models\Commerce\ServiceInstance;
use Illuminate\Console\Command;

class ResetVpsHostKey extends Command
{
    protected $signature = 'vps:reset-host-key {uuid : Service instance uuid}';

    protected $description = 'Forget the pinned SSH host key of a VPS (use after a legitimate reinstall)';

    public function handle(): int
    {
        $service = ServiceInstance::where('uuid', $this->argument('uuid'))->first();

        if (! $service) {
            $this->error('No such service.');

            return self::FAILURE;
        }

        $service->forceFill(['host_key_sha256' => null, 'panel_private_key_id' => null])->save();
        $this->info('Host key forgotten. The panel key will be installed again on the next sign-in.');

        return self::SUCCESS;
    }
}
