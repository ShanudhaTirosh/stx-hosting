<?php

namespace App\Services\Auth;

use App\Models\EmailDeliveryLog;
use App\Models\LoginVerificationCode;
use App\Models\User;
use App\Services\Notifications\EmailTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TwoFactorVerificationService
{
    public const COOKIE_NAME = 'stx_trusted_device';
    public const CODE_TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;

    /**
     * Determine if a user login requires a verification challenge.
     */
    public function requiresChallenge(User $user, Request $request): bool
    {
        $policy = $user->login_2fa_policy ?: 'new_device';

        if ($policy === 'always') {
            return true;
        }

        if ($policy === 'sensitive_only') {
            return false;
        }

        // Policy is 'new_device': Check if the request contains a valid trusted device cookie
        return ! $this->isDeviceTrusted($user, $request);
    }

    /**
     * Check if the current device is trusted for this user.
     */
    public function isDeviceTrusted(User $user, Request $request): bool
    {
        $cookieToken = $request->cookie(self::COOKIE_NAME);
        if (! $cookieToken) {
            return false;
        }

        // Cache or hash verification: token structure is "user_id|secret"
        $parts = explode('|', $cookieToken, 2);
        if (count($parts) !== 2 || (int) $parts[0] !== $user->id) {
            return false;
        }

        $expectedHash = hash_hmac('sha256', "{$user->id}|{$user->password}", config('app.key'));
        return hash_equals($expectedHash, $parts[1]);
    }

    /**
     * Generate a trusted device cookie.
     */
    public function createTrustedDeviceCookie(User $user): \Symfony\Component\HttpFoundation\Cookie
    {
        $secret = hash_hmac('sha256', "{$user->id}|{$user->password}", config('app.key'));
        $value = "{$user->id}|{$secret}";

        // 30 days expiration
        return Cookie::make(self::COOKIE_NAME, $value, 60 * 24 * 30, null, null, true, true, false, 'Lax');
    }

    /**
     * Generate a 6-digit one-time code and dispatch verification email.
     */
    public function issueCode(User $user, Request $request): LoginVerificationCode
    {
        // Invalidate any existing unused codes for this user
        LoginVerificationCode::where('user_id', $user->id)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        // Generate high-entropy 6-digit numeric code (100000 - 999999)
        $rawCode = (string) random_int(100000, 999999);

        $ipAddress = $request->ip() ?: 'Unknown IP';
        $userAgent = $request->userAgent() ?: 'Unknown Device';

        $record = LoginVerificationCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($rawCode),
            'ip_address' => $ipAddress,
            'user_agent' => Str::limit($userAgent, 255),
            'attempts' => 0,
            'is_used' => false,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        // Send email via EmailTemplates and Mailer
        $this->sendEmail($user, $rawCode, $ipAddress, $userAgent);

        return $record;
    }

    /**
     * Verify a submitted code.
     * Returns true on success, false on invalid or expired code.
     */
    public function verifyCode(User $user, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code); // Clean spaces or dashes

        $record = LoginVerificationCode::where('user_id', $user->id)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $record) {
            return false;
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            $record->update(['is_used' => true]);
            return false;
        }

        $record->increment('attempts');

        if (Hash::check($code, $record->code_hash)) {
            $record->update(['is_used' => true]);
            return true;
        }

        return false;
    }

    /**
     * Send templated login verification email.
     */
    protected function sendEmail(User $user, string $rawCode, string $ip, string $userAgent): void
    {
        try {
            $mailMessage = EmailTemplates::message('login_code', [
                'name' => $user->name ?: 'Customer',
                'code' => $rawCode,
                'ip_address' => $ip,
                'device' => Str::limit($userAgent, 40),
            ]);

            Mail::to($user->email)->send($mailMessage);

            EmailDeliveryLog::record(
                recipient: $user->email,
                subject: "Your STX Enterprises verification code: {$rawCode}",
                templateKey: 'login_code',
                status: 'sent',
                meta: ['ip' => $ip, 'user_id' => $user->id]
            );
        } catch (\Throwable $e) {
            EmailDeliveryLog::record(
                recipient: $user->email,
                subject: 'Your STX Enterprises verification code',
                templateKey: 'login_code',
                status: 'failed',
                errorMessage: $e->getMessage(),
                meta: ['ip' => $ip, 'user_id' => $user->id]
            );

            logger()->error("Failed to send 2FA login verification code: {$e->getMessage()}");
        }
    }
}
