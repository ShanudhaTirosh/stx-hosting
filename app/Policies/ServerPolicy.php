<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;
use App\Services\RbacService;

class ServerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Server $server): bool
    {
        return $user->teams->contains('id', $server->team_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isInstanceAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Server $server): bool
    {
        return $user->isAdminOfTeam($server->team_id);
    }

    /**
     * Determine whether the user can transfer the server to another Coolify instance.
     *
     * A transfer sends every secret of the server, also locked environment variables, to the target, so only owners may start it.
     */
    public function transfer(User $user, Server $server): bool
    {
        return $user->roleInTeam($server->team_id) === 'owner';
    }

    /**
     * Determine whether the user can delete the model.
     * Administrators must NOT be able to remove existing server nodes. Only Platform Owners can remove server nodes.
     */
    public function delete(User $user, Server $server): bool
    {
        if (RbacService::isAdmin($user) && ! RbacService::isOwner($user)) {
            return false;
        }

        return RbacService::isOwner($user) || $user->roleInTeam($server->team_id) === 'owner';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Server $server): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Server $server): bool
    {
        return false;
    }

    /**
     * Determine whether the user can manage proxy (start/stop/restart).
     */
    public function manageProxy(User $user, Server $server): bool
    {
        return $user->isAdminOfTeam($server->team_id);
    }

    /**
     * Determine whether the user can manage sentinel (start/stop).
     */
    public function manageSentinel(User $user, Server $server): bool
    {
        return $user->isAdminOfTeam($server->team_id);
    }

    /**
     * Determine whether the user can view Sentinel configuration and logs.
     */
    public function viewSentinel(User $user, Server $server): bool
    {
        return $user->isAdminOfTeam($server->team_id);
    }

    /**
     * Determine whether the user can manage CA certificates.
     */
    public function manageCaCertificate(User $user, Server $server): bool
    {
        return $user->isAdminOfTeam($server->team_id);
    }

    /**
     * Determine whether the user can view security views.
     */
    public function viewSecurity(User $user, Server $server): bool
    {
        return $user->isAdminOfTeam($server->team_id);
    }
}
