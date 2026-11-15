<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Supabase\SupabaseAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySupabaseSession
{
    public function __construct(
        protected SupabaseAuthService $supabaseAuth
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->supabaseAuth->isAuthEnabled() || ! auth()->check()) {
            return $next($request);
        }

        $accessToken = session('supabase_access_token') ?? $request->cookie('sb_access_token');
        $refreshToken = session('supabase_refresh_token');

        if ($accessToken) {
            // Check if JWT token is expired
            $isExpired = $this->isJwtExpired($accessToken);

            if ($isExpired && $refreshToken) {
                $refreshed = $this->supabaseAuth->refreshToken($refreshToken);
                if ($refreshed && isset($refreshed['access_token'])) {
                    $accessToken = $refreshed['access_token'];
                    session(['supabase_access_token' => $accessToken]);
                    cookie()->queue(cookie('sb_access_token', $accessToken, 60, null, null, true, true, false, 'lax'));

                    if (isset($refreshed['refresh_token'])) {
                        session(['supabase_refresh_token' => $refreshed['refresh_token']]);
                    }
                } else {
                    // Token expired and cannot refresh
                    session()->forget(['supabase_access_token', 'supabase_refresh_token']);
                    cookie()->queue(cookie()->forget('sb_access_token'));
                }
            }
        }

        return $next($request);
    }

    private function isJwtExpired(string $jwt): bool
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return true;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (! is_array($payload) || ! isset($payload['exp'])) {
            return true;
        }

        // Buffer of 60 seconds before actual expiration
        return time() >= ($payload['exp'] - 60);
    }
}
