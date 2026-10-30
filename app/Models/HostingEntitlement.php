<?php

namespace App\Models;

use App\Enums\EntitlementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * HostingEntitlement — Links a client team to a purchased product/plan.
 *
 * Created when:
 *  - An invoice is marked 'paid'
 *  - An admin manually grants access
 *
 * Revoked/expired when:
 *  - Subscription lapses (subscription expiration cron)
 *  - Admin manually suspends or revokes
 */
class HostingEntitlement extends Model
{
    use HasFactory;

    protected $table = 'hosting_entitlements';

    protected $fillable = [
        'team_id',
        'hosting_product_id',
        'hosting_plan_id',
        'status',
        'granted_by',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'team_id'            => 'integer',
        'hosting_product_id' => 'integer',
        'hosting_plan_id'    => 'integer',
        'granted_by'         => 'integer',
        'expires_at'         => 'datetime',
        'status'             => EntitlementStatus::class,
        'metadata'           => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(HostingProduct::class, 'hosting_product_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(HostingPlan::class, 'hosting_plan_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Scope: only active entitlements.
     */
    public function scopeActive($query)
    {
        return $query->where('status', EntitlementStatus::ACTIVE->value)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Is this entitlement currently granting deployment access?
     */
    public function grantsAccess(): bool
    {
        if ($this->status !== EntitlementStatus::ACTIVE) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}

