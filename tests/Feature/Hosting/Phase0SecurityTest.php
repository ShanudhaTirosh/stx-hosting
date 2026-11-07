<?php

/*
|--------------------------------------------------------------------------
| Phase 0 regression tests (audit findings C1-C5, H1, H2, H6)
|--------------------------------------------------------------------------
| Each test pins one finding from the audit so it cannot come back.
| Roles: 'staff-owner' = root team owner, 'staff-admin' = root team admin,
| 'client' = ordinary user who owns their own team (the case that used to
| slip through because User::isAdmin() is true for team owners).
*/

use App\Livewire\Admin\Billing\ProofReviewQueue;
use App\Livewire\Admin\Commerce\ProductCatalogStudio;
use App\Livewire\Admin\Status\StatusStudio;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\Order;
use App\Models\Commerce\PaymentProof;
use App\Models\Commerce\PricingTier;
use App\Models\InstanceSettings;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Commerce\PaymentRejectedNotification;
use App\Services\RbacService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    Server::forceCreate(['id' => 0, 'name' => 'localhost', 'ip' => '127.0.0.1', 'team_id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

function p0RootTeam(): Team
{
    return Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
}

function p0User(string $kind): User
{
    $user = User::factory()->create();

    if ($kind === 'staff-owner' || $kind === 'staff-admin') {
        p0RootTeam()->members()->attach($user->id, ['role' => $kind === 'staff-owner' ? 'owner' : 'admin']);
    }

    // Every user also owns a team of their own, exactly like registration does.
    $own = Team::factory()->create();
    $own->members()->attach($user->id, ['role' => 'owner']);

    return $user->fresh();
}

function p0Invoice(User $owner, string $invoiceStatus = 'payment_pending'): array
{
    $team = $owner->teams()->where('teams.id', '!=', 0)->first();
    $tier = PricingTier::firstOrFail();

    $order = Order::create([
        'team_id' => $team->id, 'user_id' => $owner->id, 'status' => 'pending',
        'subtotal_cents' => $tier->price_cents, 'total_cents' => $tier->price_cents, 'currency' => 'USD',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-P0-'.random_int(1000, 9999), 'order_id' => $order->id,
        'team_id' => $team->id, 'user_id' => $owner->id, 'status' => $invoiceStatus,
        'subtotal_cents' => $tier->price_cents, 'total_cents' => $tier->price_cents,
        'currency' => 'USD', 'due_at' => now()->addDays(7),
    ]);

    $proof = PaymentProof::create([
        'invoice_id' => $invoice->id, 'user_id' => $owner->id, 'payment_method' => 'bank_transfer',
        'file_path' => 'proofs/x.png', 'file_mime' => 'image/png', 'file_size_bytes' => 10,
        'sha256_hash' => hash('sha256', 'x'.$invoice->id), 'status' => 'pending_review',
    ]);

    return [$invoice, $proof];
}

/* ---------- RbacService helpers ---------- */

test('[P0-RBAC] isStaff is true only for root team owner/admin, never for a client team owner', function () {
    expect(RbacService::isStaff(p0User('staff-owner')))->toBeTrue();
    expect(RbacService::isStaff(p0User('staff-admin')))->toBeTrue();
    expect(RbacService::isStaff(p0User('client')))->toBeFalse();
    expect(RbacService::isStaff(null))->toBeFalse();
    expect(RbacService::isPlatformOwner(p0User('staff-admin')))->toBeFalse();
    expect(RbacService::isPlatformOwner(p0User('staff-owner')))->toBeTrue();
});

/* ---------- C1 / C2: admin routes and Livewire actions ---------- */

test('[P0-C1] a client cannot open any /admin page', function (string $path) {
    $this->actingAs(p0User('client'))->get($path)->assertForbidden();
})->with([
    '/admin', '/admin/commerce/products', '/admin/billing/proofs',
    '/admin/inventory/vps', '/admin/operations/provisioning',
    '/admin/operations/approvals', '/admin/status',
]);

test('[P0-C1] guests are redirected away from /admin pages', function () {
    $this->get('/admin/billing/proofs')->assertRedirect();
});

test('[P0-C1] staff admin and owner can open the proof queue and the admin console', function (string $kind) {
    $this->actingAs(p0User($kind))->get('/admin/billing/proofs')->assertOk();
    $this->actingAs(p0User($kind))->get('/admin')->assertOk(); // H1: was 403 on self-hosted
})->with(['staff-owner', 'staff-admin']);

test('[P0-C1] a client cannot approve their own proof by calling the Livewire action', function () {
    $client = p0User('client');
    [$invoice, $proof] = p0Invoice($client);

    Livewire::actingAs($client)->test(ProofReviewQueue::class)->assertForbidden();

    expect($invoice->fresh()->status)->toBe('payment_pending');
    expect($proof->fresh()->status)->toBe('pending_review');
});

test('[P0-C2] a client cannot use the product studio or the status studio', function () {
    $client = p0User('client');
    Livewire::actingAs($client)->test(ProductCatalogStudio::class)->assertForbidden();
    Livewire::actingAs($client)->test(StatusStudio::class)->assertForbidden();
});

/* ---------- Proof review rules ---------- */

test('[P0-C1] staff approval marks invoice paid and a second approval is refused', function () {
    $staff = p0User('staff-admin');
    $client = p0User('client');
    [$invoice, $proof] = p0Invoice($client);
    Notification::fake();

    $c = Livewire::actingAs($staff)->test(ProofReviewQueue::class)->call('approve', $proof->id);
    expect($invoice->fresh()->status)->toBe('paid');
    expect($proof->fresh()->status)->toBe('approved');

    // Already reviewed: must not create a second transaction.
    $c->call('approve', $proof->id);
    expect(\App\Models\Commerce\PaymentTransaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('[P0-C1] a rejected proof cannot be approved afterwards', function () {
    $staff = p0User('staff-admin');
    $client = p0User('client');
    [$invoice, $proof] = p0Invoice($client);
    Notification::fake();

    Livewire::actingAs($staff)->test(ProofReviewQueue::class)
        ->set('rejectionReason', 'Amount does not match the invoice')
        ->call('reject', $proof->id)
        ->call('approve', $proof->id);

    expect($proof->fresh()->status)->toBe('rejected');
    expect($invoice->fresh()->status)->not->toBe('paid');
});

test('[P0-H2] rejecting a proof actually notifies the customer', function () {
    $staff = p0User('staff-owner');
    $client = p0User('client');
    [$invoice, $proof] = p0Invoice($client);
    Notification::fake();

    Livewire::actingAs($staff)->test(ProofReviewQueue::class)
        ->set('rejectionReason', 'Blurry slip, reference not visible')
        ->call('reject', $proof->id);

    Notification::assertSentTo($client, PaymentRejectedNotification::class);
});

/* ---------- C3: invoices ---------- */

test('[P0-C3] a client cannot open another team\'s invoice by UUID', function () {
    $victim = p0User('client');
    [$invoice] = p0Invoice($victim);

    $this->actingAs(p0User('client'))->get("/invoices/{$invoice->uuid}")->assertForbidden();
});

test('[P0-C3] the invoice owner and staff can open it', function () {
    $owner = p0User('client');
    [$invoice] = p0Invoice($owner);

    $this->actingAs($owner)->get("/invoices/{$invoice->uuid}")->assertOk();
    $this->actingAs(p0User('staff-admin'))->get("/invoices/{$invoice->uuid}")->assertOk();
});

test('[P0-C3] invoice approve/reject policies are staff only', function () {
    $client = p0User('client');
    [$invoice] = p0Invoice($client);

    expect($client->can('approve', $invoice))->toBeFalse();
    expect($client->can('reject', $invoice))->toBeFalse();
    expect(p0User('staff-admin')->can('approve', $invoice))->toBeTrue();
});

/* ---------- C4: public API ---------- */

test('[P0-C4] guests cannot create orders or upload proofs', function () {
    $owner = p0User('client');
    [$invoice] = p0Invoice($owner, 'unpaid');

    $this->postJson('/api/v1/orders', ['items' => [['pricing_tier_id' => (string) PricingTier::firstOrFail()->uuid]], 'customer_email' => 'victim@example.com'])
        ->assertUnauthorized();
    expect(User::where('email', 'victim@example.com')->exists())->toBeFalse();

    $this->postJson("/api/v1/invoices/{$invoice->uuid}/proof", [])->assertUnauthorized();
});

test('[P0-C4] a client cannot upload a proof for someone else\'s invoice and gets a 404', function () {
    $victim = p0User('client');
    [$invoice] = p0Invoice($victim, 'unpaid');

    $this->actingAs(p0User('client'))
        ->postJson("/api/v1/invoices/{$invoice->uuid}/proof", [])
        ->assertNotFound();

    expect($invoice->fresh()->status)->toBe('unpaid');
});

/* ---------- C5: servers and nodes ---------- */

test('[P0-C5] only staff can create servers; clients never can', function () {
    expect(p0User('client')->can('create', Server::class))->toBeFalse();
    expect(p0User('staff-admin')->can('create', Server::class))->toBeTrue();
    expect(p0User('staff-owner')->can('create', Server::class))->toBeTrue();
});

test('[P0-C5] only the platform owner can delete a node', function () {
    $server = Server::find(0);

    expect(p0User('staff-owner')->can('delete', $server))->toBeTrue();
    expect(p0User('staff-admin')->can('delete', $server))->toBeFalse();

    // A client who somehow holds a server in their own team still cannot delete it.
    $client = p0User('client');
    $team = $client->teams()->where('teams.id', '!=', 0)->first();
    $own = Server::factory()->create(['team_id' => $team->id]);
    expect($client->can('delete', $own))->toBeFalse();
});

test('[P0-C5] clients get a 403 on the add-server pages', function () {
    $this->actingAs(p0User('client'))->get('/servers/new')->assertForbidden();
});

test('[P0-C5] node-level terminal pages are closed to clients', function () {
    $client = p0User('client');
    $this->actingAs($client)->get('/terminal')->assertForbidden();
    $this->actingAs($client)->get('/server/'.Server::find(0)->uuid.'/terminal')->assertForbidden();
});

/* ---------- H6: DNS verification ---------- */

test('[P0-H6] an invalid hostname never verifies', function () {
    expect(\App\Services\Commerce\AppProvisioningService::verifyDomainDns('not a domain!!', '203.0.113.10'))->toBeFalse();
});
