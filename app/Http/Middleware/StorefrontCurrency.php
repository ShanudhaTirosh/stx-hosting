<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Remembers the visitor's display currency (?currency=LKR or USD) for the whole storefront and cart. */
class StorefrontCurrency
{
    public function handle(Request $request, Closure $next): Response
    {
        $requested = strtoupper((string) $request->query('currency', ''));

        if (in_array($requested, ['USD', 'LKR'], true)) {
            $request->session()->put('storefront.currency', $requested);
        }

        return $next($request);
    }
}
