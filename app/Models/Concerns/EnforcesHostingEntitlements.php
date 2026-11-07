<?php

namespace App\Models\Concerns;

use App\Exceptions\PlatformRestrictionException;
use App\Services\Commerce\DeploymentGate;

/**
 * Backstop on creation of anything a client can run (applications, services, databases).
 * It applies when a person is signed in; system work (queue, webhooks, seeders) is not a client
 * creating something. Deployments are checked separately, in queue_application_deployment().
 */
trait EnforcesHostingEntitlements
{
    public static function bootEnforcesHostingEntitlements(): void
    {
        static::creating(function ($model) {
            if (! auth()->check()) {
                return;
            }

            $result = DeploymentGate::checkCreate($model);

            if (! $result->isAllowed()) {
                throw new PlatformRestrictionException($result->reason());
            }
        });
    }
}
