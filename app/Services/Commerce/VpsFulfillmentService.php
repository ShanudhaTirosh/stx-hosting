<?php

namespace App\Services\Commerce;

use App\Models\Commerce\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * VPS orders are delivered by hand. This keeps track of which paid orders still owe the customer a server,
 * how long they have been waiting, and when the order is finished.
 *
 * Order status: paid (waiting for a server) -> provisioning (some delivered) -> completed (all delivered).
 */
class VpsFulfillmentService
{
    /** Delivery promise shown to customers: 24 to 72 hours after payment approval. */
    public const SLA_HOURS = 72;

    public const ALERT_LEVELS = [24, 48, 72];

    /** Paid orders that still owe at least one VPS. */
    public static function pendingQuery(): Builder
    {
        $ordered = '(select coalesce(sum(oi.quantity), 0) from order_items oi
            join product_variants pv on pv.id = oi.product_variant_id
            join products p on p.id = pv.product_id
            where oi.order_id = orders.id and p.category = ?)';

        $delivered = "(select count(*) from service_instances si where si.order_id = orders.id and si.service_type = 'vps')";

        return Order::query()
            ->whereIn('status', ['paid', 'provisioning'])
            ->whereRaw("{$ordered} > {$delivered}", ['vps']);
    }

    public static function unitsOrdered(Order $order): int
    {
        return (int) $order->items()
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.category', 'vps')
            ->sum('order_items.quantity');
    }

    public static function unitsDelivered(Order $order): int
    {
        return (int) $order->services()->where('service_type', 'vps')->count();
    }

    public static function paidAt(Order $order): \Illuminate\Support\Carbon
    {
        return $order->invoice?->paid_at ?? $order->updated_at ?? now();
    }

    public static function dueAt(Order $order): \Illuminate\Support\Carbon
    {
        return self::paidAt($order)->copy()->addHours(self::SLA_HOURS);
    }

    public static function hoursWaiting(Order $order): int
    {
        return (int) self::paidAt($order)->diffInHours(now(), false);
    }

    /** Highest alert level (24, 48, 72) the order has reached, or 0. */
    public static function alertLevel(Order $order): int
    {
        $hours = self::hoursWaiting($order);
        $level = 0;

        foreach (self::ALERT_LEVELS as $threshold) {
            if ($hours >= $threshold) {
                $level = $threshold;
            }
        }

        return $level;
    }

    /** After delivering one unit: provisioning while some are still owed, completed when all are delivered. */
    public static function refreshStatus(Order $order): void
    {
        $order->update([
            'status' => self::unitsDelivered($order) >= self::unitsOrdered($order) ? 'completed' : 'provisioning',
        ]);
    }
}
