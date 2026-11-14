<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Services\Commerce\ProofStorageService;
use Illuminate\Validation\ValidationException;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\PaymentProof;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentProofController extends Controller
{
    /**
     * Upload and sanitize payment receipt proof for an invoice.
     */
    public function store(string $invoiceUuid, Request $request, ProofStorageService $proofs): JsonResponse
    {
        $invoice = Invoice::where('uuid', $invoiceUuid)->first();
        $user = $request->user();

        // Same 404 for "missing" and "not yours" so invoice UUIDs cannot be probed.
        if (! $invoice || ! $user || ! $user->can('uploadProof', $invoice)) {
            return response()->json(['error' => 'Invoice not found.'], 404);
        }

        if ($invoice->status === 'paid') {
            return response()->json(['error' => 'Invoice is already marked as paid.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'proof_file' => 'required|file|max:5120', // Max 5 MB
            'payment_method' => 'required|string|in:bank_transfer,crypto,wallet,other,helapay_manual',
            'transaction_reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $proof = $proofs->store(
                $request->file('proof_file'),
                $invoice,
                $user,
                $request->input('payment_method'),
                $request->input('transaction_reference'),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        $invoice->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Payment proof submitted successfully. Pending administrative verification.',
            'proof' => [
                'uuid' => $proof->uuid,
                'status' => $proof->status,
                'payment_method' => $proof->payment_method,
                'transaction_reference' => $proof->transaction_reference,
            ],
            'invoice_status' => $invoice->status,
        ], 201);
    }

    /**
     * Stream payment proof file securely using temporary signed URL.
     */
    public function viewFile(string $uuid, Request $request): \Symfony\Component\HttpFoundation\Response|JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['error' => 'Invalid or expired signature.'], 403);
        }

        $proof = PaymentProof::where('uuid', $uuid)->firstOrFail();

        $disk = Storage::disk(config('commerce.proof_disk', 'payment_proofs'));
        if (! $disk->exists($proof->file_path)) {
            return response()->json(['error' => 'Proof file not found on storage disk.'], 404);
        }

        return $disk->response($proof->file_path, 'receipt-'.$proof->uuid.'.'.pathinfo($proof->file_path, PATHINFO_EXTENSION), [
            'Content-Type' => $proof->file_mime,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * Generate temporary signed URL for reviewing the proof.
     */
    public static function getSignedViewUrl(PaymentProof $proof, int $expirationMinutes = 30): string
    {
        $supabase = app(\App\Services\Supabase\SupabaseClient::class);
        if (config('commerce.storage_driver') === 'supabase' && $supabase->isConfigured()) {
            $bucket = config('commerce.supabase_proof_bucket', 'payment-proofs');
            $signedUrl = $supabase->createSignedUrl($bucket, $proof->file_path, $expirationMinutes * 60);
            if ($signedUrl) {
                return $signedUrl;
            }
        }

        return URL::temporarySignedRoute(
            'api.commercial.proof.view',
            now()->addMinutes($expirationMinutes),
            ['uuid' => $proof->uuid]
        );
    }
}

