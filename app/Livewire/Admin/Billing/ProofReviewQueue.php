<?php

namespace App\Livewire\Admin\Billing;

use App\Http\Controllers\Api\Commerce\PaymentProofController;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\PaymentProof;
use App\Models\Commerce\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class ProofReviewQueue extends Component
{
    use WithPagination;

    public ?int $selectedProofId = null;
    public string $rejectionReason = '';
    public string $filterStatus = 'pending_review';

    public function selectProof(int $proofId): void
    {
        $this->selectedProofId = $proofId;
        $this->rejectionReason = '';
    }

    public function approve(int $proofId): void
    {
        try {
            DB::transaction(function () use ($proofId) {
                $proof = PaymentProof::with(['invoice.order'])->findOrFail($proofId);
                $invoice = Invoice::where('id', $proof->invoice_id)->lockForUpdate()->firstOrFail();

                if ($invoice->status === 'paid') {
                    throw new \Exception('Invoice is already marked as paid.');
                }

                // 1. Create transaction record
                PaymentTransaction::create([
                    'invoice_id' => $invoice->id,
                    'payment_proof_id' => $proof->id,
                    'gateway' => $proof->payment_method,
                    'transaction_id' => $proof->transaction_reference ?: ('MANUAL-' . strtoupper(Str::random(10))),
                    'amount_cents' => $invoice->total_cents,
                    'currency' => $invoice->currency,
                    'status' => 'successful',
                    'gateway_payload' => [
                        'reviewed_by' => auth()->id(),
                        'approved_at' => now()->toIso8601String(),
                    ],
                ]);

                // 2. Transition invoice & order
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                if ($invoice->order) {
                    $invoice->order->update(['status' => 'completed']);
                }

                // 3. Update proof status
                $proof->update([
                    'status' => 'approved',
                    'reviewed_by_user_id' => auth()->id(),
                    'reviewed_at' => now(),
                ]);

                CommerceAuditLog::create([
                    'user_id' => auth()->id(),
                    'team_id' => $invoice->team_id,
                    'action' => 'payment_proof.approved',
                    'target_type' => PaymentProof::class,
                    'target_id' => $proof->id,
                    'payload' => [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount_cents' => $invoice->total_cents,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);
            });

            $this->dispatch('success', 'Payment proof approved successfully. Invoice marked as paid.');
            $this->selectedProofId = null;
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Approval failed: ' . $e->getMessage());
        }
    }

    public function reject(int $proofId): void
    {
        $this->validate([
            'rejectionReason' => 'required|string|min:5|max:500',
        ]);

        try {
            DB::transaction(function () use ($proofId) {
                $proof = PaymentProof::with('invoice')->findOrFail($proofId);
                $invoice = Invoice::where('id', $proof->invoice_id)->lockForUpdate()->firstOrFail();

                $proof->update([
                    'status' => 'rejected',
                    'admin_notes' => $this->rejectionReason,
                    'reviewed_by_user_id' => auth()->id(),
                    'reviewed_at' => now(),
                ]);

                // Reset invoice status back to unpaid if no other approved proof exists
                $hasOtherApproved = PaymentProof::where('invoice_id', $invoice->id)
                    ->where('status', 'approved')
                    ->exists();

                if (! $hasOtherApproved) {
                    $invoice->update(['status' => 'unpaid']);
                }

                CommerceAuditLog::create([
                    'user_id' => auth()->id(),
                    'team_id' => $invoice->team_id,
                    'action' => 'payment_proof.rejected',
                    'target_type' => PaymentProof::class,
                    'target_id' => $proof->id,
                    'payload' => [
                        'invoice_id' => $invoice->id,
                        'reason' => $this->rejectionReason,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);
            });

            $this->dispatch('success', 'Payment proof rejected. Customer notified.');
            $this->selectedProofId = null;
            $this->rejectionReason = '';
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Rejection failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = PaymentProof::with(['invoice.order', 'invoice.user'])
            ->latest();

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        $proofs = $query->paginate(15);

        $selectedProof = null;
        $signedViewUrl = null;

        if ($this->selectedProofId) {
            $selectedProof = PaymentProof::with(['invoice.items', 'invoice.user'])->find($this->selectedProofId);
            if ($selectedProof) {
                $signedViewUrl = PaymentProofController::getSignedViewUrl($selectedProof);
            }
        }

        return view('livewire.admin.billing.proof-review-queue', [
            'proofs' => $proofs,
            'selectedProof' => $selectedProof,
            'signedViewUrl' => $signedViewUrl,
        ]);
    }
}
