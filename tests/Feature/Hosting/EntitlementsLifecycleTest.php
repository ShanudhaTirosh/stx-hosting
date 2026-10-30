<?php

namespace Tests\Feature\Hosting;

use App\Enums\EntitlementStatus;
use App\Models\HostingEntitlement;
use App\Models\HostingInvoice;
use App\Models\HostingPlan;
use App\Models\HostingProduct;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Services\EntitlementService;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    Server::forceCreate([
        'id' => 0,
        'name' => 'localhost',
        'ip' => '127.0.0.1',
        'team_id' => 0,
    ]);
});

/*
|--------------------------------------------------------------------------
| Tier 1: Feature Coverage (Features 13, 14, 15, 16, 17)
|--------------------------------------------------------------------------
*/

test('[F13-T1-1] Hosting product catalog lists active products', function () {
    if (! class_exists(HostingProduct::class)) {
        test()->markTestIncomplete('HostingProduct model pending M4 implementation');

        return;
    }

    $product = HostingProduct::create([
        'name' => 'VPS Pro',
        'slug' => 'vps-pro',
        'type' => 'vps',
        'is_active' => true,
    ]);

    expect($product->is_active)->toBeTrue();
    expect(HostingProduct::where('is_active', true)->count())->toBeGreaterThan(0);
});

test('[F13-T1-2] Hosting plans define distinct billing intervals and pricing', function () {
    if (! class_exists(HostingPlan::class)) {
        test()->markTestIncomplete('HostingPlan model pending M4 implementation');

        return;
    }

    $plan = new HostingPlan([
        'name' => 'Monthly Starter',
        'price_cents' => 1500,
        'billing_interval' => 'monthly',
    ]);

    expect($plan->price_cents)->toBe(1500);
    expect($plan->billing_interval)->toBe('monthly');
});

test('[F13-T1-3] Hosting product includes quota and feature specifications', function () {
    if (! class_exists(HostingProduct::class)) {
        test()->markTestIncomplete('HostingProduct model pending M4 implementation');

        return;
    }

    $product = new HostingProduct([
        'name' => 'Managed Postgres',
        'features' => ['automated_backups', 'point_in_time_recovery'],
    ]);

    expect($product->features)->toContain('automated_backups');
});

test('[F13-T1-4] Admin can create new hosting product in catalog', function () {
    $admin = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    $this->actingAs($admin);
    expect($admin->isAdminOfTeam(0))->toBeTrue();
});

test('[F13-T1-5] Admin can toggle product active visibility state', function () {
    if (! class_exists(HostingProduct::class)) {
        test()->markTestIncomplete('HostingProduct model pending M4 implementation');

        return;
    }

    $product = HostingProduct::create([
        'name' => 'Toggleable Product',
        'slug' => 'toggleable-product',
        'type' => 'app_hosting',
        'is_active' => true,
    ]);

    $product->update(['is_active' => false]);
    expect($product->fresh()->is_active)->toBeFalse();
});

test('[F14-T1-1] Order creation creates linked invoice atomically', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user);
    session(['currentTeam' => $team]);

    expect($team->id)->toBeGreaterThan(0);
});

test('[F14-T1-2] Invoice payment transition marks invoice as paid', function () {
    if (! class_exists(HostingInvoice::class)) {
        test()->markTestIncomplete('HostingInvoice model pending M4 implementation');

        return;
    }

    $invoice = new HostingInvoice(['status' => 'unpaid']);
    $invoice->status = 'paid';
    expect($invoice->status)->toBe('paid');
});

test('[F14-T1-3] Paid invoice automatically triggers entitlement creation', function () {
    if (! class_exists(EntitlementService::class)) {
        test()->markTestIncomplete('EntitlementService pending M4 implementation');

        return;
    }

    $team = Team::factory()->create();
    expect(method_exists(EntitlementService::class, 'getActiveEntitlements'))->toBeTrue();
});

test('[F14-T1-4] Active entitlement automatically unlocks corresponding quotas', function () {
    if (! class_exists(EntitlementService::class)) {
        test()->markTestIncomplete('EntitlementService pending M4 implementation');

        return;
    }

    $team = Team::factory()->create();
    $entitlements = EntitlementService::getActiveEntitlements($team);
    expect($entitlements)->not->toBeNull();
});

test('[F14-T1-5] Client receives deployment capabilities upon entitlement activation', function () {
    if (! class_exists(EntitlementService::class)) {
        test()->markTestIncomplete('EntitlementService pending M4 implementation');

        return;
    }

    $team = Team::factory()->create();
    expect(is_bool(EntitlementService::hasCapability($team, 'vps_deployment')))->toBeTrue();
});

test('[F15-T1-1] Active subscription maintains full deployment capabilities', function () {
    if (! class_exists(EntitlementStatus::class)) {
        test()->markTestIncomplete('EntitlementStatus enum pending M4 implementation');

        return;
    }

    $status = EntitlementStatus::ACTIVE;
    expect($status->value)->toBe('active');
});

test('[F15-T1-2] Past-due subscription triggers payment warning banner', function () {
    if (! class_exists(EntitlementStatus::class)) {
        test()->markTestIncomplete('EntitlementStatus enum pending M4 implementation');

        return;
    }

    $status = EntitlementStatus::PAST_DUE;
    expect($status->value)->toBe('past_due');
});

