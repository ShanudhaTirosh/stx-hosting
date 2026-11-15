<?php

namespace App\Actions\Fortify;

use App\Jobs\SendVerificationEmailJob;
use App\Models\Team;
use App\Models\User;
use App\Services\Security\TurnstileService;
use App\Services\Supabase\SupabaseAuthService;
use App\Support\RootSetupWindow;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    private const REGISTRATION_IP_MAX_ATTEMPTS = 3;

    private const REGISTRATION_IP_DECAY_SECONDS = 600;

    private const REGISTRATION_EMAIL_IDENTITY_MAX_ATTEMPTS = 3;

    private const REGISTRATION_EMAIL_IDENTITY_DECAY_SECONDS = 3600;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $settings = instanceSettings();
        if (! $settings->isPasswordRegistrationAllowed()) {
            abort(403);
        }

        if (! User::query()->exists() && ! RootSetupWindow::isOpen()) {
            throw ValidationException::withMessages([
                'email' => 'The setup window for the root account is closed. Restart STX Hosting on the server (docker restart stx) to open it again.',
            ]);
        }

        $this->ensureRegistrationIsNotRateLimited($input);

        $turnstile = app(TurnstileService::class);
        $turnstileToken = $input['cf-turnstile-response'] ?? request()->input('cf-turnstile-response');
        if ($turnstile->isEnabled() && ! $turnstile->verify($turnstileToken)) {
            throw ValidationException::withMessages([
                'email' => __('The security check failed. Please refresh and try again.'),
            ]);
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ])->validate();

        $supabaseId = null;
        $supabaseAuth = app(SupabaseAuthService::class);
        if ($supabaseAuth->isAuthEnabled()) {
            $supabaseResult = $supabaseAuth->signUp($input['email'], $input['password'], ['name' => $input['name']]);
            if (! $supabaseResult || ! isset($supabaseResult['user']['id'])) {
                throw ValidationException::withMessages([
                    'email' => 'Failed to create user account with Supabase Auth.',
                ]);
            }
            $supabaseId = $supabaseResult['user']['id'];
        }

        if (User::count() == 0) {
            // If this is the first user, make them the root user
            // Team is already created in the database/seeders/ProductionSeeder.php
            $user = (new User)->forceFill([
                'id' => 0,
                'supabase_id' => $supabaseId,
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);
            $user->save();
            $team = $user->teams()->first() ?? Team::find(0);
            if ($team !== null && ! $user->teams()->where('team_id', $team->id)->exists()) {
                $user->teams()->attach($team, ['role' => 'owner']);
            }

            // Disable registration after first user is created
            $settings = instanceSettings();
            $settings->is_registration_enabled = false;
            $settings->save();
        } else {
            $user = User::create([
                'supabase_id' => $supabaseId,
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);
            $team = $user->teams()->first();
            if (isCloud()) {
                SendVerificationEmailJob::dispatch($user);
            } else {
                $user->markEmailAsVerified();
            }
        }
        // Set session variable
        session(['currentTeam' => $team]);

        return $user;
    }

    /**
     * @param  array<string, string>  $input
     */
    private function ensureRegistrationIsNotRateLimited(array $input): void
    {
        $keys = [
            [
                'key' => 'registration:ip:'.sha1(client_ip(request())),
                'max' => self::REGISTRATION_IP_MAX_ATTEMPTS,
                'decay' => self::REGISTRATION_IP_DECAY_SECONDS,
            ],
        ];

        $emailIdentity = normalize_email_identity($input['email'] ?? null);
        if ($emailIdentity !== null) {
            $keys[] = [
                'key' => 'registration:email-identity:'.sha1($emailIdentity),
                'max' => self::REGISTRATION_EMAIL_IDENTITY_MAX_ATTEMPTS,
                'decay' => self::REGISTRATION_EMAIL_IDENTITY_DECAY_SECONDS,
            ];
        }

        foreach ($keys as $limit) {
            if (RateLimiter::tooManyAttempts($limit['key'], $limit['max'])) {
                abort(429, 'Too many registration attempts. Please try again later.');
            }
        }

        foreach ($keys as $limit) {
            RateLimiter::hit($limit['key'], $limit['decay']);
        }
    }
}
