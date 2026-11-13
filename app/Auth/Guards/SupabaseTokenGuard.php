<?php

namespace App\Auth\Guards;

use App\Models\User;
use App\Services\Supabase\SupabaseAuthService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class SupabaseTokenGuard implements Guard
{
    protected ?Authenticatable $user = null;

    public function __construct(
        protected SupabaseAuthService $supabaseAuth,
        protected Request $request
    ) {}

    public function check(): bool
    {
        return ! is_null($this->user());
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        $token = $this->request->bearerToken();
        if (blank($token) || ! $this->supabaseAuth->isConfigured()) {
            return null;
        }

        $supabaseUser = $this->supabaseAuth->getUser($token);
        if (! $supabaseUser || empty($supabaseUser['id'])) {
            return null;
        }

        $supabaseId = $supabaseUser['id'];
        $email = $supabaseUser['email'] ?? null;

        $user = User::where('supabase_id', $supabaseId)
            ->when($email, fn ($q) => $q->orWhere('email', strtolower($email)))
            ->first();

        if ($user && ! $user->supabase_id) {
            $user->forceFill(['supabase_id' => $supabaseId])->save();
        }

        $this->user = $user;

        return $this->user;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        $token = $credentials['token'] ?? null;
        if (blank($token)) {
            return false;
        }

        $supabaseUser = $this->supabaseAuth->getUser($token);

        return ! is_null($supabaseUser) && ! empty($supabaseUser['id']);
    }

    public function hasUser(): bool
    {
        return ! is_null($this->user);
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;

        return $this;
    }
}

