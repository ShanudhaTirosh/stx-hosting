<?php

namespace App\Services\Commerce;

use App\Models\Application;
use App\Models\Service;
use App\Models\Team;
use App\Services\EntitlementService;
use App\Services\LimitCheckResult;
use App\Services\LimitEnforcementService;
use App\Services\RbacService;
use Illuminate\Database\Eloquent\Model;

/**
 * The one rule for "may this team create or run this?":
 *
 *   1. platform staff and the root team are never restricted
 *   2. the team must hold an active entitlement for that kind of product (apps, databases)
 *      - paying creates it, expiry removes it
 *   3. the team must be inside its plan limits
 *
 * Used when a resource is created and every time a deployment is queued
 * (UI, API, rollbacks and Git push webhooks all go through the same queue).
 */
class DeploymentGate
{
    public static function enabled(): bool
    {
        return (bool) config('hosting.enforce_entitlements', true);
    }

    public static function checkCreate(Model $resource): LimitCheckResult
    {
        $isDatabase = str_starts_with(class_basename($resource), 'Standalone');

        return self::evaluate(
            self::teamOf($resource),
            $isDatabase ? 'managed_database' : 'app_hosting',
            $isDatabase ? 'databases' : ($resource instanceof Service ? 'services' : 'applications'),
            fn (Team $team) => LimitEnforcementService::canCreateResource($team, $isDatabase ? 'database' : 'application'),
        );
    }

    public static function checkDeploy(Application $application): LimitCheckResult
    {
        $running = str_starts_with((string) $application->status, 'running');

        return self::evaluate(
            self::teamOf($application),
            'app_hosting',
            'applications',
            // Redeploying something that is already running does not use an extra container.
            fn (Team $team) => $running ? LimitCheckResult::allow() : LimitEnforcementService::canDeployResource($team, $application),
        );
    }

    private static function evaluate(?Team $team, string $capability, string $label, callable $limits): LimitCheckResult
    {
        if (! self::enabled() || $team === null || (int) $team->id === 0) {
            return LimitCheckResult::allow();
        }

        $user = auth()->user();
        if ($user && RbacService::shouldBypassLimits($user)) {
            return LimitCheckResult::allow();
        }

        if (! EntitlementService::hasCapability($team, $capability)) {
            return LimitCheckResult::deny(
                "Your team has no active plan that includes {$label}. Buy a plan in the store, or renew yours if it has expired."
            );
        }

        return $limits($team);
    }

    private static function teamOf(Model $resource): ?Team
    {
        return $resource->environment?->project?->team;
    }
}
