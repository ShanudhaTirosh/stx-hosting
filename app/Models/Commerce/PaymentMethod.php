<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * An owner-configured way for clients to pay (bank transfer, crypto, wallet...).
 * `details` is a list of {label, value} rows shown to the client on the invoice.
 */
class PaymentMethod extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'details' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const TYPES = [
        'bank_transfer' => 'Bank transfer',
        'crypto' => 'Crypto',
        'wallet' => 'Wallet',
        'other' => 'Other',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Active methods that accept this currency, in display order. */
    public function scopeForCurrency(Builder $query, string $currency): Builder
    {
        return $query->active()
            ->whereIn('currency', ['ANY', strtoupper($currency)])
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }
}
