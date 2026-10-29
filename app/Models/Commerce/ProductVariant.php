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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(PricingTier::class);
    }
}

