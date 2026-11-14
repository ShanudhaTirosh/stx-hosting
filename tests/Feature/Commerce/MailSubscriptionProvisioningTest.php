<?php

namespace Tests\Feature\Commerce;

use App\Actions\Commerce\ReactivateServiceAction;
use App\Enums\EntitlementStatus;
use App\Jobs\Commerce\GenerateRenewalInvoicesJob;
use App\Jobs\Commerce\ProcessOverdueSuspensionsJob;
use App\Livewire\Portal\Mail\MailDashboard;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\MailAddon;
use App\Models\Commerce\MailAlias;
use App\Models\Commerce\MailDomain;
use App\Models\Commerce\MailMailbox;
use App\Models\Commerce\MailPlan;
use App\Models\Commerce\MailSubscription;
use App\Models\Commerce\Order;
use App\Models\Commerce\OrderItem;
use App\Models\Commerce\PaymentProof;
use App\Models\Commerce\PricingTier;
use App\Models\Commerce\ProductVariant;
use App\Models\CustomDomain;
use App\Models\HostingEntitlement;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\PaymentReviewService;
use App\Services\Commerce\WildDuckProvisioningService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

test('hosting catalog seeder sets up business email products, tiered plans, and add-ons', function () {
    $plans = MailPlan::all();
    expect($plans->count())->toBeGreaterThanOrEqual(4);

    $starter = MailPlan::where('slug', 'mail-starter')->first();
    expect($starter)->not->toBeNull();
    expect($starter->storage_mb)->toBe(500);
    expect($starter->max_mailboxes)->toBe(2);

    $business = MailPlan::where('slug', 'mail-business')->first();
    expect($business)->not->toBeNull();
    expect($business->storage_mb)->toBe(5120);
    expect($business->max_mailboxes)->toBe(15);

    $addons = MailAddon::all();
    expect($addons->count())->toBeGreaterThanOrEqual(3);
});

test('payment approval automatically provisions mail subscription and entitlement', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $reviewer = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $variant = ProductVariant::where('slug', 'mail-pro')->firstOrFail();
    $tier = PricingTier::where('product_variant_id', $variant->id)->where('billing_type', 'monthly')->firstOrFail();

    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'payment_pending',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'pricing_tier_id' => $tier->id,
        'quantity' => 1,
        'unit_price_cents' => $tier->price_cents,
        'total_price_cents' => $tier->price_cents,
    ]);

    $invoice = Invoice::create([
        'team_id' => $team->id,
        'order_id' => $order->id,
        'user_id' => $user->id,
        'invoice_number' => 'INV-EMAIL-001',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
        'status' => 'payment_pending',
        'due_at' => now()->addDays(7),
    ]);

    $proof = PaymentProof::create([
        'team_id' => $team->id,
        'order_id' => $order->id,
        'invoice_id' => $invoice->id,
        'uploaded_by_user_id' => $user->id,
        'file_path' => 'proofs/mock.png',
        'mime_type' => 'image/png',
        'file_size_bytes' => 1024,
        'status' => 'pending',
    ]);

    $reviewService = app(PaymentReviewService::class);
    $reviewService->approve($proof, $reviewer, 'Verified payment');

    // Assert that a MailSubscription was provisioned
    $sub = MailSubscription::where('team_id', $team->id)->first();
    expect($sub)->not->toBeNull();
    expect($sub->status)->toBe('active');
    expect($sub->total_storage_mb)->toBe(3072);
    expect($sub->max_mailboxes)->toBe(5);

    // Assert that a HostingEntitlement was granted
    $entitlement = HostingEntitlement::where('team_id', $team->id)->where('product_type', 'email_hosting')->first();
    expect($entitlement)->not->toBeNull();
    expect($entitlement->status)->toBe('active');
});

