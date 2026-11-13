<?php

namespace Tests\Feature\Commerce;

use App\Actions\Commerce\ProcessRefundAction;
use App\Enums\EntitlementStatus;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\Order;
use App\Models\Commerce\PaymentTransaction;
use App\Models\Commerce\PricingTier;
use App\Models\HostingEntitlement;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Commerce\PaymentRefundedNotification;
use App\Services\Commerce\RefundService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

test('staff can process a full refund and revoke entitlements', function () {
    Notification::fake();

    $rootTeam = Team::factory()->create(['id' => 0]);
    $staffUser = User::factory()->create();
    $rootTeam->members()->attach($staffUser->id, ['role' => 'owner']);

    $customerTeam = Team::factory()->create();
    $customerUser = User::factory()->create();
    $customerTeam->members()->attach($customerUser->id, ['role' => 'owner']);

    $tier = PricingTier::firstOrFail();

    $order = Order::create([
        'team_id' => $customerTeam->id,
        'user_id' => $customerUser->id,
        'status' => 'completed',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-202611-TEST01',
        'order_id' => $order->id,
        'team_id' => $customerTeam->id,
        'user_id' => $customerUser->id,
        'status' => 'paid',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
        'due_at' => now(),
        'paid_at' => now(),
    ]);

    $entitlement = HostingEntitlement::create([
        'team_id' => $customerTeam->id,
        'product_type' => 'app_hosting',
        'status' => EntitlementStatus::ACTIVE->value,
        'granted_by' => $staffUser->id,
        'expires_at' => now()->addDays(30),
        'metadata' => [
            'invoice_uuid' => $invoice->uuid,
            'order_uuid' => $order->uuid,
        ],
    ]);

    $refundService = app(RefundService::class);
    $refundedInvoice = $refundService->refund(
        $invoice,
        $invoice->total_cents,
        'Customer requested refund within 30-day window',
        $staffUser,
        'manual',
        true
    );

    expect($refundedInvoice->status)->toBe('refunded');
    expect($order->fresh()->status)->toBe('refunded');
    expect($entitlement->fresh()->status)->toBe(EntitlementStatus::REVOKED->value);

    $transaction = PaymentTransaction::where('invoice_id', $invoice->id)->where('status', 'refunded')->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->amount_cents)->toBe(-$invoice->total_cents);

    Notification::assertSentTo($customerUser, PaymentRefundedNotification::class);
});

test('staff can process a partial refund leaving entitlement active', function () {
    Notification::fake();

    $rootTeam = Team::factory()->create(['id' => 0]);
    $staffUser = User::factory()->create();
    $rootTeam->members()->attach($staffUser->id, ['role' => 'owner']);

    $customerTeam = Team::factory()->create();
    $customerUser = User::factory()->create();
    $customerTeam->members()->attach($customerUser->id, ['role' => 'owner']);

    $order = Order::create([
        'team_id' => $customerTeam->id,
        'user_id' => $customerUser->id,
        'status' => 'completed',
        'subtotal_cents' => 10000,
        'total_cents' => 10000,
        'currency' => 'USD',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-202611-PARTIAL',
        'order_id' => $order->id,
        'team_id' => $customerTeam->id,
        'user_id' => $customerUser->id,
        'status' => 'paid',
        'subtotal_cents' => 10000,
        'total_cents' => 10000,
        'currency' => 'USD',
        'due_at' => now(),
        'paid_at' => now(),
    ]);

    $entitlement = HostingEntitlement::create([
        'team_id' => $customerTeam->id,
        'product_type' => 'app_hosting',
        'status' => EntitlementStatus::ACTIVE->value,
        'granted_by' => $staffUser->id,
        'expires_at' => now()->addDays(30),
        'metadata' => [
            'invoice_uuid' => $invoice->uuid,
        ],
    ]);

    // Partial refund of 5000 cents ($50) without revoking entitlements
    ProcessRefundAction::run(
        $invoice,
        5000,
        'Partial service credit applied',
        $staffUser,
        'bank_wire',
        false
    );

    expect($invoice->fresh()->status)->toBe('partially_refunded');
    expect($entitlement->fresh()->status)->toBe(EntitlementStatus::ACTIVE->value);

    Notification::assertSentTo($customerUser, PaymentRefundedNotification::class);
});

