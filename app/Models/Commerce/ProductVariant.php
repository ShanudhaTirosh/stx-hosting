<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'specs_json' => 'array',
        'is_active' => 'boolean',
        'vcpu' => 'integer',
        'ram_mb' => 'integer',
        'disk_gb' => 'integer',
        'bandwidth_gb' => 'integer',
    ];

    public function isAvailable(): bool
    {
        return $this->is_active && ($this->stock_status ?? 'available') === 'available';
    }

    public function isSoldOut(): bool
    {
        return ($this->stock_status ?? '') === 'sold_out';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(PricingTier::class);
    }
}

