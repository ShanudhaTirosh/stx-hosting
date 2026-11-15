<?php

namespace App\Policies;

use App\Models\InstanceSettings;
use App\Models\User;
use App\Services\RbacService;

class InstanceSettingsPolicy
{
    /**
     * Determine whether the user can view the instance settings.
     */
    public function view(User $user, InstanceSettings $settings): bool
    {
        return RbacService::can($user, 'platform.settings') || $user->isInstanceAdmin();
    }

    /**
     * Determine whether the user can update the instance settings.
     */
    public function update(User $user, InstanceSettings $settings): bool
    {
        return RbacService::can($user, 'platform.settings') || $user->isInstanceAdmin();
    }
}
