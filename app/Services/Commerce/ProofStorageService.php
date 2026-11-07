<?php

namespace App\Services\Commerce;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\PaymentProof;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Commerce\PaymentProofReceivedNotification;
use App\Notifications\Commerce\StaffProofSubmittedNotification;
use App\Services\Notifications\StaffEvents;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The single place a payment proof enters the system (portal and API both use it).
 *
 * - magic-byte type check (not the client-supplied extension or MIME)
 * - raster images are re-encoded to strip polyglot payloads
 * - SHA-256 duplicate detection across invoices
 * - stored under a random name on a private disk, never publicly served
 * - one pending proof per invoice, bounded retries
 */
class ProofStorageService
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

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

        if ($invoice->proofs()->where('status', 'pending_review')->exists()) {
            $this->fail('A payment proof for this invoice is already waiting for review.');
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

        $clean = $raw;
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
        }

        $hash = hash('sha256', $clean);

        $duplicate = PaymentProof::where('sha256_hash', $hash)
            ->where(function ($q) use ($invoice) {
                $q->where('invoice_id', '!=', $invoice->id)
                    ->orWhere(function ($q2) use ($invoice) {
                        $q2->where('invoice_id', $invoice->id)->where('status', 'rejected');
                    });
            })
            ->exists();

        if ($duplicate) {
            $this->fail('This receipt was already submitted. Please upload the correct receipt for this invoice.');
        }

        $disk = (string) config('commerce.proof_disk', 'payment_proofs');
        $storagePath = 'proofs/'.Str::uuid().'.'.$ext;

        $proof = DB::transaction(function () use ($invoice, $uploader, $paymentMethod, $reference, $notes, $storagePath, $mime, $clean, $hash, $disk, $attempts) {
            Storage::disk($disk)->put($storagePath, $clean);

            $proof = PaymentProof::create([
                'invoice_id' => $invoice->id,
                'user_id' => $uploader->id,
                'payment_method' => $paymentMethod,
                'transaction_reference' => $reference ?: null,
                'file_path' => $storagePath,
                'file_mime' => $mime,
                'file_size_bytes' => strlen($clean),
                'sha256_hash' => $hash,
                'status' => 'pending_review',
                'admin_notes' => $notes ?: null,
                'attempt_no' => $attempts + 1,
            ]);

            $invoice->update(['status' => 'payment_pending']);
            $invoice->order?->update(['status' => 'payment_submitted']);

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
                    'attempt_no' => $attempts + 1,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return $proof;
        });

        $this->notify($invoice, $proof, $uploader);

        return $proof;
    }

    private function notify(Invoice $invoice, PaymentProof $proof, User $uploader): void
    {
        StaffEvents::notify(StaffEvents::PROOF_UPLOADED, 'Payment proof to verify: '.$invoice->invoice_number, "{$uploader->name} uploaded a receipt.", [
            'Amount' => Money::format((int) $invoice->total_cents, $invoice->currency),
            'Method' => $proof->payment_method,
            'Attempt' => (string) $proof->attempt_no,
        ], 'warning', 'proof:'.$proof->id);

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
