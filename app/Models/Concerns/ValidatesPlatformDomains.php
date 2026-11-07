<?php

namespace App\Models\Concerns;

use App\Services\Commerce\DeploymentGate;
use App\Services\Commerce\PlatformDomainService;
use App\Services\RbacService;

/**
 * Applications only: a client can use our wildcard names and domains they proved they own.
 * New client apps get <app-name>.<apps_domain> automatically.
 */
trait ValidatesPlatformDomains
{
    public static function bootValidatesPlatformDomains(): void
    {
        static::saving(function ($application) {
            $user = auth()->user();

            if (! DeploymentGate::enabled() || ! $user || RbacService::shouldBypassLimits($user)) {
                return;
            }

            if (! $application->isDirty('fqdn') || blank($application->fqdn)) {
                return;
            }

            $team = $application->team();
            if ($team && (int) $team->id !== 0) {
                PlatformDomainService::assertAllowed($team, $application->fqdn, $application->id);
            }
        });

        static::created(function ($application) {
            if (DeploymentGate::enabled()) {
                PlatformDomainService::assignSubdomain($application);
            }
        });
    }
}
