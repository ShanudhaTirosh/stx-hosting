<?php

namespace App\Services\Commerce;

use App\Models\Commerce\PricingTier;
use App\Models\Commerce\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Session-backed cart. The session only remembers WHICH plan, term and quantity;
 * every price is read from the catalog each time the cart is shown or ordered,
 * so nothing the browser sends can change what is charged.
 *
 * To persist carts per account later, keep this public interface and swap the storage.
 */
class CartService
{
    public const KEY = 'storefront.cart';

    public const MAX_LINES = 20;

    public const MAX_QTY = 10;

    public function currency(): string
    {
        return session('storefront.currency', 'USD') === 'LKR' ? 'LKR' : 'USD';
    }

    public function count(): int
    {
        return (int) collect(session(self::KEY, []))->sum('quantity');
    }

    /** @return string  the line id */
    public function add(string $variantUuid, ?string $billingType, int $quantity = 1): string
    {
        $variant = ProductVariant::where('uuid', $variantUuid)->where('is_active', true)->first();
        abort_unless($variant && $variant->product?->is_active, 404);

        $tier = $this->tierFor($variant, $billingType);
        abort_unless($tier, 422, 'This plan has no active price.');

        $cart = session(self::KEY, []);
        $id = substr(sha1($variant->uuid.'|'.$tier->billing_type), 0, 12);

        if (! isset($cart[$id]) && count($cart) >= self::MAX_LINES) {
            abort(422, 'The cart is full.');
        }

        $cart[$id] = [
            'variant' => $variant->uuid,
            'billing_type' => $tier->billing_type,
            'quantity' => min(self::MAX_QTY, ($cart[$id]['quantity'] ?? 0) + max(1, $quantity)),
        ];

        session([self::KEY => $cart]);

        return $id;
    }

    public function setQuantity(string $id, int $quantity): void
    {
        $cart = session(self::KEY, []);

        if (! isset($cart[$id])) {
            return;
        }

        if ($quantity < 1) {
            unset($cart[$id]);
        } else {
            $cart[$id]['quantity'] = min(self::MAX_QTY, $quantity);
        }

        session([self::KEY => $cart]);
    }

    public function remove(string $id): void
    {
        $cart = session(self::KEY, []);
        unset($cart[$id]);
        session([self::KEY => $cart]);
    }

    public function clear(): void
    {
        session()->forget(self::KEY);
    }

    /**
     * Lines with live prices in the visitor's currency.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function lines(): Collection
    {
        $currency = $this->currency();
        $cart = session(self::KEY, []);

        if ($cart === []) {
            return collect();
        }

        $variants = ProductVariant::with(['product', 'pricingTiers'])
            ->whereIn('uuid', collect($cart)->pluck('variant')->unique())
            ->get()
            ->keyBy('uuid');

        return collect($cart)->map(function (array $row, string $id) use ($variants, $currency) {
            $variant = $variants->get($row['variant']);
            $tier = $variant?->pricingTiers->first(fn ($t) => $t->billing_type === $row['billing_type'] && $t->is_active);

            $sellable = $variant && $variant->is_active && $variant->product?->is_active && $tier;
            $unit = $sellable ? $this->unitCents($tier, $currency) : null;

            return [
                'id' => $id,
                'variant' => $variant,
                'tier' => $tier,
                'billing_type' => $row['billing_type'],
                'quantity' => (int) $row['quantity'],
                'unit_cents' => $unit,
                'line_cents' => $unit === null ? null : $unit * (int) $row['quantity'],
                // Why a line cannot be ordered, or null when it can.
                'problem' => ! $sellable
                    ? 'This plan is no longer available.'
                    : ($unit === null ? "This plan has no {$currency} price yet." : null),
            ];
        })->values();
    }

    public function totalCents(): int
    {
        return (int) $this->lines()->sum(fn ($l) => $l['line_cents'] ?? 0);
    }

    public function canCheckout(): bool
    {
        $lines = $this->lines();

        return $lines->isNotEmpty() && $lines->every(fn ($l) => $l['problem'] === null);
    }

    /** Input for OrderService::place(). */
    public function orderLines(): array
    {
        return $this->lines()->map(fn ($l) => [
            'variant' => $l['variant'],
            'billing_type' => $l['billing_type'],
            'quantity' => $l['quantity'],
        ])->all();
    }

    public function tierFor(ProductVariant $variant, ?string $billingType): ?PricingTier
    {
        $tiers = $variant->pricingTiers()->where('is_active', true)->orderBy('price_cents')->get();

        return $tiers->firstWhere('billing_type', $billingType) ?? $tiers->first();
    }

    private function unitCents(PricingTier $tier, string $currency): ?int
    {
        if ($currency === 'LKR') {
            return $tier->price_lkr_cents ? (int) $tier->price_lkr_cents : null;
        }

        return (int) $tier->price_cents;
    }
}
