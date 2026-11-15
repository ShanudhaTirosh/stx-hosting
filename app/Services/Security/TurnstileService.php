<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    private const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function isEnabled(): bool
    {
        return (bool) config('services.turnstile.enabled', true) && filled(config('services.turnstile.site_key'));
    }

    public function getSiteKey(): string
    {
        return (string) config('services.turnstile.site_key', '');
    }

    /**
     * Verify a Turnstile response token with Cloudflare's siteverify endpoint.
     */
    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if (blank($token)) {
            Log::warning('Turnstile verification failed: missing response token', [
                'ip' => $remoteIp ?? request()->ip(),
            ]);

            return false;
        }

        $secretKey = config('services.turnstile.secret_key');

        // Cloudflare official test tokens support (e.g. 1x00000000000000000000AA)
        if (app()->environment('local', 'testing') && str_starts_with($token, 'mock_turnstile_success')) {
            return true;
        }

        if (app()->environment('local', 'testing') && str_starts_with($token, 'mock_turnstile_fail')) {
            return false;
        }

        if (blank($secretKey)) {
            if (app()->environment('local', 'testing')) {
                return true;
            }

            Log::error('Turnstile verification failed: missing TURNSTILE_SECRET_KEY');

            return ! (bool) config('services.turnstile.fail_closed', true);
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::SITEVERIFY_URL, [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp ?? request()->ip(),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $success = (bool) data_get($data, 'success', false);

                if (! $success) {
                    Log::warning('Turnstile challenge failed', [
                        'ip' => $remoteIp ?? request()->ip(),
                        'error_codes' => data_get($data, 'error-codes', []),
                    ]);
                }

                return $success;
            }

            Log::error('Turnstile verification HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ! (bool) config('services.turnstile.fail_closed', true);
        } catch (\Throwable $e) {
            Log::error('Turnstile verification exception', [
                'message' => $e->getMessage(),
                'ip' => $remoteIp ?? request()->ip(),
            ]);

            // Fail-closed by default unless configured otherwise
            return ! (bool) config('services.turnstile.fail_closed', true);
        }
    }
}
