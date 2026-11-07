<?php

namespace App\Http\Middleware;

use App\Services\RbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows only the platform Owner (root-team pivot role 'owner').
 */
class PlatformOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Authentication required');
        }

        if (RbacService::staffNeedsTwoFactor($user)) {
            return redirect()->route('profile')->with('error', 'Turn on two-factor sign-in to use staff pages.');
        }

        if (! RbacService::isPlatformOwner($user)) {
            abort(403, 'This area is restricted to the platform owner.');
        }

        return $next($request);
    }
}