test('mail dashboard component displays subscription and handles mailbox lifecycle', function () {
    $team = Team::factory()->create(['show_boarding' => false]);
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);
    refreshSession($team);

    $plan = MailPlan::where('slug', 'mail-basic')->firstOrFail();
    $subscription = MailSubscription::create([
        'team_id' => $team->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'total_storage_mb' => 1024,
        'max_mailboxes' => 2,
        'max_domains' => 1,
        'renews_at' => now()->addMonth(),
    ]);

    // Mock WildDuck provisioning
    $mockWildduck = Mockery::mock(WildDuckProvisioningService::class);
    $mockWildduck->shouldReceive('registerDomain')->with('acme.com')->andReturn([
        'wildduck_domain_id' => 'wd_dom_123',
        'dkim_selector' => 'stx',
        'dkim_public_key' => 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC...',
    ]);
    $mockWildduck->shouldReceive('createMailbox')->andReturn([
        'wildduck_user_id' => 'wd_usr_456',
        'address' => 'contact@acme.com',
    ]);
    $mockWildduck->shouldReceive('updateMailboxActive')->andReturn(true);
    $mockWildduck->shouldReceive('createAlias')->andReturn(true);
    $mockWildduck->shouldReceive('setCatchAll')->andReturn(true);
    $mockWildduck->shouldReceive('getMailboxUsageBytes')->andReturn(52428800); // 50 MB
    app()->instance(WildDuckProvisioningService::class, $mockWildduck);

    // Test adding custom domain
    Livewire::actingAs($user)
        ->test(MailDashboard::class)
        ->set('newDomainName', 'acme.com')
        ->call('addDomain')
        ->assertDispatched('success');

    $domain = MailDomain::where('team_id', $team->id)->where('domain', 'acme.com')->first();
    expect($domain)->not->toBeNull();
    expect($domain->dkim_selector)->toBe('stx');

    // Test creating first mailbox
    Livewire::actingAs($user)
        ->test(MailDashboard::class)
        ->set('selectedDomainId', $domain->id)
        ->set('newMailboxUsername', 'contact')
        ->set('newMailboxName', 'Acme Support')
        ->set('newMailboxPassword', 'SecretPassword123!')
        ->call('createMailbox')
        ->assertDispatched('success');

    $box = MailMailbox::where('domain_id', $domain->id)->where('address', 'contact@acme.com')->first();
    expect($box)->not->toBeNull();
    expect($box->is_active)->toBeTrue();

    // Test creating alias
    Livewire::actingAs($user)
        ->test(MailDashboard::class)
        ->set('selectedMailboxForAliases', $box->id)
        ->set('newAliasUsername', 'help')
        ->call('addAlias')
        ->assertDispatched('success');

    expect(MailAlias::where('mailbox_id', $box->id)->where('alias_address', 'help@acme.com')->exists())->toBeTrue();

    // Test setting catch-all
    Livewire::actingAs($user)
        ->test(MailDashboard::class)
        ->call('toggleCatchAll', $box->id)
        ->assertDispatched('success');

    expect($box->fresh()->is_catch_all)->toBeTrue();

    // Test toggling active / suspend status
    Livewire::actingAs($user)
        ->test(MailDashboard::class)
        ->call('toggleMailboxStatus', $box->id)
        ->assertDispatched('success');

    expect($box->fresh()->is_active)->toBeFalse();
});

test('generate renewal invoices job creates renewal invoices for expiring mail subscriptions', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $plan = MailPlan::where('slug', 'mail-basic')->firstOrFail();
    $mailSub = MailSubscription::create([
        'team_id' => $team->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'total_storage_mb' => 1024,
        'max_mailboxes' => 5,
        'max_domains' => 1,
        'renews_at' => now()->addDays(5), // Within 7-day window
    ]);

    app(GenerateRenewalInvoicesJob::class)->handle(app(OrderService::class));

    $renewalInvoice = Invoice::where('team_id', $team->id)->where('status', 'unpaid')->first();
    expect($renewalInvoice)->not->toBeNull();
    expect($renewalInvoice->due_at->toDateString())->toBe($mailSub->renews_at->toDateString());
});

