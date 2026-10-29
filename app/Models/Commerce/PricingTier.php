<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingTier extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'price_cents' => 'integer',
        'price_lkr_cents' => 'integer',
        'setup_fee_cents' => 'integer',
        'is_active' => 'boolean',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }
}
