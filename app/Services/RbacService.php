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
        $rootTeam = $user->teams()->where('teams.id', 0)->first();

        if (! $rootTeam) {
            return false;
        }

        $pivotRole = data_get($rootTeam, 'pivot.role');

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
     * Staff powers need two-factor sign-in (config/security.php). A staff member who has not turned it on is
     * treated as an ordinary customer until they do, so a stolen staff password alone gets an attacker nothing.
     */
    public static function twoFactorSatisfied(User $user): bool
    {
        return ! config('security.require_staff_2fa', true) || $user->two_factor_confirmed_at !== null;
    }

    /** A real staff member who is being held back only because two-factor is not on yet. */
    public static function staffNeedsTwoFactor(?User $user): bool
    {
        return $user !== null && $user->isInstanceAdmin() && ! self::twoFactorSatisfied($user);
    }

    /**
     * Platform staff (Owner or Admin): member of the root team (id=0) with an
     * 'admin' or 'owner' pivot role.
     *
     * Use this - never User::isAdmin() - whenever the question is "is this a
     * member of OUR staff?". User::isAdmin() only means "admin/owner of the
     * CURRENT team", which is true for every client in their own team.
     */
    public static function isStaff(?User $user): bool
    {
        return $user !== null && $user->isInstanceAdmin() && self::twoFactorSatisfied($user);
    }

    /**
     * Platform Owner only (root team pivot role 'owner').
     */
    public static function isPlatformOwner(?User $user): bool
    {
        return $user !== null && self::isOwner($user) && self::twoFactorSatisfied($user);
    }

    /**
     * 'owner' | 'admin' | 'client' | 'guest'.
     */
    public static function platformRole(?User $user): string
    {
        if ($user === null) {
            return 'guest';
        }

        // Staff powers need two-factor: until it is on, they are treated as a customer.
        if (! self::twoFactorSatisfied($user)) {
            return 'client';
        }

        if (self::isOwner($user)) {
            return 'owner';
        }

        return $user->isInstanceAdmin() ? 'admin' : 'client';
    }

    /**
     * Capability check against config/stx-permissions.php.
     * Unknown capabilities are denied.
     */
    public static function can(?User $user, string $capability): bool
    {
        $roles = config("stx-permissions.capabilities.{$capability}");

        if (! is_array($roles)) {
            return false;
        }

        return in_array(self::platformRole($user), $roles, true);
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
        return $user->isInstanceAdmin() && self::twoFactorSatisfied($user);
    }

    /**
     * Only Owners (root-team members with 'owner' pivot role) can override restrictions,
     * i.e. grant resources beyond plan limits or revoke client entitlements.
     */
    public static function canOverrideRestrictions(User $user): bool
    {
        $rootTeam = $user->teams()->where('teams.id', 0)->first();

        if (! $rootTeam) {
            return false;
        }

        $pivotRole = data_get($rootTeam, 'pivot.role');

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
