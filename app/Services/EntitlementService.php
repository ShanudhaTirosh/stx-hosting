<?php

namespace App\Services;

use App\Enums\EntitlementStatus;
use App\Models\HostingEntitlement;
use App\Models\HostingInvoice;
use App\Models\HostingPlan;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

class EntitlementService
{
    /**
     * Get all active entitlements for a team.
     */
    public static function getActiveEntitlements(Team $team): Collection
    {
        return HostingEntitlement::where('team_id', $team->id)
            ->where('status', EntitlementStatus::ACTIVE->value)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();
    }

    /**
     * Check if a team has a specific capability.
     * Capabilities: vps_deployment, app_hosting, managed_database, custom_templates, etc.
     */
    public static function hasCapability(Team $team, string $capability): bool
    {
        $user = auth()->user();
        if ($user && RbacService::shouldBypassLimits($user)) {
            return true;
        }

        $active = self::getActiveEntitlements($team);

        if ($active->isEmpty()) {
            return false;
        }

        foreach ($active as $entitlement) {
            if ($entitlement->product_type) {
                if ($entitlement->product_type === 'vps' && str_contains($capability, 'vps')) {
                    return true;
                }
                if ($entitlement->product_type === 'app_hosting' && str_contains($capability, 'app')) {
                    return true;
                }
                if ($entitlement->product_type === 'managed_database' && str_contains($capability, 'database')) {
                    return true;
                }
            }

            $product = $entitlement->product;
            if (! $product) {
                continue;
            }

            if ($product->type === 'vps' && str_contains($capability, 'vps')) {
                return true;
            }

            if ($product->type === 'app_hosting' && str_contains($capability, 'app')) {
                return true;
            }

            if ($product->type === 'managed_database' && str_contains($capability, 'database')) {
                return true;
            }

            if (is_array($product->features) && in_array($capability, $product->features)) {
                return true;
            }

            if (is_array($entitlement->metadata) && in_array($capability, $entitlement->metadata['capabilities'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Automatically trigger entitlement creation from a paid invoice.
     */
    public static function handlePaidInvoice(HostingInvoice $invoice): ?HostingEntitlement
    {
        if ($invoice->status !== 'paid' || ! $invoice->hosting_plan_id) {
            return null;
        }

        $plan = HostingPlan::find($invoice->hosting_plan_id);
        if (! $plan) {
            return null;
        }

        $expiresAt = $plan->term_days ? now()->addDays($plan->term_days) : (
            $plan->billing_interval === 'monthly' ? now()->addMonth() : (
                $plan->billing_interval === 'annual' ? now()->addYear() : null
            )
        );

        return HostingEntitlement::create([
            'team_id'            => $invoice->team_id,
            'hosting_product_id' => $plan->hosting_product_id,
            'hosting_plan_id'    => $plan->id,
            'status'             => EntitlementStatus::ACTIVE,
            'expires_at'         => $expiresAt,
        ]);
    }
}
