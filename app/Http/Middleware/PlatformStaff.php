<?php

namespace App\Http\Middleware;

use App\Services\RbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows only platform staff (root-team Owner or Admin).
 * Do not replace this with User::isAdmin(), which is true for every client in their own team.
 */
class PlatformStaff
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

        if (! RbacService::isStaff($user)) {
            abort(403, 'This area is restricted to platform staff.');
        }

        return $next($request);
    }
}
