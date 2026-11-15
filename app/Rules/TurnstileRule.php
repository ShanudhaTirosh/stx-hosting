<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Security\TurnstileService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TurnstileRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $turnstile = app(TurnstileService::class);

        if (! $turnstile->isEnabled()) {
            return;
        }

        if (! is_string($value) || ! $turnstile->verify($value)) {
            $fail(__('The security check failed. Please refresh and try again.'));
        }
    }
}
