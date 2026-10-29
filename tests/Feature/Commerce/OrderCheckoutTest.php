<?php

namespace Tests\Feature\Commerce;

use App\Models\Commerce\Invoice;
use App\Models\Commerce\Order;
use App\Models\Commerce\PricingTier;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

test('authenticated user can place an order creating an invoice atomically', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);
    $this->actingAs($user);
    session(['currentTeam' => $team]);

    $tier = PricingTier::where('billing_type', '30-day')->firstOrFail();

    $response = $this->postJson('/api/v1/orders', [
        'items' => [
            [
                'pricing_tier_id' => (string) $tier->uuid,
                'quantity' => 1,
                'hostname' => 'test-node-01.shanutechx.internal',
                'datacenter' => 'fsn1',
            ],
        ],
        'currency' => 'USD',
        'customer_notes' => 'Please configure Debian 12.',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'success',
            'order' => ['uuid', 'status', 'total', 'currency'],
            'invoice' => ['uuid', 'invoice_number', 'status', 'total', 'currency', 'due_at'],
        ]);

    $order = Order::where('uuid', $response->json('order.uuid'))->firstOrFail();
    expect($order->status)->toBe('pending');
    expect($order->total_cents)->toBe($tier->price_cents);
    expect($order->team_id)->toBe($team->id);

    $invoice = Invoice::where('uuid', $response->json('invoice.uuid'))->firstOrFail();
    expect($invoice->status)->toBe('unpaid');
    expect($invoice->total_cents)->toBe($tier->price_cents);
    expect($invoice->order_id)->toBe($order->id);
    expect($invoice->items()->count())->toBe(1);
});

test('server-side pricing validation cannot be bypassed by client payload', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tier = PricingTier::where('billing_type', '30-day')->firstOrFail();

    // Client attempts to pass fake price of $0.01
    $response = $this->postJson('/api/v1/orders', [
        'items' => [
            [
                'pricing_tier_id' => (string) $tier->uuid,
                'quantity' => 1,
                'price' => 0.01, // Spoofed amount
            ],
        ],
        'total' => 0.01, // Spoofed total
        'currency' => 'USD',
    ]);

    $response->assertCreated();
    $order = Order::where('uuid', $response->json('order.uuid'))->firstOrFail();
    
    // Server enforces canonical price from database, ignoring spoofed client price
    expect($order->total_cents)->toBe($tier->price_cents);
});
