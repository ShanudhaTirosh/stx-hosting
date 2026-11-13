<?php

namespace App\Jobs\Commerce;

use App\Models\Commerce\Invoice;
use App\Models\Commerce\OrderItem;
use App\Models\HostingEntitlement;
use App\Models\User;
use App\Notifications\Commerce\UpcomingRenewalNotification;
use App\Services\Commerce\OrderService;
use App\Services\Notifications\StaffEvents;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateRenewalInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(OrderService $orderService): void
    {
        // Find entitlements expiring within 7 days that are active
        $expiring = HostingEntitlement::with('team')
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(7))
            ->get();

        $generatedCount = 0;

        foreach ($expiring as $entitlement) {
            $orderItemUuid = $entitlement->metadata['order_item_uuid'] ?? null;
            if (! $orderItemUuid) {
                continue;
            }

            $orderItem = OrderItem::with(['pricingTier.productVariant.product', 'order'])
                ->where('uuid', $orderItemUuid)
                ->first();

            if (! $orderItem || ! $orderItem->pricingTier || ! $orderItem->pricingTier->is_active) {
                continue;
            }

            $team = $entitlement->team;
            if (! $team) {
                continue;
            }

            $user = User::find($entitlement->granted_by) ?? $team->members()->first();
            if (! $user) {
                continue;
            }

            // Check if an unpaid renewal invoice has already been created for this entitlement
            $existingInvoice = Invoice::where('team_id', $team->id)
                ->whereIn('status', ['unpaid', 'payment_pending'])
                ->whereHas('order', function ($query) use ($entitlement) {
                    $query->where('customer_notes', 'LIKE', "%renewal:entitlement:{$entitlement->id}%");
                })
                ->exists();

            if ($existingInvoice) {
                continue;
            }

            try {
                $lines = [[
                    'tier' => $orderItem->pricingTier,
                    'quantity' => $orderItem->quantity ?: 1,
                    'hostname' => $orderItem->hostname,
                    'datacenter' => $orderItem->datacenter,
                    'public_ssh_key' => $orderItem->public_ssh_key,
                ]];

                $currency = $orderItem->order?->currency ?? 'USD';
                $notes = "Automated renewal for entitlement #{$entitlement->id} (renewal:entitlement:{$entitlement->id})";

                [$order, $invoice] = $orderService->place($user, $team, $lines, $currency, $notes);

                // Set invoice due date matching the expiry date
                $invoice->update(['due_at' => $entitlement->expires_at]);

                $productName = $orderItem->pricingTier->productVariant->product->name ?? 'Hosting Plan';

                try {
                    $user->notify(new UpcomingRenewalNotification($order, $invoice, $productName));
                } catch (\Throwable $e) {
                    Log::warning("Could not send UpcomingRenewalNotification for invoice {$invoice->invoice_number}: {$e->getMessage()}");
                }

                $generatedCount++;
            } catch (\Throwable $e) {
                Log::error("Failed to generate renewal invoice for entitlement #{$entitlement->id}: {$e->getMessage()}");
            }
        }

        if ($generatedCount > 0) {
            StaffEvents::notify(
                StaffEvents::ORDER_CREATED,
                'Renewal invoices generated',
                "Generated {$generatedCount} renewal invoice(s) for upcoming service expirations.",
                ['Count' => (string) $generatedCount],
                'info',
                'renewals:'.now()->toDateString()
            );
        }
    }
}

