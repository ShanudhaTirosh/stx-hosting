<?php

namespace Tests\Feature\Commerce;

use App\Livewire\Admin\Billing\ProofReviewQueue;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\Order;
use App\Models\Commerce\PaymentProof;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
});

test('staff user can review, approve, reject and request resubmission on customer proofs', function () {
    $staff = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($staff->id, ['role' => 'admin']);

    $customerTeam = Team::factory()->create();
    $customer = User::factory()->create();

    $order = Order::create([
        'team_id' => $customerTeam->id,
        'user_id' => $customer->id,
        'status' => 'pending',
        'subtotal_cents' => 125000,
        'total_cents' => 125000,
        'currency' => 'LKR',
        'payment_reference' => 'STX-QUEUE-TEST',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-QUEUE-1',
        'order_id' => $order->id,
        'team_id' => $customerTeam->id,
        'user_id' => $customer->id,
        'status' => 'payment_pending',
        'subtotal_cents' => 125000,
        'total_cents' => 125000,
        'currency' => 'LKR',
        'due_at' => now()->addDays(3),
    ]);

    $proof = PaymentProof::create([
        'invoice_id' => $invoice->id,
        'user_id' => $customer->id,
        'payment_method' => 'bank_transfer',
        'transaction_reference' => 'TXN-BANK-112233',
        'file_path' => 'proofs/queue_sample.png',
        'file_mime' => 'image/png',
        'file_size_bytes' => 1024,
        'sha256_hash' => hash('sha256', 'sample-queue-1'),
        'dhash' => '1234567890abcdef',
        'status' => PaymentProof::STATUS_PENDING_REVIEW,
        'confidence_score' => 0.78,
    ]);

    $this->actingAs($staff);

    // 1. Staff can select and view proof
    Livewire::test(ProofReviewQueue::class)
        ->assertSee('TXN-BANK-112233')
        ->call('selectProof', $proof->id)
        ->assertSet('selectedProofId', $proof->id);

    // 2. Staff can approve proof
    Livewire::test(ProofReviewQueue::class)
        ->call('selectProof', $proof->id)
        ->call('approve', $proof->id)
        ->assertDispatched('success');

    $proof->refresh();
    $invoice->refresh();
    $order->refresh();

    expect($proof->status)->toBe(PaymentProof::STATUS_APPROVED);
    expect($proof->reviewed_by_user_id)->toBe($staff->id);
    expect($invoice->status)->toBe('paid');
    expect($order->status)->toBe('paid');

    // 3. Test reject flow on another proof
    $proof2 = PaymentProof::create([
        'invoice_id' => $invoice->id,
        'user_id' => $customer->id,
        'payment_method' => 'bank_transfer',
        'transaction_reference' => 'TXN-INVALID-99',
        'file_path' => 'proofs/queue_sample2.png',
        'file_mime' => 'image/png',
        'file_size_bytes' => 1024,
        'sha256_hash' => hash('sha256', 'sample-queue-2'),
        'status' => PaymentProof::STATUS_PENDING_REVIEW,
    ]);

    Livewire::test(ProofReviewQueue::class)
        ->call('selectProof', $proof2->id)
        ->set('rejectionReason', 'Account number on the slip does not match our bank account.')
        ->call('reject', $proof2->id)
        ->assertDispatched('success');

    $proof2->refresh();
    expect($proof2->status)->toBe(PaymentProof::STATUS_REJECTED);
    expect($proof2->rejection_reason)->toBe('Account number on the slip does not match our bank account.');

    // 4. Test request resubmission flow
    $proof3 = PaymentProof::create([
        'invoice_id' => $invoice->id,
        'user_id' => $customer->id,
        'payment_method' => 'bank_transfer',
        'transaction_reference' => 'TXN-BLURRY-01',
        'file_path' => 'proofs/queue_sample3.png',
        'file_mime' => 'image/png',
        'file_size_bytes' => 1024,
        'sha256_hash' => hash('sha256', 'sample-queue-3'),
        'status' => PaymentProof::STATUS_PENDING_REVIEW,
    ]);

    Livewire::test(ProofReviewQueue::class)
        ->call('selectProof', $proof3->id)
        ->set('resubmitReason', 'The slip photo is blurry and the reference is cut off. Please re-take a clear photo.')
        ->call('requestResubmit', $proof3->id)
        ->assertDispatched('success');

    $proof3->refresh();
    expect($proof3->status)->toBe(PaymentProof::STATUS_RESUBMIT);
    expect($proof3->rejection_reason)->toBe('The slip photo is blurry and the reference is cut off. Please re-take a clear photo.');
});

test('non-staff customer cannot access proof review queue', function () {
    $customer = User::factory()->create();
    $customerTeam = Team::factory()->create();
    $customerTeam->members()->attach($customer->id, ['role' => 'admin']);

    $this->actingAs($customer);

    Livewire::test(ProofReviewQueue::class)
        ->assertForbidden();
});

