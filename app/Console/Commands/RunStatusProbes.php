<?php

namespace App\Console\Commands;

use App\Jobs\Commerce\ExecuteStatusProbesJob;
use Illuminate\Console\Command;

class RunStatusProbes extends Command
{
    protected $signature = 'status:probe';
    protected $description = 'Execute real-time socket and TCP health checks for all infrastructure monitors';

    public function handle(): int
    {
        $this->info('Starting STX Enterprises status telemetry probe execution...');
        (new ExecuteStatusProbesJob())->handle();
        $this->info('Completed status probe execution successfully.');
        return 0;
    }
}
