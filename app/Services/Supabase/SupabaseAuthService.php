<?php

namespace App\Services\Supabase;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseAuthService
{
    protected ?string $url;
    protected ?string $key;
    protected ?string $serviceRoleKey;
    protected ?string $jwtSecret;

    public function __construct()
    {
        $this->url = rtrim((string) config('supabase.url'), '/');
        $this->key = config('supabase.key');
        $this->serviceRoleKey = config('supabase.service_role_key');
        $this->jwtSecret = config('supabase.jwt_secret');
    }

    public function isConfigured(): bool
    {
        return filled($this->url) && (filled($this->key) || filled($this->serviceRoleKey));
    }

    public function isAuthEnabled(): bool
    {
        return (bool) config('supabase.auth_enabled', false) && $this->isConfigured();
    }

    /**
     * Authenticate user with email and password via Supabase Auth GoTrue API.
     */
    public function signInWithPassword(string $email, string $password): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $this->key ?: $this->serviceRoleKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$this->url}/auth/v1/token?grant_type=password", [
                'email' => strtolower(trim($email)),
                'password' => $password,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Supabase password sign-in failed', [
                'email' => $email,
                'status' => $response->status(),
                'error' => $response->json('error_description') ?? $response->body(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error("Supabase sign-in exception: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Sign up a new user via Supabase Auth API.
     */
    public function signUp(string $email, string $password, array $data = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $apiKey = $this->serviceRoleKey ?: $this->key;

        try {
            $response = Http::withHeaders([
                'apikey' => $apiKey,
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$this->url}/auth/v1/signup", [
                'email' => strtolower(trim($email)),
                'password' => $password,
                'data' => $data,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Supabase signup failed', [
                'email' => $email,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error("Supabase signup exception: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Send password recovery email via Supabase Auth.
     */
    public function resetPasswordForEmail(string $email, ?string $redirectTo = null): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $payload = ['email' => strtolower(trim($email))];
            if ($redirectTo) {
                $payload['redirect_to'] = $redirectTo;
            }

            $response = Http::withHeaders([
                'apikey' => $this->key ?: $this->serviceRoleKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$this->url}/auth/v1/recover", $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("Supabase recover exception: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Update user password using Supabase Admin API.
     */
    public function updateUserPassword(string $supabaseId, string $newPassword): bool
    {
        if (blank($this->serviceRoleKey) || blank($this->url)) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $this->serviceRoleKey,
                'Authorization' => "Bearer {$this->serviceRoleKey}",
                'Content-Type' => 'application/json',
            ])->timeout(10)->put("{$this->url}/auth/v1/admin/users/{$supabaseId}", [
                'password' => $newPassword,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("Supabase admin update password exception: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Fetch user profile from Supabase using an active access token.
     */
    public function getUser(string $accessToken): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $this->key ?: $this->serviceRoleKey,
                'Authorization' => "Bearer {$accessToken}",
            ])->timeout(10)->get("{$this->url}/auth/v1/user");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

