<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
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
    public function view(User $user, Team $team): bool
    {
        return $user->teams->contains('id', $team->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create teams
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        if (! $user->teams->contains('id', $team->id)) {
            return false;
        }

        return $user->isAdminOfTeam($team->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Team $team): bool
    {
        if (! $user->teams->contains('id', $team->id)) {
            return false;
        }

        return $user->canPerformInTeam($team->id, 'team.delete');
    }

    /**
     * Determine whether the user can transfer team ownership.
     */
    public function transferOwnership(User $user, Team $team): bool
    {
        if (! $user->teams->contains('id', $team->id)) {
            return false;
        }

        return $user->canPerformInTeam($team->id, 'ownership.transfer');
    }

    /**
     * Determine whether the user can manage team members.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        if (! $user->teams->contains('id', $team->id)) {
            return false;
        }

        return $user->canPerformInTeam($team->id, 'members.manage');
    }

    /**
     * Determine whether the user can view admin panel.
     */
    public function viewAdmin(User $user, Team $team): bool
    {
        if (! $user->teams->contains('id', $team->id)) {
            return false;
        }

        return $user->isAdminOfTeam($team->id);
    }

    /**
     * Determine whether the user can manage invitations.
     */
    public function manageInvitations(User $user, Team $team): bool
    {
        if (! $user->teams->contains('id', $team->id)) {
            return false;
        }

        return $user->canPerformInTeam($team->id, 'members.manage');
    }

    /**
     * Determine whether the user can view billing and invoices.
     */
    public function viewBilling(User $user, Team $team): bool
    {
        if (! $user->teams->contains('id', $team->id)) {
            return false;
        }

        return $user->canPerformInTeam($team->id, 'billing.view');
    }

    /**
     * Determine whether the user can manage billing and payment proofs.
     */
    public function manageBilling(User $user, Team $team): bool
    {
        if (! $user->teams->contains('id', $team->id)) {
            return false;
        }

        return $user->canPerformInTeam($team->id, 'billing.manage');
    }

    /**
     * Determine whether the user can deploy applications.
     */
    public function deploy(User $user, Team $team): bool
    {
        if (! $user->teams->contains('id', $team->id)) {
            return false;
        }

        return $user->canPerformInTeam($team->id, 'deploy');
    }

    /**
     * Determine whether the user can manage custom domains.
     */
    public function manageDomains(User $user, Team $team): bool
    {
        if (! $user->teams->contains('id', $team->id)) {
            return false;
        }

        return $user->canPerformInTeam($team->id, 'domains.manage');
    }

    /**
     * Determine whether the user can access servers and consoles.
     */
    public function accessServers(User $user, Team $team): bool
    {
        if (! $user->teams->contains('id', $team->id)) {
            return false;
        }

        return $user->canPerformInTeam($team->id, 'servers.access');
    }
}
