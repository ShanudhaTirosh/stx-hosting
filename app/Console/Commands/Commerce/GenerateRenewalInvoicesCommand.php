<?php

namespace App\Console\Commands\Commerce;

use App\Jobs\Commerce\GenerateRenewalInvoicesJob;
use App\Services\Commerce\OrderService;
use Illuminate\Console\Command;

class GenerateRenewalInvoicesCommand extends Command
{
    protected $signature = 'commerce:generate-renewals';

    protected $description = 'Scan active hosting entitlements expiring within 7 days and generate renewal invoices';

    public function handle(OrderService $orderService): int
    {
        $this->info('Starting renewal invoice generation...');
        app(GenerateRenewalInvoicesJob::class)->handle($orderService);
        $this->info('Renewal invoice generation completed.');

        return Command::SUCCESS;
    }
}

