<?php

namespace App\Livewire\Admin\Billing;

use App\Http\Controllers\Api\Commerce\PaymentProofController;
use App\Models\Commerce\PaymentProof;
use App\Livewire\Concerns\RequiresPlatformStaff;
use App\Services\Commerce\PaymentReviewService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ProofReviewQueue extends Component
{
    use RequiresPlatformStaff;

    use WithPagination;

    public ?int $selectedProofId = null;

    public string $rejectionReason = '';

    public string $filterStatus = 'pending_review';

    public function selectProof(int $proofId): void
    {
        $this->selectedProofId = $proofId;
        $this->rejectionReason = '';
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
