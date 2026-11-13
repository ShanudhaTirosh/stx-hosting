<?php

namespace App\Actions\Commerce;

use App\Enums\EntitlementStatus;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\ServiceInstance;
use App\Models\HostingEntitlement;
use App\Models\User;
use App\Notifications\Commerce\ServiceReactivatedNotification;
use App\Services\Notifications\StaffEvents;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ReactivateServiceAction
{
    use AsAction;

    public function handle(Invoice $invoice, User $reviewer): void
    {
        $team = $invoice->team;
        if (! $team) {
            return;
        }

        $customerNotes = $invoice->order?->customer_notes ?? '';
        $matchedEntitlementId = null;

        if (preg_match('/renewal:entitlement:(\d+)/', $customerNotes, $matches)) {
            $matchedEntitlementId = (int) $matches[1];
        }

        // Find entitlements to reactivate
        $query = HostingEntitlement::where('team_id', $team->id);
        if ($matchedEntitlementId) {
            $query->where('id', $matchedEntitlementId);
        } else {
            $query->whereIn('status', [EntitlementStatus::SUSPENDED->value, EntitlementStatus::EXPIRED->value]);
        }

        $suspendedEntitlements = $query->get();

        foreach ($suspendedEntitlements as $entitlement) {
            $days = 30;
            $orderItem = $invoice->order?->items()->first();
            if ($orderItem && $orderItem->pricingTier) {
                $billingType = $orderItem->pricingTier->billing_type;
                $days = match ($billingType) {
                    'quarterly' => 90,
                    'semi_annual' => 180,
                    'annual' => 365,
                    default => 30,
                };
            }

            $newExpiry = ($entitlement->expires_at && $entitlement->expires_at->isFuture())
                ? $entitlement->expires_at->addDays($days)
                : now()->addDays($days);

            $entitlement->update([
                'status' => EntitlementStatus::ACTIVE->value,
                'expires_at' => $newExpiry,
            ]);

            // Reactivate VPS instances if suspended
            ServiceInstance::where('team_id', $team->id)
                ->where('status', 'suspended')
                ->update([
                    'status' => 'active',
                    'suspended_at' => null,
                ]);

            CommerceAuditLog::create([
                'team_id' => $team->id,
                'user_id' => $reviewer->id,
                'action' => 'entitlement.reactivated',
                'target_type' => HostingEntitlement::class,
                'target_id' => $entitlement->id,
                'payload' => [
                    'invoice_uuid' => $invoice->uuid,
                    'new_expires_at' => $newExpiry->toIso8601String(),
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'action/ReactivateServiceAction',
                'created_at' => now(),
            ]);

            $customer = $invoice->user ?? $team->members()->first();
            if ($customer) {
                try {
                    $customer->notify(new ServiceReactivatedNotification(
                        ucwords(str_replace('_', ' ', (string) $entitlement->product_type))
                    ));
                } catch (\Throwable $e) {
                    Log::warning("Could not send ServiceReactivatedNotification: {$e->getMessage()}");
                }
            }

            StaffEvents::notify(
                StaffEvents::SERVICE_HEALTHY,
                'Service reactivated',
                "Reactivated service for team {$team->name} following payment of {$invoice->invoice_number}.",
                ['Team' => $team->name, 'Invoice' => $invoice->invoice_number],
                'success',
                'reactivate:'.$entitlement->id
            );
        }
    }
}

