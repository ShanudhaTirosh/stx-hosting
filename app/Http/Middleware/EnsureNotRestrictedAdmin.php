<?php

namespace App\Http\Middleware;

use App\Services\RbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts platform administrators from accessing the customer portal, client dashboards, and deployments.
 * Admins are dedicated strictly to the Admin Management Console.
 * Owners and Clients are allowed through.
 */
class EnsureNotRestrictedAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && RbacService::isPlatformAdminOnly($user)) {
            abort(403, 'Platform administrators have access to the Admin Management Console only. Client portal, deployments, and resources are restricted.');
        }

        return $next($request);
    }
}
