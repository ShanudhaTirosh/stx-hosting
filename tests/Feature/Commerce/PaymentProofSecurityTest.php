<?php

namespace Tests\Feature\Commerce;

use App\Livewire\Admin\Billing\ProofReviewQueue;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\Order;
use App\Models\Commerce\PaymentProof;
use App\Models\Commerce\PricingTier;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
    Storage::fake('payment_proofs');
});

test('customer can upload valid payment receipt transitioning invoice to payment_pending', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);
    $this->actingAs($user);

    $tier = PricingTier::firstOrFail();
    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-0001',
        'order_id' => $order->id,
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'unpaid',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
        'due_at' => now()->addDays(7),
    ]);

    $fakeImage = UploadedFile::fake()->image('receipt.png', 400, 300);

    $response = $this->postJson("/api/v1/invoices/{$invoice->uuid}/proof", [
        'proof_file' => $fakeImage,
        'payment_method' => 'bank_transfer',
        'transaction_reference' => 'TXN-BANK-998822',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'success',
            'proof' => ['uuid', 'status', 'payment_method', 'transaction_reference'],
            'invoice_status',
        ]);

    $invoice->refresh();
    expect($invoice->status)->toBe('payment_pending');

    $proof = PaymentProof::where('invoice_id', $invoice->id)->firstOrFail();
    expect($proof->status)->toBe('pending_review');
    expect($proof->transaction_reference)->toBe('TXN-BANK-998822');
    Storage::disk('payment_proofs')->assertExists($proof->file_path);
});

test('administrator can approve payment proof in review queue marking invoice as paid', function () {
    $admin = User::factory()->create();
    $team = Team::factory()->create();
    $customer = User::factory()->create();

    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $customer->id,
        'status' => 'pending',
        'subtotal_cents' => 1500,
        'total_cents' => 1500,
        'currency' => 'USD',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-0002',
        'order_id' => $order->id,
        'team_id' => $team->id,
        'user_id' => $customer->id,
        'status' => 'payment_pending',
        'subtotal_cents' => 1500,
        'total_cents' => 1500,
        'currency' => 'USD',
        'due_at' => now()->addDays(7),
    ]);

    $proof = PaymentProof::create([
        'invoice_id' => $invoice->id,
        'user_id' => $customer->id,
        'payment_method' => 'bank_transfer',
        'transaction_reference' => 'REF-12345',
        'file_path' => 'proofs/test.png',
        'file_mime' => 'image/png',
        'file_size_bytes' => 1024,
        'sha256_hash' => hash('sha256', 'dummy'),
        'status' => 'pending_review',
    ]);

    $this->actingAs($admin);

    Livewire::test(ProofReviewQueue::class)
        ->call('selectProof', $proof->id)
        ->assertSet('selectedProofId', $proof->id)
        ->call('approve', $proof->id)
        ->assertDispatched('success');

    $invoice->refresh();
    $order->refresh();
    $proof->refresh();

    expect($invoice->status)->toBe('paid');
    expect($order->status)->toBe('completed');
    expect($proof->status)->toBe('approved');
    expect($proof->reviewed_by_user_id)->toBe($admin->id);
    expect($invoice->transactions()->count())->toBe(1);
});

