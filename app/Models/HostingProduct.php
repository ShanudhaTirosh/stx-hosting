<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * HostingProduct — A purchasable platform offering (VPS, App Hosting, Managed DB, etc.)
 */
class HostingProduct extends Model
{
    use HasFactory;

    protected $table = 'hosting_products';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'is_active',
        'features',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'features'  => 'array',
        'metadata'  => 'array',
    ];

    /**
     * Valid product types.
     */
    public const TYPES = [
        'vps',
        'app_hosting',
        'managed_database',
        'smtp',
        'game_server',
        'storage_addon',
    ];

    public function plans(): HasMany
    {
        return $this->hasMany(HostingPlan::class, 'hosting_product_id');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(HostingEntitlement::class, 'hosting_product_id');
    }
}

