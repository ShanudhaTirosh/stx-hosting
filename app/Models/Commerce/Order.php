<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'subtotal_cents' => 'integer',
        'discount_cents' => 'integer',
        'tax_cents' => 'integer',
        'total_cents' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(ServiceInstance::class);
    }

    public function dueAt(): \Illuminate\Support\Carbon
    {
        return \App\Services\Commerce\VpsFulfillmentService::dueAt($this);
    }

    public function hasPendingVps(): bool
    {
        $ordered = (int) $this->items()
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.category', 'vps')
            ->sum('order_items.quantity');

        $delivered = (int) $this->services()->where('service_type', 'vps')->count();

        return in_array($this->status, ['paid', 'provisioning']) && $ordered > $delivered;
    }
}

