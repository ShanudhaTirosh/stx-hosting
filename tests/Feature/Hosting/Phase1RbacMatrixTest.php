<?php

/*
|--------------------------------------------------------------------------
| Phase 1: roles, permissions and navigation
|--------------------------------------------------------------------------
| Pins the Owner / Admin / Client matrix from config/stx-permissions.php on
| the three enforcement layers: capability helpers, routes/policies (API and
| server side) and the sidebar (UI).
*/

use App\Livewire\Admin\Commerce\ProductCatalogStudio;
use App\Models\InstanceSettings;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Services\RbacService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    Server::forceCreate(['id' => 0, 'name' => 'localhost', 'ip' => '127.0.0.1', 'team_id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

function p1User(string $role): User
{
    $user = User::factory()->create();
    if ($role === 'owner' || $role === 'admin') {
        $root = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
        $root->members()->attach($user->id, ['role' => $role]);
    }
    Team::factory()->create()->members()->attach($user->id, ['role' => 'owner']); // own team, like registration

    return $user->fresh();
}

/* ---------- capability matrix ---------- */

test('[P1-MATRIX] platformRole resolves owner, admin, client and guest', function () {
    expect(p1User('owner')->platformRole())->toBe('owner');
    expect(p1User('admin')->platformRole())->toBe('admin');
    expect(p1User('client')->platformRole())->toBe('client');
    expect(RbacService::platformRole(null))->toBe('guest');
});

test('[P1-MATRIX] capability matrix matches the approved permission table', function (string $capability, bool $owner, bool $admin, bool $client) {
    expect(RbacService::can(p1User('owner'), $capability))->toBe($owner);
    expect(RbacService::can(p1User('admin'), $capability))->toBe($admin);
    expect(RbacService::can(p1User('client'), $capability))->toBe($client);
    expect(RbacService::can(null, $capability))->toBeFalse();
})->with([
    // capability,            owner, admin, client
    ['nodes.add',             true,  true,  false],
    ['nodes.remove',          true,  false, false],
    ['nodes.migrate',         true,  true,  false],
    ['nodes.stats',           true,  true,  false],
    ['nodes.terminal',        true,  false, false],
    ['nodes.power',           true,  false, false],
    ['payments.verify',       true,  true,  false],
    ['catalog.manage',        true,  true,  false],
    ['pricing.manage',        true,  false, false],
    ['payment_methods.manage', true, false, false],
    ['platform.settings',     true,  true,  false],
    ['users.impersonate',     true,  false, false],
    ['portal.use',            true,  true,  true],
]);

test('[P1-MATRIX] unknown capabilities are denied for everyone', function () {
    expect(RbacService::can(p1User('owner'), 'does.not.exist'))->toBeFalse();
});

/* ---------- server side: routes and policies ---------- */

test('[P1-ROUTE] clients cannot open platform settings; staff can', function () {
    $this->actingAs(p1User('client'))->get('/settings')->assertRedirect();
    $this->actingAs(p1User('admin'))->get('/settings')->assertSuccessful();
    $this->actingAs(p1User('owner'))->get('/settings')->assertSuccessful();
});

test('[P1-POLICY] only the owner may power-control or delete a node', function () {
    $server = Server::find(0);

    expect(p1User('owner')->can('power', $server))->toBeTrue();
    expect(p1User('admin')->can('power', $server))->toBeFalse();
    expect(p1User('client')->can('power', $server))->toBeFalse();

    expect(p1User('owner')->can('delete', $server))->toBeTrue();
    expect(p1User('admin')->can('delete', $server))->toBeFalse();
});

test('[P1-POLICY] admin and owner can add nodes, clients cannot', function () {
    expect(p1User('owner')->can('create', Server::class))->toBeTrue();
    expect(p1User('admin')->can('create', Server::class))->toBeTrue();
    expect(p1User('client')->can('create', Server::class))->toBeFalse();
});

test('[P1-ROUTE] node-level terminal pages are owner only', function () {
    $uuid = Server::find(0)->uuid;

    foreach (['/terminal', "/server/{$uuid}/terminal"] as $path) {
        $this->actingAs(p1User('client'))->get($path)->assertForbidden();
        $this->actingAs(p1User('admin'))->get($path)->assertForbidden();
    }
    $this->actingAs(p1User('owner'))->get('/terminal')->assertSuccessful();
});

test('[P1-POLICY] resource terminals keep the upstream gate for signed-in users', function () {
    expect(p1User('client')->can('canAccessTerminal'))->toBeTrue();
});

test('[P1-STUDIO] an admin can edit products but not prices; the owner can do both', function () {
    $tier = \App\Models\Commerce\PricingTier::firstOrFail();

    Livewire::actingAs(p1User('admin'))->test(ProductCatalogStudio::class)
        ->call('editPricingTier', $tier->id)
        ->assertForbidden();

    Livewire::actingAs(p1User('admin'))->test(ProductCatalogStudio::class)
        ->call('openCreateProductModal')
        ->assertOk();

    Livewire::actingAs(p1User('owner'))->test(ProductCatalogStudio::class)
        ->call('editPricingTier', $tier->id)
        ->assertOk();
});

/* ---------- UI: the sidebar follows the same map ---------- */

test('[P1-NAV] each role sees only its own menu entries', function () {
    $client = $this->actingAs(p1User('client'))->get('/portal')->assertSuccessful();
    foreach (['Proof Reviews', 'Products &amp; Plans', 'VPS Inventory', 'Provisioning Queue', 'Admin Console', 'Registries', 'S3 Storage'] as $hidden) {
        $client->assertDontSee($hidden, false);
    }
    $client->assertSee('My Services');
    $client->assertSee('Billing &amp; Invoices', false);

    $admin = $this->actingAs(p1User('admin'))->get('/portal')->assertSuccessful();
    $admin->assertSee('Proof Reviews');
    $admin->assertSee('Provisioning Queue');
    $admin->assertDontSee('Terminal');

    $owner = $this->actingAs(p1User('owner'))->get('/portal')->assertSuccessful();
    $owner->assertSee('Proof Reviews');
    $owner->assertSee('Terminal');
});
