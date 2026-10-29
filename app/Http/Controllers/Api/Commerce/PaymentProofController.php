<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\CommerceAuditLog;
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
    public function store(string $invoiceUuid, Request $request): JsonResponse
    {
        $invoice = Invoice::where('uuid', $invoiceUuid)->first();
        if (! $invoice) {
            return response()->json(['error' => 'Invoice not found.'], 404);
        }

        if ($invoice->status === 'paid') {
            return response()->json(['error' => 'Invoice is already marked as paid.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'proof_file' => 'required|file|max:5120', // Max 5 MB
            'payment_method' => 'required|string|in:bank_transfer,crypto,helapay_manual',
            'transaction_reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('proof_file');
        $rawContent = file_get_contents($file->getRealPath());

        // 1. Magic-byte inspection via finfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($rawContent);

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];

        if (! array_key_exists($mime, $allowedMimes)) {
            return response()->json([
                'success' => false,
                'error' => "Invalid file format detected ({$mime}). Only PNG, JPEG, WEBP, and PDF receipts are permitted.",
            ], 422);
        }

        $ext = $allowedMimes[$mime];
        $sanitizedContent = $rawContent;

        // 2. Pure-pixel image re-encoding to purge polyglot webshells (if GD extension is available)
        if (str_starts_with($mime, 'image/') && function_exists('imagecreatefromstring')) {
            $imageResource = @imagecreatefromstring($rawContent);
            if ($imageResource === false) {
                return response()->json([
                    'success' => false,
                    'error' => 'Corrupt or unreadable image file structure.',
                ], 422);
            }

            ob_start();
            if ($ext === 'png') {
                imagepng($imageResource);
            } elseif ($ext === 'webp' && function_exists('imagewebp')) {
                imagewebp($imageResource);
            } else {
                imagejpeg($imageResource, null, 90);
            }
            $sanitizedContent = ob_get_clean();
            imagedestroy($imageResource);
        }

        // 3. Generate SHA-256 hash & save to private storage
        $sha256 = hash('sha256', $sanitizedContent);
        $fileUuid = (string) Str::uuid();
        $storedFilename = "{$fileUuid}.{$ext}";
        $storagePath = "proofs/{$storedFilename}";

        Storage::disk('payment_proofs')->put($storagePath, $sanitizedContent);

        // 4. Save PaymentProof record & transition invoice status
        $proof = PaymentProof::create([
            'invoice_id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'payment_method' => $request->input('payment_method'),
            'transaction_reference' => $request->input('transaction_reference'),
            'file_path' => $storagePath,
            'file_mime' => $mime,
            'file_size_bytes' => strlen($sanitizedContent),
            'sha256_hash' => $sha256,
            'status' => 'pending_review',
        ]);

        $invoice->update(['status' => 'payment_pending']);

        CommerceAuditLog::record(
            'payment_proof.uploaded',
            PaymentProof::class,
            $proof->id,
            [
                'invoice_uuid' => $invoice->uuid,
                'payment_method' => $proof->payment_method,
                'sha256' => $sha256,
            ]
        );

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
    public function viewFile(string $uuid, Request $request): BinaryFileResponse|JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['error' => 'Invalid or expired signature.'], 403);
        }

        $proof = PaymentProof::where('uuid', $uuid)->firstOrFail();

        $disk = Storage::disk('payment_proofs');
        if (! $disk->exists($proof->file_path)) {
            return response()->json(['error' => 'Proof file not found on storage disk.'], 404);
        }

        return response()->file($disk->path($proof->file_path), [
            'Content-Type' => $proof->file_mime,
            'Content-Disposition' => 'inline; filename="receipt-' . $proof->uuid . '.' . pathinfo($proof->file_path, PATHINFO_EXTENSION) . '"',
        ]);
    }

    /**
     * Generate temporary signed URL for reviewing the proof.
     */
    public static function getSignedViewUrl(PaymentProof $proof, int $expirationMinutes = 30): string
    {
        return URL::temporarySignedRoute(
            'api.commercial.proof.view',
            now()->addMinutes($expirationMinutes),
            ['uuid' => $proof->uuid]
        );
    }
}

