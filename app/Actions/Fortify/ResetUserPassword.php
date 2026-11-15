<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Security\TurnstileService;
use App\Services\Supabase\SupabaseAuthService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        $turnstile = app(TurnstileService::class);
        $turnstileToken = $input['cf-turnstile-response'] ?? request()->input('cf-turnstile-response');
        if ($turnstile->isEnabled() && ! $turnstile->verify($turnstileToken)) {
            throw ValidationException::withMessages([
                'email' => __('The security check failed. Please refresh and try again.'),
            ]);
        }

        Validator::make($input, [
            'password' => ['required', Password::defaults(), 'confirmed'],
        ])->validate();

        $user->fill([
            'password' => Hash::make($input['password']),
            'force_password_reset' => false,
        ])->save();

        if ($user->supabase_id) {
            app(SupabaseAuthService::class)->updateUserPassword($user->supabase_id, $input['password']);
        }
        $user->deleteAllSessions();
    }
}
