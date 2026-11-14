<?php

namespace App\Services\Commerce;

use App\Jobs\Commerce\VerifyPaymentProofJob;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\PaymentProof;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Commerce\PaymentProofReceivedNotification;
use App\Notifications\Commerce\StaffProofSubmittedNotification;
use App\Services\Notifications\StaffEvents;
use App\Services\Supabase\SupabaseClient;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Single, secure entrypoint for customer payment slips.
 * Handles validation, PDF rasterization, dHash perceptual deduplication,
 * private encrypted/Supabase storage, and automated AI verification dispatch.
 */
class ProofStorageService
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function __construct(
        protected PdfRasterizerService $rasterizer,
        protected SupabaseClient $supabase
    ) {}

    public function store(
        UploadedFile $file,
        Invoice $invoice,
        User $uploader,
        string $paymentMethod = 'bank_transfer',
        ?string $reference = null,
        ?string $notes = null,
    ): PaymentProof {
        if (! in_array($invoice->status, ['unpaid', 'payment_pending'], true)) {
            $this->fail('This invoice is not open for payment.');
        }

        if ($invoice->proofs()->whereIn('status', [PaymentProof::STATUS_VERIFYING, PaymentProof::STATUS_PENDING_REVIEW])->exists()) {
            $this->fail('A payment proof for this invoice is already being verified.');
        }

        $attempts = $invoice->proofs()->count();
        if ($attempts >= (int) config('commerce.proof_max_attempts', 5)) {
            $this->fail('Too many proofs were submitted for this invoice. Please contact support.');
        }

        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            $this->fail('The uploaded file could not be read.');
        }

        if (filesize($path) > (int) config('commerce.proof_max_bytes', 5 * 1024 * 1024)) {
            $this->fail('The file is larger than 5 MB.');
        }

        $raw = file_get_contents($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw);

        if (! array_key_exists($mime, self::ALLOWED)) {
            $this->fail('Only PNG, JPEG, WEBP and PDF receipts are accepted.');
        }

        $ext = self::ALLOWED[$mime];

        if ($mime === 'application/pdf' && ! str_starts_with($raw, '%PDF-')) {
            $this->fail('The PDF file is not valid.');
        }

        // Clean & sanitize raster images to strip polyglot payloads
        $clean = $raw;
        $rasterPngBytes = null;

        if (str_starts_with($mime, 'image/') && function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring($raw);
            if ($image === false) {
                $this->fail('The image file is corrupt or unreadable.');
            }

            ob_start();
            match (true) {
                $ext === 'png' => imagepng($image),
                $ext === 'webp' && function_exists('imagewebp') => imagewebp($image),
                default => imagejpeg($image, null, 90),
            };
            $clean = (string) ob_get_clean();
            imagedestroy($image);
            $rasterPngBytes = $clean;
        } elseif ($mime === 'application/pdf') {
            // Convert first page to PNG for perceptual dHash and AI verification
            $rasterPngBytes = $this->rasterizer->rasterize($path, 200);
        }

        // Compute exact cryptographic SHA-256
        $hash = hash('sha256', $clean);

        // Compute 64-bit gradient perceptual dHash from rasterized pixels
        $dhash = $rasterPngBytes ? DHashService::compute($rasterPngBytes) : null;

        // Check exact duplicate
        $duplicateSha = PaymentProof::where('sha256_hash', $hash)
            ->where(function ($q) use ($invoice) {
                $q->where('invoice_id', '!=', $invoice->id)
                    ->orWhere(function ($q2) use ($invoice) {
                        $q2->where('invoice_id', $invoice->id)->where('status', PaymentProof::STATUS_REJECTED);
                    });
            })
            ->exists();

        if ($duplicateSha) {
            $this->fail('This receipt was already submitted. Please upload the valid receipt for this invoice.');
        }

        // Check perceptual dHash duplicate
        if ($dhash) {
            $existingDhashes = PaymentProof::whereNotNull('dhash')
                ->where('invoice_id', '!=', $invoice->id)
                ->pluck('dhash');

            foreach ($existingDhashes as $prevHash) {
                if (DHashService::distance($dhash, $prevHash) <= 3) {
                    $this->fail('A virtually identical receipt image was already submitted for another invoice.');
                }
            }
        }

        $disk = (string) config('commerce.proof_disk', 'payment_proofs');
        $storagePath = 'proofs/'.Str::uuid().'.'.$ext;

        // Upload to private storage disk
        Storage::disk($disk)->put($storagePath, $clean);

        // Upload to private Supabase Storage bucket if configured
        if (config('commerce.storage_driver') === 'supabase' && $this->supabase->isConfigured()) {
            $bucket = config('commerce.supabase_proof_bucket', 'payment-proofs');
            $this->supabase->uploadFile($bucket, $storagePath, $clean, $mime);
        }

        $proof = DB::transaction(function () use ($invoice, $uploader, $paymentMethod, $reference, $notes, $storagePath, $mime, $clean, $hash, $dhash, $attempts) {
            $proof = PaymentProof::create([
                'invoice_id' => $invoice->id,
                'user_id' => $uploader->id,
                'payment_method' => $paymentMethod,
                'transaction_reference' => $reference ?: null,
                'file_path' => $storagePath,
                'file_mime' => $mime,
                'file_size_bytes' => strlen($clean),
                'sha256_hash' => $hash,
                'dhash' => $dhash,
                'status' => PaymentProof::STATUS_VERIFYING,
                'admin_notes' => $notes ?: null,
                'attempt_no' => $attempts + 1,
            ]);

            $invoice->update([
                'status' => 'payment_pending',
                'payment_reference' => $reference ?: ($invoice->payment_reference ?: ('REF-'.strtoupper(Str::random(8)))),
            ]);
            $invoice->order?->update([
                'status' => 'payment_submitted',
                'payment_reference' => $invoice->payment_reference,
            ]);

            CommerceAuditLog::create([
                'user_id' => $uploader->id,
                'team_id' => $invoice->team_id,
                'action' => 'payment_proof.uploaded',
                'target_type' => PaymentProof::class,
                'target_id' => $proof->id,
                'payload' => [
                    'invoice_uuid' => $invoice->uuid,
                    'payment_method' => $paymentMethod,
                    'sha256' => $hash,
                    'dhash' => $dhash,
                    'attempt_no' => $attempts + 1,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return $proof;
        });

        // Dispatch background verification job
        VerifyPaymentProofJob::dispatch($proof->id);

        $this->notify($invoice, $proof, $uploader);

        return $proof;
    }

    private function notify(Invoice $invoice, PaymentProof $proof, User $uploader): void
    {
        StaffEvents::notify(StaffEvents::PROOF_UPLOADED, 'Payment proof submitted: '.$invoice->invoice_number, "{$uploader->name} uploaded a receipt (verifying with AI).", [
            'Amount' => Money::format((int) $invoice->total_cents, $invoice->currency),
            'Method' => $proof->payment_method,
            'Attempt' => (string) $proof->attempt_no,
        ], 'info', 'proof:'.$proof->id);

        try {
            $uploader->notify(new PaymentProofReceivedNotification($invoice));
        } catch (\Throwable $e) {
            logger()->warning("Could not send PaymentProofReceivedNotification: {$e->getMessage()}");
        }

        try {
            $staff = Team::find(0)?->members()->wherePivotIn('role', ['owner', 'admin'])->get() ?? collect();
            if ($staff->isNotEmpty()) {
                Notification::send($staff, new StaffProofSubmittedNotification($invoice, $proof));
            }
        } catch (\Throwable $e) {
            logger()->warning("Could not notify staff about proof: {$e->getMessage()}");
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['proof_file' => $message]);
    }
}
