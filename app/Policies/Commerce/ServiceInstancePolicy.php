<?php

namespace App\Policies\Commerce;

use App\Models\Commerce\ServiceInstance;
use App\Models\User;

class ServiceInstancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceInstance $service): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->teams->contains('id', $service->team_id);
    }

    public function viewCredentials(User $user, ServiceInstance $service): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Only team members can reveal credentials for their service
        return $user->teams->contains('id', $service->team_id);
    }

    public function update(User $user, ServiceInstance $service): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isAdminOfTeam($service->team_id);
    }

    public function decommission(User $user, ServiceInstance $service): bool
    {
        return $user->isAdmin();
    }
}

