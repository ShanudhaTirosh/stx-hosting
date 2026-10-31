<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HostingPlan — A specific pricing tier / billing interval for a HostingProduct.
 */
class HostingPlan extends Model
{
    use HasFactory;

    protected $table = 'hosting_plans';

    protected $fillable = [
        'hosting_product_id',
        'name',
        'slug',
        'price_cents',
        'billing_interval',
        'term_days',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'hosting_product_id' => 'integer',
        'price_cents' => 'integer',
        'term_days' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Valid billing intervals.
     */
    public const INTERVALS = ['monthly', 'quarterly', 'annual', 'one_time', 'fixed_term'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(HostingProduct::class, 'hosting_product_id');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(HostingEntitlement::class, 'hosting_plan_id');
    }

    public function limits(): HasMany
    {
        return $this->hasMany(HostingLimit::class, 'hosting_plan_id');
    }

    public function templatePermissions(): HasMany
    {
        return $this->hasMany(TemplatePermission::class, 'hosting_plan_id');
    }
}
