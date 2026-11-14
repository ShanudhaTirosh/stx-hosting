<?php

namespace App\Services\Commerce;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\OrderItem;
use App\Models\Commerce\PaymentProof;
use App\Models\Commerce\PaymentTransaction;
use App\Models\HostingEntitlement;
use App\Models\User;
use App\Notifications\Commerce\PaymentApprovedNotification;
use App\Notifications\Commerce\PaymentRejectedNotification;
use App\Services\Notifications\StaffEvents;
use App\Support\Money;
use App\Services\RbacService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Staff decision on a payment proof. Every rule lives here so the Livewire
 * queue, the API and any future tool behave identically:
 *
 *  - only staff with the payments.verify capability may decide
 *  - only a proof still in pending_review can be decided, exactly once (row locks)
 *  - approve: invoice paid, order paid, one transaction, entitlements granted
 *  - reject: a reason is required, invoice back to unpaid, order back to pending,
 *    so the client can upload a corrected proof
 */
class PaymentReviewService
{
    private const TERM_DAYS = [
        '30-day' => 30, '50-day' => 50, 'monthly' => 30,
        'quarterly' => 90, 'semi_annual' => 180, 'annual' => 365,
    ];

    private const ENTITLEMENT_TYPE = [
        'vps' => 'vps',
        'database' => 'managed_database',
        'email_hosting' => 'email_hosting',
        'mail_hosting' => 'email_hosting',
    ];

    public function approve(PaymentProof $proof, User $reviewer): Invoice
    {
        $this->authorize($reviewer);

        $invoice = DB::transaction(function () use ($proof, $reviewer) {
            $proof = PaymentProof::lockForUpdate()->findOrFail($proof->id);
            $invoice = Invoice::with('order.items.pricingTier.productVariant.product')->lockForUpdate()->findOrFail($proof->invoice_id);

            $this->assertReviewable($proof);

            if ($invoice->status === 'paid') {
                throw ValidationException::withMessages(['proof' => 'This invoice is already paid.']);
            }

            PaymentTransaction::create([
                'invoice_id' => $invoice->id,
                'payment_proof_id' => $proof->id,
                'gateway' => $proof->payment_method,
                'transaction_id' => $proof->transaction_reference ?: ('MANUAL-'.strtoupper(Str::random(10))),
                'amount_cents' => $invoice->total_cents,
                'currency' => $invoice->currency,
                'status' => 'successful',
                'gateway_payload' => [
                    'reviewed_by' => $reviewer->id,
                    'approved_at' => now()->toIso8601String(),
                ],
            ]);

            $invoice->update(['status' => 'paid', 'paid_at' => now()]);
            $invoice->order?->update(['status' => 'paid']);

            // Only a VPS needs hand delivery. An order made of apps and databases is finished once paid,
            // because the entitlement below is what unlocks them. An order with a VPS stays 'paid' until delivered.
            $items = $invoice->order?->items ?? collect();
            if ($items->isNotEmpty() && $items->every(fn ($i) => $i->pricingTier?->productVariant?->product?->category !== 'vps')) {
                $invoice->order->update(['status' => 'completed']);
            }

            $proof->update([
                'status' => 'approved',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            foreach ($invoice->order?->items ?? [] as $item) {
                $this->grantEntitlement($item, $invoice, $reviewer);
            }

            \App\Actions\Commerce\ReactivateServiceAction::run($invoice, $reviewer);

            $this->audit($reviewer, $invoice, 'payment_proof.approved', $proof, [
                'invoice_number' => $invoice->invoice_number,
                'amount_cents' => $invoice->total_cents,
            ]);

            return $invoice;
        });

        StaffEvents::notify(StaffEvents::PROOF_REVIEWED, 'Payment approved: '.$invoice->invoice_number, "{$reviewer->name} approved it.", [
            'Amount' => Money::format((int) $invoice->total_cents, $invoice->currency),
        ], 'success', 'approved:'.$proof->id);

        try {
            $invoice->user?->notify(new PaymentApprovedNotification($invoice->order, $invoice));
        } catch (\Throwable $e) {
            logger()->warning("Could not send PaymentApprovedNotification: {$e->getMessage()}");
        }

        return $invoice;
    }

    public function reject(PaymentProof $proof, User $reviewer, string $reason): Invoice
    {
        $this->authorize($reviewer);

        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['rejectionReason' => 'Please give the customer a reason (at least 5 characters).']);
        }
        $reason = Str::limit($reason, 500, '');

        $invoice = DB::transaction(function () use ($proof, $reviewer, $reason) {
            $proof = PaymentProof::lockForUpdate()->findOrFail($proof->id);
            $invoice = Invoice::with('order')->lockForUpdate()->findOrFail($proof->invoice_id);

            $this->assertReviewable($proof);

            $proof->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'admin_notes' => $reason,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            if ($invoice->status !== 'paid') {
                $invoice->update(['status' => 'unpaid']);
                $invoice->order?->update(['status' => 'pending']);
            }

            $this->audit($reviewer, $invoice, 'payment_proof.rejected', $proof, [
                'invoice_id' => $invoice->id,
                'reason' => $reason,
            ]);

            return $invoice;
        });

        StaffEvents::notify(StaffEvents::PROOF_REVIEWED, 'Payment rejected: '.$invoice->invoice_number, "{$reviewer->name} rejected it.", [
            'Reason' => $reason,
        ], 'error', 'rejected:'.$proof->id);

        try {
            $invoice->user?->notify(new PaymentRejectedNotification($invoice, $reason));
        } catch (\Throwable $e) {
            logger()->warning("Could not send PaymentRejectedNotification: {$e->getMessage()}");
        }

        return $invoice;
    }

