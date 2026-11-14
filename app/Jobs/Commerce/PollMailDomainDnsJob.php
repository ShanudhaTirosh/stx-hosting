<?php

namespace App\Jobs\Commerce;

use App\Models\Commerce\MailDomain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollMailDomainDnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public string $mailDomainId,
        public int $attempts = 1,
        public int $maxRetries = 10
    ) {}

    public function handle(): void
    {
        $domain = MailDomain::find($this->mailDomainId);

        if (! $domain || $domain->is_verified) {
            return;
        }

        $targetDomain = $domain->domain;
        $platformMailHost = config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.ourdomain.com'));

        $isMxValid = false;
        $isSpfValid = false;
        $isDkimValid = false;
        $isDmarcValid = false;

        // 1. Check MX Record
        $mxRecords = @dns_get_record($targetDomain, DNS_MX);
        if ($mxRecords && is_array($mxRecords)) {
            foreach ($mxRecords as $mx) {
                if (str_contains(strtolower($mx['target'] ?? ''), strtolower($platformMailHost))) {
                    $isMxValid = true;
                    break;
                }
            }
        }

        // 2. Check SPF Record
        $txtRecords = @dns_get_record($targetDomain, DNS_TXT);
        if ($txtRecords && is_array($txtRecords)) {
            foreach ($txtRecords as $txt) {
                $content = strtolower($txt['txt'] ?? '');
                if (str_contains($content, 'v=spf1') && (str_contains($content, strtolower($platformMailHost)) || str_contains($content, 'mx') || str_contains($content, '~all') || str_contains($content, '-all'))) {
                    $isSpfValid = true;
                    break;
                }
            }
        }

        // 3. Check DKIM Record
        $selector = $domain->dkim_selector ?: 'wildduck';
        $dkimRecords = @dns_get_record("{$selector}._domainkey.{$targetDomain}", DNS_TXT);
        if ($dkimRecords && is_array($dkimRecords)) {
            foreach ($dkimRecords as $txt) {
                $content = $txt['txt'] ?? '';
                if (str_contains($content, 'v=DKIM1')) {
                    $isDkimValid = true;
                    break;
                }
            }
        }

        // 4. Check DMARC Record
        $dmarcRecords = @dns_get_record("_dmarc.{$targetDomain}", DNS_TXT);
        if ($dmarcRecords && is_array($dmarcRecords)) {
            foreach ($dmarcRecords as $txt) {
                $content = strtolower($txt['txt'] ?? '');
                if (str_contains($content, 'v=dmarc1')) {
                    $isDmarcValid = true;
                    break;
                }
            }
        }

        if (app()->environment(['local', 'testing'])) {
            $isMxValid = true;
            $isSpfValid = true;
            $isDkimValid = true;
            $isDmarcValid = true;
        }

        $verified = ($isMxValid || app()->environment(['local', 'testing']));

        $domain->update([
            'is_verified' => $verified,
            'verified_at' => $verified ? ($domain->verified_at ?: now()) : null,
            'last_checked_at' => now(),
            'dns_status' => [
                'mx' => $isMxValid,
                'spf' => $isSpfValid,
                'dkim' => $isDkimValid,
                'dmarc' => $isDmarcValid,
            ],
        ]);

        if ($verified) {
            Log::info("Mail domain {$domain->domain} successfully verified via background DNS polling.");

            return;
        }

        // Retry with backoff if attempts are within limits
        if ($this->attempts < $this->maxRetries) {
            $delaySeconds = $this->attempts <= 3 ? 30 : 120;
            self::dispatch($this->mailDomainId, $this->attempts + 1, $this->maxRetries)
                ->delay(now()->addSeconds($delaySeconds));
        }
    }
}

