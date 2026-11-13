<?php

namespace App\Services\Commerce;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\InvoiceItem;
use App\Models\Commerce\Order;
use App\Models\Commerce\OrderItem;
use App\Models\Commerce\PricingTier;
use App\Models\Commerce\ProductVariant;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Commerce\OrderPlacedNotification;
use App\Services\Notifications\StaffEvents;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates an order and its invoice. Prices ALWAYS come from the catalog here,
 * never from anything the browser sent.
 *
 * Order status: pending -> payment_submitted -> paid -> completed
 *               (rejected proof sends it back to pending; cancelled/refunded are terminal)
 * Invoice status: unpaid -> payment_pending -> paid
 */
class OrderService
{
    /**
     * @param  array<int, array{
     *     variant?: ProductVariant,
     *     billing_type?: string,
     *     tier?: PricingTier,
     *     pricing_tier_id?: string|int,
     *     quantity?: int,
     *     hostname?: string|null,
     *     public_ssh_key?: string|null,
     *     datacenter?: string|null,
     *     config_options?: array|null
     * }>  $lines
     * @return array{0: Order, 1: Invoice}
     */
    public function place(User $user, Team $team, array $lines, string $currency, ?string $notes = null): array
    {
        $currency = strtoupper($currency) === 'LKR' ? 'LKR' : 'USD';

        if ($lines === []) {
            throw new InvalidArgumentException('An order needs at least one item.');
        }

        [$order, $invoice] = DB::transaction(function () use ($user, $team, $lines, $currency, $notes) {
            $priced = [];
            $subtotal = 0;

            foreach ($lines as $line) {
                $tier = null;
                $variant = null;

                if (isset($line['tier']) && $line['tier'] instanceof PricingTier) {
                    $tier = $line['tier'];
                    $variant = $tier->productVariant;
                } elseif (! empty($line['pricing_tier_id'])) {
                    $tierId = (string) $line['pricing_tier_id'];
                    $tier = PricingTier::with('productVariant.product')
                        ->where('uuid', $tierId)
                        ->orWhere('id', is_numeric($tierId) ? (int) $tierId : 0)
                        ->first();
                    if (! $tier) {
                        throw new InvalidArgumentException("Pricing tier '{$tierId}' was not found.");
                    }
                    $variant = $tier->productVariant;
                } elseif (isset($line['variant']) && $line['variant'] instanceof ProductVariant) {
                    $variant = $line['variant'];
                    $tier = $this->resolveTier($variant, (string) ($line['billing_type'] ?? ''));
                } else {
                    throw new InvalidArgumentException('Invalid line item: missing pricing tier or product variant.');
                }

                if (! $tier->is_active) {
                    throw new InvalidArgumentException("Pricing tier '{$tier->uuid}' is no longer active.");
                }

                if (! $variant || ! $variant->is_active || ! $variant->product?->is_active) {
                    throw new InvalidArgumentException("'".($variant?->name ?? 'Selected plan')."' is no longer available.");
                }

                $quantity = max(1, min(10, (int) ($line['quantity'] ?? 1)));
                $unit = $this->unitPrice($tier, $currency);
                $total = $unit * $quantity;

                $hostname = ! empty($line['hostname']) ? (string) $line['hostname'] : null;
                $publicSshKey = ! empty($line['public_ssh_key']) ? (string) $line['public_ssh_key'] : null;
                $datacenter = ! empty($line['datacenter']) ? (string) $line['datacenter'] : 'fsn1';
                $configOptions = isset($line['config_options']) && is_array($line['config_options']) ? $line['config_options'] : null;

                $priced[] = compact('variant', 'tier', 'quantity', 'unit', 'total', 'hostname', 'publicSshKey', 'datacenter', 'configOptions');
                $subtotal += $total;
            }

            $order = Order::create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'subtotal_cents' => $subtotal,
                'discount_cents' => 0,
                'tax_cents' => 0,
                'total_cents' => $subtotal,
                'currency' => $currency,
                'customer_notes' => $notes ? Str::limit($notes, 1000, '') : null,
            ]);

            $invoice = Invoice::create([
                'invoice_number' => 'INV-'.date('Ym').'-'.strtoupper(Str::random(6)),
                'order_id' => $order->id,
                'team_id' => $team->id,
                'user_id' => $user->id,
                'status' => 'unpaid',
                'subtotal_cents' => $subtotal,
                'tax_cents' => 0,
                'total_cents' => $subtotal,
                'currency' => $currency,
                'due_at' => now()->addDays(7),
            ]);

            foreach ($priced as $row) {
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $row['variant']->id,
                    'pricing_tier_id' => $row['tier']->id,
                    'quantity' => $row['quantity'],
                    'unit_price_cents' => $row['unit'],
                    'total_price_cents' => $row['total'],
                    'hostname' => $row['hostname'],
                    'public_ssh_key' => $row['publicSshKey'],
                    'datacenter' => $row['datacenter'],
                    'config_options' => $row['configOptions'],
                ]);

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'order_item_id' => $item->id,
                    'description' => trim(($row['variant']->product?->name ?? '').' - '.$row['variant']->name.' ('.$row['tier']->billing_type.')', ' -'),
                    'quantity' => $row['quantity'],
                    'unit_price_cents' => $row['unit'],
                    'total_price_cents' => $row['total'],
                ]);
            }

            CommerceAuditLog::create([
                'user_id' => $user->id,
                'team_id' => $team->id,
                'action' => 'order.created',
                'target_type' => Order::class,
                'target_id' => $order->id,
                'payload' => [
                    'order_uuid' => $order->uuid,
                    'invoice_uuid' => $invoice->uuid,
                    'total_cents' => $subtotal,
                    'currency' => $currency,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return [$order, $invoice];
        });

        StaffEvents::notify(StaffEvents::ORDER_CREATED, 'New order '.$invoice->invoice_number, "{$user->name} placed an order.", [
            'Total' => Money::format((int) $invoice->total_cents, $currency),
            'Items' => (string) count($lines),
        ], 'info', 'order:'.$order->id);

        try {
            $user->notify(new OrderPlacedNotification($order, $invoice));
        } catch (\Throwable $e) {
            logger()->warning("Could not send OrderPlacedNotification: {$e->getMessage()}");
        }

        return [$order, $invoice];
    }

    private function resolveTier(ProductVariant $variant, string $billingType): PricingTier
    {
        $tiers = $variant->pricingTiers()->where('is_active', true)->get();

        $tier = $tiers->firstWhere('billing_type', $billingType) ?? $tiers->first();

        if (! $tier) {
            throw new InvalidArgumentException("'{$variant->name}' has no active price and cannot be ordered.");
        }

        return $tier;
    }

    private function unitPrice(PricingTier $tier, string $currency): int
    {
        // The one-time setup fee exists in USD only, so a plan that has one cannot be sold in LKR.
        $setup = (int) $tier->setup_fee_cents;

        if ($currency === 'LKR') {
            if (! $tier->price_lkr_cents || $setup > 0) {
                throw new InvalidArgumentException('This plan is not priced in LKR yet. Please pay in USD.');
            }

            return (int) $tier->price_lkr_cents;
        }

        return (int) $tier->price_cents + $setup;
    }
}
