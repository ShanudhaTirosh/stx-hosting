<?php

namespace App\Services\Commerce;

use App\Actions\Application\StopApplication;
use App\Actions\Database\StopDatabase;
use App\Actions\Service\StopService;
use App\Enums\EntitlementStatus;
use App\Models\Application;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\Order;
use App\Models\Commerce\PaymentTransaction;
use App\Models\Commerce\ServiceInstance;
use App\Models\HostingEntitlement;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Commerce\PaymentRefundedNotification;
use App\Services\Backups\DatabaseCatalog;
use App\Services\Notifications\StaffEvents;
use App\Services\RbacService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefundService
{
    /**
     * Process a refund for a paid invoice.
     */
    public function refund(
        Invoice $invoice,
        int $amountCents,
        string $reason,
        User $processor,
        string $refundMethod = 'manual',
        bool $revokeEntitlements = true
    ): Invoice {
        $this->authorize($processor);

        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'Please provide a refund reason (at least 5 characters).']);
        }

        if ($amountCents <= 0 || $amountCents > $invoice->total_cents) {
            throw ValidationException::withMessages(['amount' => 'Invalid refund amount. Must be between 1 and invoice total.']);
        }

        $invoice = DB::transaction(function () use ($invoice, $amountCents, $reason, $processor, $refundMethod, $revokeEntitlements) {
            $invoice = Invoice::with(['order', 'team'])->lockForUpdate()->findOrFail($invoice->id);

            if (! in_array($invoice->status, ['paid', 'partially_refunded'])) {
                throw ValidationException::withMessages(['invoice' => "Cannot refund an invoice in '{$invoice->status}' status."]);
            }

            // 1. Create refund transaction
            PaymentTransaction::create([
                'invoice_id' => $invoice->id,
                'gateway' => $refundMethod,
                'transaction_id' => 'REFUND-'.strtoupper(Str::random(10)),
                'amount_cents' => -$amountCents,
                'currency' => $invoice->currency,
                'status' => 'refunded',
                'gateway_payload' => [
                    'reason' => $reason,
                    'refunded_by_user_id' => $processor->id,
                    'refunded_at' => now()->toIso8601String(),
                ],
            ]);

            // 2. Update Invoice and Order status
            $isFull = ($amountCents >= $invoice->total_cents);
            $invoice->update([
                'status' => $isFull ? 'refunded' : 'partially_refunded',
            ]);

            if ($invoice->order) {
                $invoice->order->update([
                    'status' => $isFull ? 'refunded' : 'processing',
                ]);
            }

            // 3. Revoke entitlements if requested
            if ($revokeEntitlements && $invoice->team_id) {
                $entitlements = HostingEntitlement::where('team_id', $invoice->team_id)
                    ->where(function ($q) use ($invoice) {
                        $q->where('metadata->invoice_uuid', $invoice->uuid)
                            ->orWhere('metadata->order_uuid', $invoice->order?->uuid);
                    })
                    ->get();

                foreach ($entitlements as $ent) {
                    $ent->update(['status' => EntitlementStatus::REVOKED->value]);
                }

                // If full refund, stop running workloads
                if ($isFull) {
                    $applications = Application::whereHas('environment.project', fn ($q) => $q->where('team_id', $invoice->team_id))->get();
                    foreach ($applications as $app) {
                        if (str_starts_with((string) $app->status, 'running')) {
                            try {
                                StopApplication::run($app);
                            } catch (\Throwable $e) {
                                Log::warning("Failed to stop application {$app->uuid} during refund revocation: {$e->getMessage()}");
                            }
                        }
                    }

                    $databases = DatabaseCatalog::forTeam($invoice->team);
                    foreach ($databases as $db) {
                        if (DatabaseCatalog::isRunning($db)) {
                            try {
                                StopDatabase::run($db);
                            } catch (\Throwable $e) {
                                Log::warning("Failed to stop database {$db->uuid} during refund revocation: {$e->getMessage()}");
                            }
                        }
                    }

                    $services = Service::whereHas('environment.project', fn ($q) => $q->where('team_id', $invoice->team_id))->get();
                    foreach ($services as $svc) {
                        try {
                            StopService::run($svc);
                        } catch (\Throwable $e) {
                            Log::warning("Failed to stop service {$svc->uuid} during refund revocation: {$e->getMessage()}");
                        }
                    }

                    ServiceInstance::where('team_id', $invoice->team_id)
                        ->where('status', 'active')
                        ->update(['status' => 'suspended', 'suspended_at' => now()]);
                }
            }

            // 4. Audit Log
            CommerceAuditLog::create([
                'user_id' => $processor->id,
                'team_id' => $invoice->team_id,
                'action' => 'payment.refunded',
                'target_type' => Invoice::class,
                'target_id' => $invoice->id,
                'payload' => [
                    'invoice_number' => $invoice->invoice_number,
                    'amount_cents' => $amountCents,
                    'reason' => $reason,
                    'refund_method' => $refundMethod,
                    'revoke_entitlements' => $revokeEntitlements,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'service/RefundService',
                'created_at' => now(),
            ]);

            return $invoice;
        });

        // 5. Customer Notification
        try {
            $customer = $invoice->user ?? $invoice->team?->members()->first();
            if ($customer) {
                $customer->notify(new PaymentRefundedNotification($invoice, $amountCents, $reason));
            }
        } catch (\Throwable $e) {
            Log::warning("Could not dispatch PaymentRefundedNotification: {$e->getMessage()}");
        }

        // 6. Staff Alert
        StaffEvents::notify(
            StaffEvents::PROOF_REVIEWED,
            'Refund issued for '.$invoice->invoice_number,
            "{$processor->name} issued a refund of ".Money::format($amountCents, $invoice->currency),
            [
                'Invoice' => $invoice->invoice_number,
                'Amount' => Money::format($amountCents, $invoice->currency),
                'Reason' => $reason,
            ],
            'info',
            'refund:'.$invoice->id
        );

        return $invoice;
    }

    private function authorize(User $processor): void
    {
        abort_unless(RbacService::can($processor, 'payments.verify'), 403, 'Only platform staff can process refunds.');
    }
}

