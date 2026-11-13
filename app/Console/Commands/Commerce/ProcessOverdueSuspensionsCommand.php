<?php

namespace App\Console\Commands\Commerce;

use App\Jobs\Commerce\ProcessOverdueSuspensionsJob;
use Illuminate\Console\Command;

class ProcessOverdueSuspensionsCommand extends Command
{
    protected $signature = 'commerce:process-suspensions';

    protected $description = 'Scan overdue hosting entitlements exceeding grace period and suspend workloads';

    public function handle(): int
    {
        $this->info('Starting overdue suspension processing...');
        app(ProcessOverdueSuspensionsJob::class)->handle();
        $this->info('Overdue suspension processing completed.');

        return Command::SUCCESS;
    }
}

