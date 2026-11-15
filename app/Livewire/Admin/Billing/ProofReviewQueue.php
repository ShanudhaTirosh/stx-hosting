<?php

namespace App\Livewire\Admin\Billing;

use App\Http\Controllers\Api\Commerce\PaymentProofController;
use App\Livewire\Concerns\RequiresPlatformStaff;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\PaymentProof;
use App\Services\Commerce\PaymentReviewService;
use App\Services\Commerce\RefundService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ProofReviewQueue extends Component
{
    use RequiresPlatformStaff;
    use WithPagination;

    public ?int $selectedProofId = null;

    public string $rejectionReason = '';

    public string $resubmitReason = '';

    public string $refundReason = '';

    public ?int $refundAmount = null;

    public bool $refundRevokeEntitlements = true;

    public string $filterStatus = 'pending_review';

    public function selectProof(int $proofId): void
    {
        $this->selectedProofId = $proofId;
        $this->rejectionReason = '';
        $this->resubmitReason = '';
        $this->refundReason = '';
        $this->refundAmount = null;
    }

    public function approve(int $proofId, PaymentReviewService $reviews): void
    {
        try {
            $reviews->approve(PaymentProof::findOrFail($proofId), auth()->user());

            $this->dispatch('success', 'Payment proof approved. Invoice marked as paid and the service is unlocked.');
            $this->selectedProofId = null;
        } catch (ValidationException $e) {
            $this->dispatch('error', 'Approval failed: '.collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Approval failed: '.$e->getMessage());
        }
    }

    public function reject(int $proofId, PaymentReviewService $reviews): void
    {
        $this->validate([
            'rejectionReason' => 'required|string|min:5|max:500',
        ]);

        try {
            $reviews->reject(PaymentProof::findOrFail($proofId), auth()->user(), $this->rejectionReason);

            $this->dispatch('success', 'Payment proof rejected. Customer notified.');
            $this->selectedProofId = null;
            $this->rejectionReason = '';
        } catch (ValidationException $e) {
            $this->dispatch('error', 'Rejection failed: '.collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Rejection failed: '.$e->getMessage());
        }
    }

    public function requestResubmission(int $proofId, PaymentReviewService $reviews): void
    {
        $this->validate([
            'resubmitReason' => 'required|string|min:5|max:500',
        ]);

        try {
            $reviews->requestResubmission(PaymentProof::findOrFail($proofId), auth()->user(), $this->resubmitReason);

            $this->dispatch('success', 'New slip requested from customer. Order remains open.');
            $this->selectedProofId = null;
            $this->resubmitReason = '';
        } catch (ValidationException $e) {
            $this->dispatch('error', 'Request failed: '.collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Request failed: '.$e->getMessage());
        }
    }

    public function refundInvoice(int $invoiceId, RefundService $refunds): void
    {
        $this->validate([
            'refundReason' => 'required|string|min:5|max:500',
        ]);

        try {
            $invoice = Invoice::findOrFail($invoiceId);
            $amountCents = $this->refundAmount ? (int) ($this->refundAmount * 100) : (int) $invoice->total_cents;

            $refunds->refund(
                $invoice,
                $amountCents,
                $this->refundReason,
                auth()->user(),
                'manual',
                $this->refundRevokeEntitlements
            );

            $this->dispatch('success', 'Refund processed successfully. Customer notified and records updated.');
            $this->selectedProofId = null;
            $this->refundReason = '';
            $this->refundAmount = null;
        } catch (ValidationException $e) {
            $this->dispatch('error', 'Refund failed: '.collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Refund failed: '.$e->getMessage());
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

        $pendingCount = PaymentProof::where('status', PaymentProof::STATUS_PENDING_REVIEW)->count();
        $verifyingCount = PaymentProof::where('status', PaymentProof::STATUS_VERIFYING)->count();
        $autoVerifiedCount = PaymentProof::where('status', PaymentProof::STATUS_AUTO_VERIFIED)->count();
        $rejectedCount = PaymentProof::where('status', PaymentProof::STATUS_REJECTED)->count();

        return view('livewire.admin.billing.proof-review-queue', [
            'proofs' => $proofs,
            'selectedProof' => $selectedProof,
            'signedViewUrl' => $signedViewUrl,
            'pendingCount' => $pendingCount,
            'verifyingCount' => $verifyingCount,
            'autoVerifiedCount' => $autoVerifiedCount,
            'rejectedCount' => $rejectedCount,
        ]);
    }
}