    public function autoApprove(PaymentProof $proof, string $basis = 'ai_confidence'): Invoice
    {
        $systemUser = User::firstWhere('id', 0) ?? User::where('email', 'like', '%admin%')->first() ?? $proof->user;

        $invoice = DB::transaction(function () use ($proof, $systemUser, $basis) {
            $proof = PaymentProof::lockForUpdate()->findOrFail($proof->id);
            $invoice = Invoice::with('order.items.pricingTier.productVariant.product')->lockForUpdate()->findOrFail($proof->invoice_id);

            if ($invoice->status === 'paid') {
                return $invoice;
            }

            PaymentTransaction::create([
                'invoice_id' => $invoice->id,
                'payment_proof_id' => $proof->id,
                'gateway' => $proof->payment_method ?: 'bank_transfer',
                'transaction_id' => $proof->transaction_reference ?: ('AUTO-'.strtoupper(Str::random(10))),
                'amount_cents' => $invoice->total_cents,
                'currency' => $invoice->currency,
                'status' => 'successful',
                'gateway_payload' => [
                    'verification_basis' => $basis,
                    'confidence_score' => $proof->confidence_score,
                    'auto_approved_at' => now()->toIso8601String(),
                ],
            ]);

            $invoice->update(['status' => 'paid', 'paid_at' => now()]);
            $invoice->order?->update(['status' => 'paid']);

            $items = $invoice->order?->items ?? collect();
            if ($items->isNotEmpty() && $items->every(fn ($i) => $i->pricingTier?->productVariant?->product?->category !== 'vps')) {
                $invoice->order->update(['status' => 'completed']);
            }

            $proof->update([
                'status' => PaymentProof::STATUS_AUTO_VERIFIED,
                'verification_basis' => $basis,
                'reviewed_by_user_id' => $systemUser?->id,
                'reviewed_at' => now(),
            ]);

            foreach ($invoice->order?->items ?? [] as $item) {
                $this->grantEntitlement($item, $invoice, $systemUser);
            }

            if ($systemUser) {
                \App\Actions\Commerce\ReactivateServiceAction::run($invoice, $systemUser);
            }

            $this->audit($systemUser, $invoice, 'payment_proof.auto_approved', $proof, [
                'invoice_number' => $invoice->invoice_number,
                'amount_cents' => $invoice->total_cents,
                'basis' => $basis,
                'confidence' => $proof->confidence_score,
            ]);

            return $invoice;
        });

        StaffEvents::notify(
            StaffEvents::PROOF_REVIEWED,
            'Payment auto-approved: '.$invoice->invoice_number,
            "AI verification ({$basis}) auto-approved payment.",
            [
                'Amount' => Money::format((int) $invoice->total_cents, $invoice->currency),
                'Confidence' => $proof->confidencePercent().'%',
            ],
            'success',
            'auto_approved:'.$proof->id
        );

        try {
            $invoice->user?->notify(new PaymentApprovedNotification($invoice->order, $invoice));
        } catch (\Throwable $e) {
            logger()->warning("Could not send PaymentApprovedNotification: {$e->getMessage()}");
        }

        return $invoice;
    }

