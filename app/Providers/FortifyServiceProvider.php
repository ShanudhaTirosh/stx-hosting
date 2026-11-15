<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use App\Services\Security\TurnstileService;
use App\Services\Supabase\SupabaseAuthService;
use App\Support\PasskeyRelyingParty;
use App\Support\RootSetupWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->instance(RegisterResponse::class, new class implements RegisterResponse
        {
            public function toResponse($request)
            {
                // First user (root) will be redirected to /settings instead of / on registration.
                if ($request->user()->currentTeam()?->id === 0) {
                    return redirect()->route('settings.index');
                }

                return redirect(RouteServiceProvider::HOME);
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::registerView(function () {
            $isFirstUser = User::count() === 0;

            $settings = instanceSettings();
            if (! $settings->isPasswordRegistrationAllowed()) {
                return redirect()->route('login');
            }

            return view('auth.register', [
                'isFirstUser' => $isFirstUser,
                'isRootSetupWindowClosed' => $isFirstUser && ! RootSetupWindow::isOpen(),
            ]);
        });

        Fortify::loginView(function () {
            $settings = instanceSettings();
            $users = User::count();
            if ($users == 0 && $settings->isPasswordRegistrationAllowed()) {
                // If there are no users and password registration is allowed, redirect to registration.
                return redirect()->route('register');
            }

            return view('auth.login', [
                'is_registration_enabled' => $settings->isPasswordRegistrationAllowed(),
                'passkeys_available' => PasskeyRelyingParty::isAvailable(),
            ]);
        });

        Fortify::authenticateUsing(function (Request $request) {
            $turnstile = app(TurnstileService::class);
            $turnstileToken = $request->input('cf-turnstile-response');
            if ($turnstile->isEnabled() && ! $turnstile->verify($turnstileToken, $request->ip())) {
                throw ValidationException::withMessages([
                    'email' => __('The security check failed. Please refresh and try again.'),
                ]);
            }

            $email = strtolower(trim((string) $request->email));
            $password = (string) $request->password;

            $supabaseAuth = app(SupabaseAuthService::class);

            // 1. Supabase Auth Verification (Primary)
            if ($supabaseAuth->isAuthEnabled()) {
                $supabaseResponse = $supabaseAuth->signInWithPassword($email, $password);
                if ($supabaseResponse && isset($supabaseResponse['user']['id'])) {
                    $supabaseId = $supabaseResponse['user']['id'];
                    $user = User::where('supabase_id', $supabaseId)
                        ->orWhere('email', $email)
                        ->with('teams')
                        ->first();

                    if (! $user) {
                        $name = data_get($supabaseResponse, 'user.user_metadata.name', explode('@', $email)[0]);
                        $user = User::create([
                            'supabase_id' => $supabaseId,
                            'name' => $name,
                            'email' => $email,
                            'password' => Hash::make($password),
                            'email_verified_at' => now(),
                        ]);
                    } elseif (! $user->supabase_id) {
                        $user->forceFill(['supabase_id' => $supabaseId])->save();
                    }

                    $user->updated_at = now();
                    $user->save();

                    // Store Supabase session tokens
                    if (isset($supabaseResponse['access_token'])) {
                        session(['supabase_access_token' => $supabaseResponse['access_token']]);
                        cookie()->queue(cookie('sb_access_token', $supabaseResponse['access_token'], 60, null, null, true, true, false, 'lax'));
                    }
                    if (isset($supabaseResponse['refresh_token'])) {
                        session(['supabase_refresh_token' => $supabaseResponse['refresh_token']]);
                    }

                    $team = $user->resolveStoredTeam();
                    if (! $team && $user->teams->isEmpty()) {
                        $team = $user->recreate_personal_team();
                    }
                    if ($team) {
                        session(['currentTeam' => $team]);
                    }

                    return $user;
                }
            }

            // 2. Native Auth Fallback
            $user = User::where('email', $email)->with('teams')->first();
            if (
                $user &&
                Hash::check($password, $user->password)
            ) {
                $user->updated_at = now();
                $user->save();

                // Pending team invitations are not accepted here; the user accepts
                // them explicitly on the invitation page (team.invitation.show).
                // Restore the last active team; only fall back when unambiguous.
                $team = $user->resolveStoredTeam();
                if (! $team && $user->teams->isEmpty()) {
                    $team = $user->recreate_personal_team();
                }
                if ($team) {
                    session(['currentTeam' => $team]);
                }
                // Otherwise (multiple teams, no stored choice) leave the session
                // team unset so the user is sent to the team-selection screen.

                return $user;
            }
        });
        Fortify::requestPasswordResetLinkView(function () {
            return view('auth.forgot-password');
        });
        Fortify::resetPasswordView(function ($request) {
            return view('auth.reset-password', ['request' => $request]);
        });
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        Fortify::confirmPasswordView(function () {
            return view('auth.confirm-password', [
                'can_confirm_with_passkey' => PasskeyRelyingParty::isAvailable()
                    && auth()->user()->hasPasskeysEnabled(),
            ]);
        });

        Fortify::twoFactorChallengeView(function () {
            return view('auth.two-factor-challenge');
        });
    }
}
