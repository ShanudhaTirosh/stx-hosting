<?php

namespace App\Jobs;

use App\Actions\Server\UpdateStx;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateStxJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct()
    {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        try {
            CheckForUpdatesJob::dispatchSync();
            $settings = instanceSettings();
            if (! $settings->new_version_available) {
                Log::info('No new version available. Skipping update.');

                return;
            }

            $server = Server::findOrFail(0);
            if (! $server) {
                Log::error('Server not found. Cannot proceed with update.');

                return;
            }

            Log::info('Starting STX Hosting update process...');
            UpdateStx::run(false); // false means it's not a manual update

            Log::info('STX Hosting upgrade script started. Result: /data/coolify/source/.upgrade-status, log: /data/coolify/source/upgrade-*.log.');
        } catch (\Throwable $e) {
            Log::error('UpdateStxJob failed: '.$e->getMessage());
            // Consider implementing a notification to administrators
        }
    }
}
