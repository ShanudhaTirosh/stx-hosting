<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;

/**
 * RbacService — Platform-level role resolution and limit-bypass gate.
 *
 * Three-tier hierarchy:
 *   Owner       — member of root team (id = 0) with pivot role 'owner' or 'admin'
 *                 AND isInstanceAdmin() returns true.
 *   Administrator — same as Owner in terms of root-team membership; differentiated
 *                 by pivot role ('admin' vs 'owner') and canOverrideRestrictions().
 *   Client      — every authenticated user who is NOT a platform Owner or Admin.
 *
 * IMPORTANT: shouldBypassLimits() returns true for BOTH Owner and Admin.
 *            canOverrideRestrictions() returns true for Owner ONLY.
 */
class RbacService
{
    /**
     * Platform Owner: member of root team (id=0) with 'owner' pivot role.
     * Returns true regardless of which team is currently active in session.
     */
    public static function isOwner(User $user, ?Team $team = null): bool
    {
        $rootTeam = Team::find(0);

        if (! $rootTeam) {
            return false;
        }

        $pivotRole = $user->teams()
            ->where('teams.id', 0)
            ->first()?->membership?->role;

        return $pivotRole === 'owner' && $user->isInstanceAdmin();
    }

    /**
     * Platform Administrator: member of root team (id=0) with 'admin' or 'owner' pivot role.
     * Both owners and admins are platform administrators (isInstanceAdmin() covers both).
     */
    public static function isAdmin(User $user, ?Team $team = null): bool
    {
        return $user->isInstanceAdmin();
    }

    /**
     * Platform Client: authenticated user who is NOT a platform Owner or Admin.
     * They are scoped to their own personal/organization team only.
     */
    public static function isClient(User $user, ?Team $team = null): bool
    {
        return ! $user->isInstanceAdmin();
    }

    /**
     * Owners AND Administrators bypass all client resource limits.
     * This is the critical gate: client limits must NEVER apply to platform staff.
     */
    public static function shouldBypassLimits(User $user): bool
    {
        return $user->isInstanceAdmin();
    }

    /**
     * Only Owners (root-team members with 'owner' pivot role) can override restrictions,
     * i.e. grant resources beyond plan limits or revoke client entitlements.
     */
    public static function canOverrideRestrictions(User $user): bool
    {
        $rootTeam = Team::find(0);

        if (! $rootTeam) {
            return false;
        }

        $pivotRole = $user->teams()
            ->where('teams.id', 0)
            ->first()?->membership?->role;

        return $pivotRole === 'owner';
    }

    /**
     * Generic permission check.
     * Owners and Admins have all permissions; clients have none by default.
     * Fine-grained permissions can be layered on top via the HostingLimit model.
     */
    public static function hasPermission(User $user, string $permission): bool
    {
        return $user->isInstanceAdmin();
    }
}

