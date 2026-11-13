<?php

namespace App\Jobs\Commerce;

use App\Models\CustomDomain;
use App\Services\Commerce\CustomDomainService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollCustomDomainDnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public CustomDomain $domain,
        public int $maxRetries = 10
    ) {}

    public function handle(CustomDomainService $service): void
    {
        $this->domain->refresh();

        if ($this->domain->isVerified()) {
            return;
        }

        $verified = $service->verify($this->domain);

        if ($verified) {
            Log::info("Custom domain {$this->domain->domain} automatically verified via background polling.");

            return;
        }

        // Retry with backoff if attempts are within limits
        if ($this->domain->check_attempts < $this->maxRetries) {
            $delaySeconds = $this->domain->check_attempts <= 3 ? 30 : 120;
            self::dispatch($this->domain, $this->maxRetries)->delay(now()->addSeconds($delaySeconds));
        }
    }
}