test('process overdue suspensions job suspends mail subscription and pauses mailboxes', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $plan = MailPlan::where('slug', 'mail-basic')->firstOrFail();
    $mailSub = MailSubscription::create([
        'team_id' => $team->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'total_storage_mb' => 1024,
        'max_mailboxes' => 5,
        'max_domains' => 1,
        'renews_at' => now()->subDays(5), // 5 days past due (beyond 4-day grace period)
    ]);

    $box = MailMailbox::create([
        'domain_id' => 'dom_test_1',
        'team_id' => $team->id,
        'wildduck_user_id' => 'wd_usr_test',
        'address' => 'admin@testcorp.com',
        'name' => 'Admin User',
        'is_active' => true,
    ]);

    $mockWildduck = Mockery::mock(WildDuckProvisioningService::class);
    $mockWildduck->shouldReceive('updateMailboxActive')->with('wd_usr_test', false)->andReturn(true);
    app()->instance(WildDuckProvisioningService::class, $mockWildduck);

    app(ProcessOverdueSuspensionsJob::class)->handle();

    expect($mailSub->fresh()->status)->toBe('suspended');
    expect($box->fresh()->is_active)->toBeFalse();
});

test('reactivate service action reactivates suspended mail subscription and mailboxes', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $reviewer = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $plan = MailPlan::where('slug', 'mail-basic')->firstOrFail();
    $mailSub = MailSubscription::create([
        'team_id' => $team->id,
        'plan_id' => $plan->id,
        'status' => 'suspended',
        'total_storage_mb' => 1024,
        'max_mailboxes' => 5,
        'max_domains' => 1,
        'renews_at' => now()->subDays(5),
    ]);

    $box = MailMailbox::create([
        'domain_id' => 'dom_test_2',
        'team_id' => $team->id,
        'wildduck_user_id' => 'wd_usr_test_2',
        'address' => 'ceo@testcorp.com',
        'name' => 'CEO',
        'is_active' => false,
    ]);

    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => 'completed',
        'subtotal_cents' => 300,
        'total_cents' => 300,
        'currency' => 'USD',
        'customer_notes' => "Automated renewal for mail subscription #{$mailSub->id} (renewal:mail_subscription:{$mailSub->id})",
    ]);

    $invoice = Invoice::create([
        'team_id' => $team->id,
        'order_id' => $order->id,
        'user_id' => $user->id,
        'invoice_number' => 'INV-RENEW-001',
        'subtotal_cents' => 300,
        'total_cents' => 300,
        'currency' => 'USD',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $mockWildduck = Mockery::mock(WildDuckProvisioningService::class);
    $mockWildduck->shouldReceive('updateMailboxActive')->with('wd_usr_test_2', true)->andReturn(true);
    app()->instance(WildDuckProvisioningService::class, $mockWildduck);

    ReactivateServiceAction::run($invoice, $reviewer);

    expect($mailSub->fresh()->status)->toBe('active');
    expect($mailSub->fresh()->renews_at->isFuture())->toBeTrue();
    expect($box->fresh()->is_active)->toBeTrue();
});

test('caddy on-demand tls validation endpoint authorizes platform hosts and verified custom domains', function () {
    // 1. Platform subdomain should return 200
    $response = $this->get('/api/v1/domains/validate-ask?domain=app.ourdomain.com');
    $response->assertOk();

    // 2. Unregistered external domain should return 403
    $response = $this->get('/api/v1/domains/validate-ask?domain=unauthorized-hacker-domain.com');
    $response->assertStatus(403);

    // 3. Verified custom domain should return 200
    $team = Team::factory()->create();
    CustomDomain::create([
        'team_id' => $team->id,
        'domain' => 'clientdomain.com',
        'status' => CustomDomain::STATUS_ACTIVE,
        'verified_at' => now(),
    ]);

    $response = $this->get('/api/v1/domains/validate-ask?domain=clientdomain.com');
    $response->assertOk();

    // 4. Missing domain parameter should return 400
    $response = $this->get('/api/v1/domains/validate-ask');
    $response->assertStatus(400);
});

