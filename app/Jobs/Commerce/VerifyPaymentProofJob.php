<?php

namespace App\Jobs\Commerce;

use App\Models\Commerce\CommercialSetting;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\PaymentProof;
use App\Notifications\Commerce\PaymentApprovedNotification;
use App\Notifications\Commerce\PaymentRejectedNotification;
use App\Notifications\Commerce\StaffProofSubmittedNotification;
use App\Services\Commerce\PaymentReviewService;
use App\Services\Commerce\PayVerifyClient;
use App\Services\Commerce\PdfRasterizerService;
use App\Services\Notifications\StaffEvents;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VerifyPaymentProofJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public int $proofId
    ) {}

    public function handle(
        PayVerifyClient $payverify,
        PaymentReviewService $reviewService,
        PdfRasterizerService $rasterizer
    ): void {
        $proof = PaymentProof::with(['invoice.order.items.pricingTier.productVariant.product', 'invoice.user', 'user'])->find($this->proofId);

        if (! $proof) {
            Log::warning("VerifyPaymentProofJob: PaymentProof #{$this->proofId} not found.");

            return;
        }

        // Only process proofs currently in verifying state
        if (! in_array($proof->status, [PaymentProof::STATUS_VERIFYING, PaymentProof::STATUS_PENDING_REVIEW], true)) {
            Log::info("VerifyPaymentProofJob: Proof #{$proof->id} is already in '{$proof->status}' state, skipping.");

            return;
        }

        $invoice = $proof->invoice;
        if (! $invoice) {
            Log::error("VerifyPaymentProofJob: Invoice missing for proof #{$proof->id}.");

            return;
        }

        // 1. Load file bytes from storage disk
        $diskName = (string) config('commerce.proof_disk', 'payment_proofs');
        $disk = Storage::disk($diskName);

        if (! $disk->exists($proof->file_path)) {
            Log::error("VerifyPaymentProofJob: Proof file {$proof->file_path} not found on disk {$diskName}.");
            $proof->update(['status' => PaymentProof::STATUS_PENDING_REVIEW]);

            return;
        }

        $rawBytes = $disk->get($proof->file_path);
        $mime = $proof->file_mime;

        // 2. If PDF, ensure we have rasterized PNG bytes for visual AI model
        $reviewBytes = $rawBytes;
        $reviewMime = $mime;

        if ($mime === 'application/pdf') {
            $fullPath = $disk->path($proof->file_path);
            $pngBytes = $rasterizer->rasterize($fullPath, 200);
            if ($pngBytes) {
                $reviewBytes = $pngBytes;
                $reviewMime = 'image/png';
            }
        }

        // 3. Call PayVerify External API
        $user = $proof->user;
        $customerData = [
            'id' => (string) ($user?->id ?? ''),
            'name' => $user?->name ?? 'Customer',
            'email' => $user?->email ?? '',
            'phone' => (string) ($user?->phone ?? ''),
        ];

        $paymentData = [
            'reference' => $proof->transaction_reference ?: $invoice->payment_reference,
            'channel' => 'bank_transfer',
            'bank_name' => $proof->payment_method,
        ];

        $result = $payverify->uploadAndReview(
            invoiceNo: $invoice->invoice_number,
            amountMinor: (int) $invoice->total_cents,
            base64Proof: base64_encode($reviewBytes),
            mime: $reviewMime,
            filename: basename($proof->file_path),
            customer: $customerData,
            payment: $paymentData
        );

        Log::info("VerifyPaymentProofJob: PayVerify result for invoice {$invoice->invoice_number}:", [
            'is_approved' => $result['is_approved'] ?? false,
            'status' => $result['status'] ?? 'unknown',
            'verdict' => $result['verdict'] ?? [],
        ]);

        // 4. Extract verification details
        $verdict = $result['verdict'] ?? [];
        $extraction = $result['extraction'] ?? [];
        $forensics = $result['forensics'] ?? [];

        $confidenceScore = isset($verdict['confidence_score']) ? (float) $verdict['confidence_score'] : null;
        $extractedAmount = isset($extraction['amount_minor']) ? (int) $extraction['amount_minor'] : null;
        $extractedRef = isset($extraction['reference']) ? (string) $extraction['reference'] : null;
        $discrepancies = $verdict['discrepancies'] ?? [];
        $dhash = $forensics['dhash'] ?? $proof->dhash;

        $proofUpdates = [
            'confidence_score' => $confidenceScore,
            'extracted_amount_cents' => $extractedAmount,
            'extracted_reference' => $extractedRef,
            'dhash' => $dhash,
            'ai_analysis' => $result,
            'discrepancies' => $discrepancies,
        ];

        if (isset($extraction['transaction_time']) && $extraction['transaction_time']) {
            try {
                $proofUpdates['extracted_datetime'] = \Carbon\Carbon::parse($extraction['transaction_time']);
            } catch (\Throwable) {
                // Ignore unparseable datetime
            }
        }

        // 5. Evaluate Decision Thresholds
        $autoApproveEnabled = (bool) CommercialSetting::get('payverify_auto_approve_enabled', config('commerce.payverify.auto_approve_enabled', true));
        $autoApproveThreshold = (float) CommercialSetting::get('payverify_auto_approve_min_confidence', config('commerce.payverify.auto_approve_min_confidence', 0.90));
        $manualReviewThreshold = (float) CommercialSetting::get('payverify_manual_review_min_confidence', config('commerce.payverify.manual_review_min_confidence', 0.60));

        $isBankVerified = ! empty($result['is_approved']) || ($result['status'] ?? '') === 'APPROVED';
        $hasDuplicate = ! empty($forensics['duplicate_detected']) || in_array('duplicate_proof', $discrepancies, true);
        $hasWrongRecipient = in_array('wrong_recipient', $discrepancies, true);
        $hasAmountMismatch = in_array('amount_mismatch', $discrepancies, true) || ($extractedAmount !== null && $extractedAmount < $invoice->total_cents);

        // CASE 1: BANK MATCH (Deterministic) OR HIGH-CONFIDENCE AI AUTO-APPROVAL
        $canAutoApprove = $isBankVerified || (
            $autoApproveEnabled &&
            $confidenceScore !== null &&
            $confidenceScore >= $autoApproveThreshold &&
            ! $hasDuplicate &&
            ! $hasWrongRecipient &&
            ! $hasAmountMismatch &&
            empty($discrepancies)
        );

        if ($canAutoApprove) {
            $proofUpdates['status'] = PaymentProof::STATUS_AUTO_VERIFIED;
            $proofUpdates['verification_basis'] = $isBankVerified ? 'bank_match' : 'ai_confidence';
            $proof->update($proofUpdates);

            try {
                $reviewService->autoApprove($proof, $proofUpdates['verification_basis']);
                Log::info("VerifyPaymentProofJob: Proof #{$proof->id} successfully auto-approved.");
            } catch (\Throwable $e) {
                Log::error("VerifyPaymentProofJob: Auto-approval failed: {$e->getMessage()}");
                $proof->update(['status' => PaymentProof::STATUS_PENDING_REVIEW]);
            }

            return;
        }

        // CASE 2: HARD REJECTION (Fraud, duplicate, wrong recipient, or low confidence)
        $shouldReject = $hasDuplicate ||
            $hasWrongRecipient ||
            ($confidenceScore !== null && $confidenceScore < $manualReviewThreshold) ||
            ($hasAmountMismatch && $confidenceScore !== null && $confidenceScore >= 0.80);

        if ($shouldReject) {
            $rejectionReason = 'Payment verification could not be completed.';
            if ($hasDuplicate) {
                $rejectionReason = 'This receipt was already submitted for another invoice. Please upload the valid slip for this payment.';
            } elseif ($hasWrongRecipient) {
                $rejectionReason = 'The payment slip indicates transfer to an unrecognized receiving bank account.';
            } elseif ($hasAmountMismatch) {
                $formattedRead = $extractedAmount ? Money::format($extractedAmount, $invoice->currency) : 'lower amount';
                $rejectionReason = "Payment amount mismatch: invoice requires " . Money::format($invoice->total_cents, $invoice->currency) . ", but slip shows {$formattedRead}.";
            } elseif ($confidenceScore !== null && $confidenceScore < $manualReviewThreshold) {
                $rejectionReason = 'The uploaded receipt image is blurry, cropped, or unreadable. Please upload a clear photo or PDF.';
            }

            $proofUpdates['status'] = PaymentProof::STATUS_REJECTED;
            $proofUpdates['rejection_reason'] = $rejectionReason;
            $proofUpdates['admin_notes'] = $rejectionReason;
            $proof->update($proofUpdates);

            $invoice->update(['status' => 'unpaid']);
            $invoice->order?->update(['status' => 'pending']);

            try {
                $invoice->user?->notify(new PaymentRejectedNotification($invoice, $rejectionReason));
            } catch (\Throwable $e) {
                Log::warning("VerifyPaymentProofJob: Could not notify user of rejection: {$e->getMessage()}");
            }

            return;
        }

        // CASE 3: ESCALATE TO MANUAL REVIEW QUEUE
        $proofUpdates['status'] = PaymentProof::STATUS_PENDING_REVIEW;
        $proof->update($proofUpdates);

        StaffEvents::notify(
            StaffEvents::PROOF_UPLOADED,
            'Payment proof needs review: ' . $invoice->invoice_number,
            "AI scored {$proof->confidencePercent()}% confidence: " . ($verdict['summary'] ?? 'Manual check required.'),
            [
                'Invoice' => $invoice->invoice_number,
                'Amount' => Money::format((int) $invoice->total_cents, $invoice->currency),
                'Confidence' => $proof->confidencePercent() . '%',
                'Signals' => implode(', ', $discrepancies) ?: 'None',
            ],
            'warning',
            'review:' . $proof->id
        );
    }
}

