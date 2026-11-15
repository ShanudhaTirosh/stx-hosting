<?php

namespace App\Providers;

use App\Models\EmailDeliveryLog;
use App\Services\Supabase\SupabaseAuthService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Events\PasskeyVerified;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [];

    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            auditLog('auth.user.login_succeeded', $this->authContext($event->user));
        });
        Event::listen(Failed::class, function (Failed $event): void {
            auditLog('auth.user.login_failed', [
                'attempted_email' => $this->attemptedEmailForAudit(data_get($event->credentials, 'email')),
                'guard' => $event->guard,
            ], 'warning');
        });
        Event::listen(Logout::class, function (Logout $event): void {
            $token = session('supabase_access_token');
            if ($token) {
                app(SupabaseAuthService::class)->signOut($token);
            }
            session()->forget(['supabase_access_token', 'supabase_refresh_token']);
            cookie()->queue(cookie()->forget('sb_access_token'));
            auditLog('auth.user.logged_out', $this->authContext($event->user));
        });
        Event::listen(Registered::class, function (Registered $event): void {
            auditLog('auth.user.registered', $this->authContext($event->user));
        });
        Event::listen(Verified::class, function (Verified $event): void {
            auditLog('auth.user.email_verified', $this->authContext($event->user));
        });
        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            auditLog('auth.user.password_reset', $this->authContext($event->user));
        });
        Event::listen(TwoFactorAuthenticationEnabled::class, function (TwoFactorAuthenticationEnabled $event): void {
            auditLog('auth.user.two_factor_enabled', $this->authContext($event->user));
        });
        Event::listen(TwoFactorAuthenticationConfirmed::class, function (TwoFactorAuthenticationConfirmed $event): void {
            auditLog('auth.user.two_factor_confirmed', $this->authContext($event->user));
        });
        Event::listen(TwoFactorAuthenticationDisabled::class, function (TwoFactorAuthenticationDisabled $event): void {
            auditLog('auth.user.two_factor_disabled', $this->authContext($event->user));
        });
        Event::listen(RecoveryCodesGenerated::class, function (RecoveryCodesGenerated $event): void {
            auditLog('auth.user.recovery_codes_regenerated', $this->authContext($event->user));
        });
        Event::listen(PasskeyRegistered::class, function (PasskeyRegistered $event): void {
            auditLog('auth.user.passkey_registered', $this->authContext($event->user));
        });
        Event::listen(PasskeyVerified::class, function (PasskeyVerified $event): void {
            auditLog('auth.user.passkey_verified', $this->authContext($event->user));
        });
        Event::listen(PasskeyDeleted::class, function (PasskeyDeleted $event): void {
            auditLog('auth.user.passkey_deleted', $this->authContext($event->user));
        });

        Event::listen(MessageSent::class, function (MessageSent $event): void {
            try {
                $symfonyMessage = $event->message;
                $toAddresses = [];
                if (method_exists($symfonyMessage, 'getTo')) {
                    foreach ((array) $symfonyMessage->getTo() as $address) {
                        $toAddresses[] = method_exists($address, 'getAddress') ? $address->getAddress() : (string) $address;
                    }
                }
                $recipient = implode(', ', $toAddresses) ?: 'recipient';
                $subject = method_exists($symfonyMessage, 'getSubject') ? (string) $symfonyMessage->getSubject() : 'STX Notification';
                $templateKey = $event->data['template_key'] ?? $event->data['key'] ?? null;
                $mailer = config('mail.default');

                EmailDeliveryLog::record(
                    recipient: $recipient,
                    subject: $subject,
                    templateKey: $templateKey,
                    status: 'delivered',
                    mailer: $mailer,
                    meta: [
                        'timestamp' => now()->toIso8601String(),
                    ]
                );
            } catch (\Throwable) {
                // Silently ignore logging failures to not disrupt mail flow
            }
        });
    }

    /**
     * Users sometimes type a password into the email field, so only a valid email address is stored.
     */
    private function attemptedEmailForAudit(mixed $email): ?string
    {
        if (blank($email)) {
            return null;
        }

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '[invalid]';
    }

    private function authContext(?object $user): array
    {
        return [
            'team_id' => $user?->currentTeam()?->id,
            'resource' => 'user',
            'user_name' => $user?->name,
            'actor_id' => $user?->id,
            'actor_name' => $user?->name,
            'actor_email' => $user?->email,
        ];
    }

    public function shouldDiscoverEvents(): bool
    {
        return true;
    }
}
