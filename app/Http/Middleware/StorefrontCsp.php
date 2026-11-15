<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public storefront and cart use no JavaScript, so the browser is told to run none: an injected script
 * could not execute even if some text were ever rendered unescaped. Fonts come from Google Fonts only.
 */
class StorefrontCsp
{
    public const POLICY = "default-src 'self'; script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        ."font-src https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Content-Security-Policy', self::POLICY);
        $response->headers->set('X-Frame-Options', 'DENY');

        return $response;
    }
}
