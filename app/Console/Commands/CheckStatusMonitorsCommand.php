<?php

namespace App\Console\Commands;

use App\Models\Commerce\Monitor;
use App\Services\Status\StatusCheckService;
use Illuminate\Console\Command;

class CheckStatusMonitorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'status:check {--monitor= : Check specific monitor ID}';

    /**
     * The console command description.
     */
    protected $description = 'Execute health, latency and uptime checks for infrastructure monitors';

    /**
     * Execute the console command.
     */
    public function handle(StatusCheckService $service): int
    {
        $monitorId = $this->option('monitor');

        if ($monitorId) {
            $monitor = Monitor::find($monitorId);
            if (!$monitor) {
                $this->error("Monitor ID {$monitorId} not found.");
                return 1;
            }
            $this->info("Checking monitor: {$monitor->name} ({$monitor->type})...");
            $res = $service->check($monitor);
            $this->line("Result: Status={$res['status']}, Latency={$res['latency_ms']}ms, Error=" . ($res['error'] ?? 'None'));
            return 0;
        }

        $this->info('Starting automated check for all active monitors...');
        $results = $service->checkAll();

        $rows = [];
        foreach ($results as $id => $res) {
            $rows[] = [$id, $res['monitor'], $res['status'], "{$res['latency_ms']} ms", $res['error'] ?? 'OK'];
        }

        $this->table(['ID', 'Monitor', 'Status', 'Latency', 'Details'], $rows);
        $this->info("Completed health checks for " . count($results) . " monitors.");

        return 0;
    }
}

