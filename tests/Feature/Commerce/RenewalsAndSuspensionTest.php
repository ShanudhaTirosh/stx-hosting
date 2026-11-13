<?php

namespace Tests\Feature\Commerce;

use App\Actions\Commerce\ReactivateServiceAction;
use App\Enums\EntitlementStatus;
use App\Jobs\Commerce\GenerateRenewalInvoicesJob;
use App\Jobs\Commerce\ProcessOverdueSuspensionsJob;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\Order;
use App\Models\Commerce\OrderItem;
use App\Models\Commerce\PricingTier;
use App\Models\Commerce\ServiceInstance;
use App\Models\HostingEntitlement;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Commerce\ServiceReactivatedNotification;
use App\Notifications\Commerce\ServiceSuspendedNotification;
use App\Notifications\Commerce\UpcomingRenewalNotification;
use App\Services\Commerce\OrderService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

test('generate renewal invoices job creates renewal invoice for entitlements expiring within 7 days', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $tier = PricingTier::where('billing_type', 'monthly')->first() ?? PricingTier::firstOrFail();

    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'completed',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_variant_id' => $tier->product_variant_id,
        'pricing_tier_id' => $tier->id,
        'quantity' => 1,
        'unit_price_cents' => $tier->price_cents,
        'total_price_cents' => $tier->price_cents,
    ]);

    $entitlement = HostingEntitlement::create([
        'team_id' => $team->id,
        'product_type' => 'app_hosting',
        'status' => EntitlementStatus::ACTIVE->value,
        'granted_by' => $user->id,
        'expires_at' => now()->addDays(5), // Within 7 days window
        'metadata' => [
            'order_uuid' => $order->uuid,
            'order_item_uuid' => $orderItem->uuid,
        ],
    ]);

    app(GenerateRenewalInvoicesJob::class)->handle(app(OrderService::class));

    // Assert that a renewal invoice was created
    $renewalInvoice = Invoice::where('team_id', $team->id)->where('status', 'unpaid')->first();
    expect($renewalInvoice)->not->toBeNull();
    expect($renewalInvoice->due_at->toDateString())->toBe($entitlement->expires_at->toDateString());

    Notification::assertSentTo($user, UpcomingRenewalNotification::class);
});

test('process overdue suspensions job suspends workloads when 4-day grace period expires', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $tier = PricingTier::firstOrFail();

    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'completed',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_variant_id' => $tier->product_variant_id,
        'pricing_tier_id' => $tier->id,
        'quantity' => 1,
        'unit_price_cents' => $tier->price_cents,
        'total_price_cents' => $tier->price_cents,
    ]);

    // Expired 5 days ago (past the 4-day grace period)
    $entitlement = HostingEntitlement::create([
        'team_id' => $team->id,
        'product_type' => 'vps',
        'status' => EntitlementStatus::ACTIVE->value,
        'granted_by' => $user->id,
        'expires_at' => now()->subDays(5),
        'metadata' => [
            'order_item_uuid' => $orderItem->uuid,
        ],
    ]);

    $vps = ServiceInstance::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'order_id' => $order->id,
        'product_variant_id' => $tier->product_variant_id,
        'service_type' => 'vps',
        'status' => 'active',
        'name' => 'vps-test',
    ]);

    app(ProcessOverdueSuspensionsJob::class)->handle();

    expect($entitlement->fresh()->status)->toBe(EntitlementStatus::SUSPENDED->value);
    expect($vps->fresh()->status)->toBe('suspended');
    expect($vps->fresh()->suspended_at)->not->toBeNull();

    Notification::assertSentTo($user, ServiceSuspendedNotification::class);
});

test('reactivate service action unsuspends services upon renewal payment', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $tier = PricingTier::firstOrFail();

    $entitlement = HostingEntitlement::create([
        'team_id' => $team->id,
        'product_type' => 'vps',
        'status' => EntitlementStatus::SUSPENDED->value,
        'granted_by' => $user->id,
        'expires_at' => now()->subDays(5),
    ]);

    $vps = ServiceInstance::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'product_variant_id' => $tier->product_variant_id,
        'service_type' => 'vps',
        'status' => 'suspended',
        'suspended_at' => now()->subDays(1),
        'name' => 'vps-test',
    ]);

    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'paid',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
        'customer_notes' => "renewal:entitlement:{$entitlement->id}",
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-202611-RENEW1',
        'order_id' => $order->id,
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'paid',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
        'due_at' => now(),
    ]);

    ReactivateServiceAction::run($invoice, $user);

    expect($entitlement->fresh()->status)->toBe(EntitlementStatus::ACTIVE->value);
    expect($entitlement->fresh()->expires_at->isFuture())->toBeTrue();
    expect($vps->fresh()->status)->toBe('active');
    expect($vps->fresh()->suspended_at)->toBeNull();

    Notification::assertSentTo($user, ServiceReactivatedNotification::class);
});

