<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * An owner-configured way for clients to pay (bank transfer, crypto, wallet...).
 * Includes account holder, account number, bank, branch, instructions, logo, and product limits.
 */
class PaymentMethod extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'details' => 'array',
        'allowed_categories' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'min_amount_cents' => 'integer',
        'max_amount_cents' => 'integer',
    ];

    public const TYPES = [
        'bank_transfer' => 'Bank transfer',
        'crypto' => 'Crypto',
        'wallet' => 'Wallet',
        'other' => 'Other',
    ];

    public const PRODUCT_CATEGORIES = [
        'vps' => 'VPS Hosting',
        'app_hosting' => 'App Hosting',
        'database' => 'Managed Databases',
        'mail_hosting' => 'Email Hosting',
        'addon' => 'Add-ons & Backups',
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

    /** Active methods scoped to currency and product category. */
    public function scopeForCheckout(Builder $query, string $currency, ?string $category = null): Builder
    {
        $q = $query->forCurrency($currency);

        if ($category) {
            $q->where(function (Builder $sub) use ($category) {
                $sub->whereNull('allowed_categories')
                    ->orWhereJsonContains('allowed_categories', $category);
            });
        }

        return $q;
    }

    public function matchesProductCategory(?string $category): bool
    {
        if (! $category || empty($this->allowed_categories)) {
            return true;
        }

        return in_array($category, $this->allowed_categories, true);
    }

    public function supportsAmount(int $cents): bool
    {
        if ($this->min_amount_cents && $cents < $this->min_amount_cents) {
            return false;
        }

        if ($this->max_amount_cents && $cents > $this->max_amount_cents) {
            return false;
        }

        return true;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function displayName(): string
    {
        return $this->label ?: ($this->bank_name ?: $this->typeLabel());
    }
}