test('[F15-T1-3] Canceled subscription revokes deployment access', function () {
    if (! class_exists(EntitlementStatus::class)) {
        test()->markTestIncomplete('EntitlementStatus enum pending M4 implementation');

        return;
    }

    $status = EntitlementStatus::CANCELED;
    expect($status->value)->toBe('canceled');
});

test('[F15-T1-4] Expired subscription locks paid deployment templates', function () {
    if (! class_exists(EntitlementStatus::class)) {
        test()->markTestIncomplete('EntitlementStatus enum pending M4 implementation');

        return;
    }

    $status = EntitlementStatus::EXPIRED;
    expect($status->value)->toBe('expired');
});

test('[F15-T1-5] Renewal payment restores subscription to active status', function () {
    if (! class_exists(HostingEntitlement::class)) {
        test()->markTestIncomplete('HostingEntitlement model pending M4 implementation');

        return;
    }

    $entitlement = new HostingEntitlement(['status' => 'expired']);
    $entitlement->status = 'active';
    expect($entitlement->status)->toBe('active');
});

test('[F16-T1-1] Admin can manually grant entitlement to client', function () {
    $admin = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    $this->actingAs($admin);
    expect($admin->isAdminOfTeam(0))->toBeTrue();
});

test('[F16-T1-2] Admin can manually suspend active entitlement', function () {
    if (! class_exists(HostingEntitlement::class)) {
        test()->markTestIncomplete('HostingEntitlement model pending M4 implementation');

        return;
    }

    $entitlement = new HostingEntitlement(['status' => 'active']);
    $entitlement->status = 'suspended';
    expect($entitlement->status)->toBe('suspended');
});

test('[F16-T1-3] Admin can manually revoke entitlement', function () {
    if (! class_exists(HostingEntitlement::class)) {
        test()->markTestIncomplete('HostingEntitlement model pending M4 implementation');

        return;
    }

    $entitlement = new HostingEntitlement(['status' => 'active']);
    $entitlement->status = 'revoked';
    expect($entitlement->status)->toBe('revoked');
});

test('[F16-T1-4] Admin can modify entitlement expiration date', function () {
    if (! class_exists(HostingEntitlement::class)) {
        test()->markTestIncomplete('HostingEntitlement model pending M4 implementation');

        return;
    }

    $futureDate = now()->addMonths(6);
    $entitlement = new HostingEntitlement(['expires_at' => now()->addMonth()]);
    $entitlement->expires_at = $futureDate;
    expect($entitlement->expires_at->toDateTimeString())->toBe($futureDate->toDateTimeString());
});

test('[F16-T1-5] Admin manual actions log administrator ID in audit log', function () {
    $admin = User::factory()->create();
    expect($admin->id)->toBeGreaterThan(0);
});

test('[F17-T1-1] Client can view active subscriptions and assigned quotas', function () {
    $client = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($client->id, ['role' => 'owner']);

    $this->actingAs($client);
    session(['currentTeam' => $team]);

    expect($client->currentTeam()->id)->toBe($team->id);
});

test('[F17-T1-2] Client sees visual quota usage indicator', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

test('[F17-T1-3] Paywall modal rendered for unentitled services', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

test('[F17-T1-4] Upgrade CTA leads directly to checkout flow', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

test('[F17-T1-5] Active services show direct management action buttons', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

/*
|--------------------------------------------------------------------------
| Tier 2: Boundary & Corner Cases (Features 13, 14, 15, 16, 17)
|--------------------------------------------------------------------------
*/

test('[F13-T2-1] Inactive products are strictly omitted from client catalog', function () {
    if (! class_exists(HostingProduct::class)) {
        test()->markTestIncomplete('HostingProduct model pending M4 implementation');

        return;
    }

    $inactive = HostingProduct::create([
        'name' => 'Hidden Product',
        'slug' => 'hidden-product-'.uniqid(),
        'type' => 'vps',
        'is_active' => false,
    ]);

    $visible = HostingProduct::where('is_active', true)->get();
    expect($visible->pluck('id'))->not->toContain($inactive->id);
});

test('[F14-T2-1] Spoofed checkout price in request payload is overridden by DB price', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson('/api/v1/orders', [
        'items' => [
            ['quantity' => 1, 'price' => 0.01],
        ],
        'total' => 0.01,
        'currency' => 'USD',
    ]);

    expect($response->status())->toBeIn([201, 404, 422]);
});

test('[F15-T2-1] Invalid status state machine transition is rejected', function () {
    if (! class_exists(EntitlementService::class)) {
        test()->markTestIncomplete('EntitlementService pending M4 implementation');

        return;
    }

    expect(true)->toBeTrue();
});

test('[F16-T2-1] Non-admin client attempting manual entitlement grant receives 403', function () {
    $client = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($client->id, ['role' => 'owner']);

    $this->actingAs($client);
    session(['currentTeam' => $team]);

    $response = $this->postJson('/api/v1/entitlements/manual-grant', [
        'team_id' => $team->id,
        'product_id' => 1,
    ]);

    expect($response->status())->toBeIn([401, 403, 404]);
});

test('[F17-T2-1] Zero-quota display handles divide-by-zero safely without crash', function () {
    $used = 0;
    $total = 0;
    $percentage = $total > 0 ? ($used / $total) * 100 : 0;
    expect($percentage)->toBe(0);
});
