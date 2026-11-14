<?php

namespace Tests\Feature\Commerce;

use App\Jobs\Commerce\VerifyPaymentProofJob;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\Order;
use App\Models\Commerce\PaymentMethod;
use App\Models\Commerce\PaymentProof;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Services\Commerce\DHashService;
use App\Services\Commerce\PayVerifyClient;
use App\Services\Commerce\PdfRasterizerService;
use App\Services\Commerce\ProofStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    Storage::fake('payment_proofs');
});

test('proof storage service processes uploaded image, computes dhash and dispatches verification job', function () {
    Queue::fake();

    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'subtotal_cents' => 350000,
        'total_cents' => 350000,
        'currency' => 'LKR',
        'payment_reference' => 'STX-TEST-REF1',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-0010',
        'order_id' => $order->id,
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'unpaid',
        'subtotal_cents' => 350000,
        'total_cents' => 350000,
        'currency' => 'LKR',
        'payment_reference' => 'STX-TEST-REF1',
        'due_at' => now()->addDays(3),
    ]);

    $fakeFile = UploadedFile::fake()->image('receipt.png', 400, 600);

    $storageService = app(ProofStorageService::class);
    $proof = $storageService->storeAndSanitize(
        $invoice,
        $fakeFile,
        'bank_transfer',
        'STX-TEST-REF1',
        $user
    );

    expect($proof->status)->toBe(PaymentProof::STATUS_VERIFYING);
    expect($proof->sha256_hash)->not->toBeEmpty();
    expect($proof->dhash)->not->toBeEmpty();
    expect($invoice->fresh()->status)->toBe('payment_pending');

    Queue::assertPushed(VerifyPaymentProofJob::class, function ($job) use ($proof) {
        return $job->proofId === $proof->id;
    });
});

test('verification job auto-approves proof when payverify returns high confidence and matching fields', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $paymentMethod = PaymentMethod::create([
        'name' => 'Commercial Bank',
        'type' => 'bank_transfer',
        'account_holder' => 'STX Cloud',
        'account_number' => '1000248491',
        'currency' => 'LKR',
        'is_active' => true,
    ]);

    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'subtotal_cents' => 350000,
        'total_cents' => 350000,
        'currency' => 'LKR',
        'payment_reference' => 'STX-CONF-9901',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-0011',
        'order_id' => $order->id,
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'payment_pending',
        'subtotal_cents' => 350000,
        'total_cents' => 350000,
        'currency' => 'LKR',
        'payment_reference' => 'STX-CONF-9901',
        'due_at' => now()->addDays(3),
    ]);

    $proof = PaymentProof::create([
        'invoice_id' => $invoice->id,
        'user_id' => $user->id,
        'payment_method' => 'bank_transfer',
        'payment_method_id' => $paymentMethod->id,
        'transaction_reference' => 'STX-CONF-9901',
        'file_path' => 'proofs/test_high_confidence.png',
        'file_mime' => 'image/png',
        'file_size_bytes' => 2048,
        'sha256_hash' => hash('sha256', 'sample-proof-content'),
        'dhash' => '0f0f0f0f0f0f0f0f',
        'status' => PaymentProof::STATUS_VERIFYING,
    ]);

    // Mock PayVerifyClient returning 96% confidence and perfect match
    $mockClient = Mockery::mock(PayVerifyClient::class);
    $mockClient->shouldReceive('uploadAndReview')
        ->once()
        ->andReturn([
            'status' => 'verified',
            'confidence_score' => 0.96,
            'claim_id' => 881,
            'discrepancies' => [],
            'extraction' => [
                'amount_cents' => 350000,
                'reference' => 'STX-CONF-9901',
                'recipient_account_tail' => '8491',
                'datetime' => now()->toISOString(),
            ],
            'forensics' => [
                'dhash' => '0f0f0f0f0f0f0f0f',
                'tampering_detected' => false,
            ],
            'verification_methods' => ['ai_vision_ocr', 'amount_reconciliation', 'account_tail_match'],
        ]);

    $job = new VerifyPaymentProofJob($proof->id);
    $job->handle(
        $mockClient,
        app(\App\Services\Commerce\PaymentReviewService::class),
        app(PdfRasterizerService::class),
        app(DHashService::class)
    );

    $proof->refresh();
    $invoice->refresh();
    $order->refresh();

    expect($proof->status)->toBe(PaymentProof::STATUS_AUTO_VERIFIED);
    expect($proof->confidence_score)->toBe(0.96);
    expect($proof->extracted_amount_cents)->toBe(350000);
    expect($proof->extracted_reference)->toBe('STX-CONF-9901');
    expect($invoice->status)->toBe('paid');
    expect($order->status)->toBe('paid');
    expect($invoice->transactions()->count())->toBe(1);
});

