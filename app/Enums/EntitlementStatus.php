<?php

namespace App\Enums;

/**
 * EntitlementStatus — Lifecycle states for a HostingEntitlement.
 */
enum EntitlementStatus: string
{
    case ACTIVE    = 'active';
    case SUSPENDED = 'suspended';
    case PAST_DUE  = 'past_due';
    case EXPIRED   = 'expired';
    case CANCELED  = 'canceled';
    case REVOKED   = 'revoked';
    case PENDING   = 'pending';

    /**
     * Statuses that grant active access to deployment capabilities.
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Statuses that allow read access but block new deployments.
     */
    public function isGracePeriod(): bool
    {
        return $this === self::PAST_DUE;
    }

    /**
     * Statuses that fully revoke access.
     */
    public function isRevoked(): bool
    {
        return in_array($this, [self::SUSPENDED, self::EXPIRED, self::CANCELED, self::REVOKED]);
    }
}