    public function requestResubmission(PaymentProof $proof, User $reviewer, string $reason): Invoice
    {
        $this->authorize($reviewer);

        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['rejectionReason' => 'Please provide a clear reason for resubmission (at least 5 characters).']);
        }
        $reason = Str::limit($reason, 500, '');

        $invoice = DB::transaction(function () use ($proof, $reviewer, $reason) {
            $proof = PaymentProof::lockForUpdate()->findOrFail($proof->id);
            $invoice = Invoice::with('order')->lockForUpdate()->findOrFail($proof->invoice_id);

            $proof->update([
                'status' => PaymentProof::STATUS_RESUBMIT,
                'rejection_reason' => $reason,
                'admin_notes' => 'Resubmission requested: '.$reason,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            if ($invoice->status !== 'paid') {
                $invoice->update(['status' => 'unpaid']);
                $invoice->order?->update(['status' => 'pending']);
            }

            $this->audit($reviewer, $invoice, 'payment_proof.resubmission_requested', $proof, [
                'invoice_id' => $invoice->id,
                'reason' => $reason,
            ]);

            return $invoice;
        });

        StaffEvents::notify(
            StaffEvents::PROOF_REVIEWED,
            'New slip requested: '.$invoice->invoice_number,
            "{$reviewer->name} asked customer to re-upload proof.",
            ['Reason' => $reason],
            'warning',
            'resubmit:'.$proof->id
        );

        try {
            $invoice->user?->notify(new PaymentRejectedNotification($invoice, "Please re-upload a clearer payment slip: {$reason}"));
        } catch (\Throwable $e) {
            logger()->warning("Could not send resubmission notification: {$e->getMessage()}");
        }

        return $invoice;
    }

    private function authorize(User $reviewer): void
    {
        abort_unless(RbacService::can($reviewer, 'payments.verify'), 403, 'Only platform staff can verify payments.');
    }

    private function assertReviewable(PaymentProof $proof): void
    {
        if ($proof->status !== 'pending_review') {
            throw ValidationException::withMessages(['proof' => 'This proof has already been reviewed.']);
        }
    }

    /** One entitlement per paid order item; safe to call twice. */
    private function grantEntitlement(OrderItem $item, Invoice $invoice, User $reviewer): void
    {
        $exists = HostingEntitlement::where('team_id', $invoice->team_id)
            ->where('metadata->order_item_uuid', $item->uuid)
            ->exists();

        if ($exists) {
            return;
        }

        $tier = $item->pricingTier;
        $category = (string) ($tier?->productVariant?->product?->category ?? '');
        $days = self::TERM_DAYS[$tier?->billing_type ?? ''] ?? 30;

        $variant = $tier?->productVariant;
        $qty = max(1, (int) $item->quantity);
        // A VPS runs on its own machine; only apps, bots, sites and databases share our nodes and get capped.
        $specs = ($variant && $category !== 'vps') ? [
            'vcpu' => (float) $variant->vcpu * $qty,
            'ram_mb' => (int) $variant->ram_mb * $qty,
            'disk_gb' => (int) $variant->disk_gb * $qty,
            'bandwidth_gb' => (int) $variant->bandwidth_gb * $qty,
        ] : null;

        HostingEntitlement::create([
            'team_id' => $invoice->team_id,
            'product_type' => self::ENTITLEMENT_TYPE[$category] ?? 'app_hosting',
            'status' => 'active',
            'granted_by' => $reviewer->id,
            'expires_at' => now()->addDays($days * max(1, (int) $item->quantity)),
            'metadata' => [
                'order_uuid' => $invoice->order?->uuid,
                'order_item_uuid' => $item->uuid,
                'invoice_uuid' => $invoice->uuid,
                'category' => $category,
                'specs' => $specs,
            ],
        ]);

        if ($category === 'email_hosting' || $category === 'mail_hosting') {
            $planSlug = $variant?->slug ?? 'mail-basic';
            $mailPlan = \App\Models\Commerce\MailPlan::where('slug', $planSlug)->first()
                ?? \App\Models\Commerce\MailPlan::firstWhere('slug', 'mail-basic');

            $storageMb = data_get($variant?->specs_json, 'storage_mb', $mailPlan?->storage_mb ?? 1024);
            $maxMailboxes = data_get($variant?->specs_json, 'max_mailboxes', $mailPlan?->max_mailboxes ?? 5);
            $maxDomains = data_get($variant?->specs_json, 'max_domains', $planSlug === 'mail-business' ? 3 : ($planSlug === 'mail-pro' ? 2 : 1));

            $existingSub = \App\Models\Commerce\MailSubscription::where('team_id', $invoice->team_id)->first();
            $expiresAt = now()->addDays($days * max(1, (int) $item->quantity));

            if ($existingSub) {
                $newRenewsAt = ($existingSub->renews_at && $existingSub->renews_at->isFuture())
                    ? $existingSub->renews_at->addDays($days * max(1, (int) $item->quantity))
                    : $expiresAt;

                $existingSub->update([
                    'plan_id' => $mailPlan?->id ?? $existingSub->plan_id,
                    'status' => 'active',
                    'total_storage_mb' => max($existingSub->total_storage_mb, (int) $storageMb),
                    'max_mailboxes' => max($existingSub->max_mailboxes, (int) $maxMailboxes),
                    'max_domains' => max($existingSub->max_domains, (int) $maxDomains),
                    'renews_at' => $newRenewsAt,
                ]);
            } else {
                \App\Models\Commerce\MailSubscription::create([
                    'team_id' => $invoice->team_id,
                    'user_id' => $invoice->user_id,
                    'plan_id' => $mailPlan?->id,
                    'status' => 'active',
                    'total_storage_mb' => (int) $storageMb,
                    'max_mailboxes' => (int) $maxMailboxes,
                    'max_domains' => (int) $maxDomains,
                    'active_addons' => [],
                    'renews_at' => $expiresAt,
                ]);
            }
        }
    }

    private function audit(User $reviewer, Invoice $invoice, string $action, PaymentProof $proof, array $payload): void
    {
        CommerceAuditLog::create([
            'user_id' => $reviewer->id,
            'team_id' => $invoice->team_id,
            'action' => $action,
            'target_type' => PaymentProof::class,
            'target_id' => $proof->id,
            'payload' => $payload,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