test('verification job routes to manual review when confidence is moderate or partial discrepancy exists', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'subtotal_cents' => 500000,
        'total_cents' => 500000,
        'currency' => 'LKR',
        'payment_reference' => 'STX-REVW-1002',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-0012',
        'order_id' => $order->id,
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'payment_pending',
        'subtotal_cents' => 500000,
        'total_cents' => 500000,
        'currency' => 'LKR',
        'payment_reference' => 'STX-REVW-1002',
        'due_at' => now()->addDays(3),
    ]);

    $proof = PaymentProof::create([
        'invoice_id' => $invoice->id,
        'user_id' => $user->id,
        'payment_method' => 'bank_transfer',
        'transaction_reference' => 'STX-REVW-1002',
        'file_path' => 'proofs/test_medium_confidence.png',
        'file_mime' => 'image/png',
        'file_size_bytes' => 2048,
        'sha256_hash' => hash('sha256', 'medium-content'),
        'dhash' => '1111222233334444',
        'status' => PaymentProof::STATUS_VERIFYING,
    ]);

    $mockClient = Mockery::mock(PayVerifyClient::class);
    $mockClient->shouldReceive('uploadAndReview')
        ->once()
        ->andReturn([
            'status' => 'manual_review',
            'confidence_score' => 0.72,
            'claim_id' => 882,
            'discrepancies' => ['reference_partially_obscured_by_stamp'],
            'extraction' => [
                'amount_cents' => 500000,
                'reference' => 'STX-REVW-10??',
            ],
            'forensics' => [
                'tampering_detected' => false,
            ],
            'verification_methods' => ['ai_vision_ocr'],
        ]);

    $job = new VerifyPaymentProofJob($proof->id);
    $job->handle(
        $mockClient,
        app(\App\Services\Commerce\PaymentReviewService::class),
        app(PdfRasterizerService::class),
        app(DHashService::class)
    );

    $proof->refresh();
    $invoice->refresh();

    expect($proof->status)->toBe(PaymentProof::STATUS_PENDING_REVIEW);
    expect($proof->confidence_score)->toBe(0.72);
    expect($invoice->status)->toBe('payment_pending');
});

test('verification job detects duplicate perceptual hash and rejects reused receipt', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $order1 = Order::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'paid',
        'subtotal_cents' => 200000,
        'total_cents' => 200000,
        'currency' => 'LKR',
    ]);

    $invoice1 = Invoice::create([
        'invoice_number' => 'INV-TEST-ORIGINAL',
        'order_id' => $order1->id,
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'paid',
        'subtotal_cents' => 200000,
        'total_cents' => 200000,
        'currency' => 'LKR',
        'due_at' => now(),
    ]);

    // Existing verified proof with dhash "0000000000000000"
    PaymentProof::create([
        'invoice_id' => $invoice1->id,
        'user_id' => $user->id,
        'payment_method' => 'bank_transfer',
        'file_path' => 'proofs/original.png',
        'file_mime' => 'image/png',
        'file_size_bytes' => 1024,
        'sha256_hash' => hash('sha256', 'original-content'),
        'dhash' => '0000000000000000',
        'status' => PaymentProof::STATUS_APPROVED,
    ]);

    // New order and invoice
    $order2 = Order::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'subtotal_cents' => 200000,
        'total_cents' => 200000,
        'currency' => 'LKR',
    ]);

    $invoice2 = Invoice::create([
        'invoice_number' => 'INV-TEST-DUPLICATE',
        'order_id' => $order2->id,
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'payment_pending',
        'subtotal_cents' => 200000,
        'total_cents' => 200000,
        'currency' => 'LKR',
        'due_at' => now()->addDays(3),
    ]);

    // Reused proof with identical dhash
    $duplicateProof = PaymentProof::create([
        'invoice_id' => $invoice2->id,
        'user_id' => $user->id,
        'payment_method' => 'bank_transfer',
        'file_path' => 'proofs/duplicate.png',
        'file_mime' => 'image/png',
        'file_size_bytes' => 1024,
        'sha256_hash' => hash('sha256', 'duplicate-content'),
        'dhash' => '0000000000000000',
        'status' => PaymentProof::STATUS_VERIFYING,
    ]);

    $mockClient = Mockery::mock(PayVerifyClient::class);

    $job = new VerifyPaymentProofJob($duplicateProof->id);
    $job->handle(
        $mockClient,
        app(\App\Services\Commerce\PaymentReviewService::class),
        app(PdfRasterizerService::class),
        app(DHashService::class)
    );

    $duplicateProof->refresh();
    expect($duplicateProof->status)->toBe(PaymentProof::STATUS_REJECTED);
    expect($duplicateProof->rejection_reason)->toContain('Duplicate payment slip detected');
});

