<?php

namespace App\Enums;

enum Role: string
{
    case VIEWER = 'viewer';
    case MEMBER = 'member';
    case BILLING = 'billing';
    case DEVELOPER = 'developer';
    case ADMIN = 'admin';
    case OWNER = 'owner';

    public function rank(): int
    {
        return match ($this) {
            self::VIEWER => 1,
            self::MEMBER => 1,
            self::BILLING => 2,
            self::DEVELOPER => 3,
            self::ADMIN => 4,
            self::OWNER => 5,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::VIEWER => 'Viewer',
            self::MEMBER => 'Member',
            self::BILLING => 'Billing',
            self::DEVELOPER => 'Developer',
            self::ADMIN => 'Admin',
            self::OWNER => 'Owner',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OWNER => 'Full administrative, financial, and structural control over the team.',
            self::ADMIN => 'Can invite members, manage services, configure domains, and deploy apps.',
            self::DEVELOPER => 'Can deploy applications, configure domains, and access servers.',
            self::BILLING => 'Can view and pay invoices, manage payment methods and deposit slips.',
            self::VIEWER, self::MEMBER => 'Read-only access to team services, status, and resources.',
        };
    }

    public function canDeploy(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::DEVELOPER], true);
    }

    public function canManageDomains(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::DEVELOPER], true);
    }

    public function canAccessServers(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::DEVELOPER], true);
    }

    public function canViewBilling(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::BILLING], true);
    }

    public function canManageBilling(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::BILLING], true);
    }

    public function canManageMembers(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN], true);
    }

    public function canDeleteTeam(): bool
    {
        return $this === self::OWNER;
    }

    public function lt(Role|string $role): bool
    {
        if (is_string($role)) {
            try {
                $role = Role::from($role);
            } catch (\ValueError $e) {
                throw new \TypeError("Invalid role: {$role}", 0, $e);
            }
        }

        return $this->rank() < $role->rank();
    }

    public function gt(Role|string $role): bool
    {
        if (is_string($role)) {
            try {
                $role = Role::from($role);
            } catch (\ValueError $e) {
                throw new \TypeError("Invalid role: {$role}", 0, $e);
            }
        }

        return $this->rank() > $role->rank();
    }

    public function lte(Role|string $role): bool
    {
        return ! $this->gt($role);
    }

    public function gte(Role|string $role): bool
    {
        return ! $this->lt($role);
    }
}
