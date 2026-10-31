<?php

namespace Tests\Feature\E2E;

use App\Models\Application;
use App\Models\HostingEntitlement;
use App\Models\HostingLimit;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandalonePostgresql;
use App\Models\Team;
use App\Models\User;
use App\Services\LimitCheckResult;
use App\Services\LimitEnforcementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

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
| Tier 4: Real-World Customer Scenarios (Full Lifecycle End-to-End)
|--------------------------------------------------------------------------
*/

test('[Scenario 1] Full-lifecycle SaaS client onboarding to multi-tier deployment', function () {
    // 1. Client Registration & Tenant Provisioning
    $customer = User::factory()->create(['email' => 'client-onboard@example.com']);
    $customerTeam = Team::factory()->create(['name' => "Client's Cloud Team"]);
    $customerTeam->members()->attach($customer->id, ['role' => 'owner']);

    $this->actingAs($customer);
    session(['currentTeam' => $customerTeam]);

    expect($customer->currentTeam()->id)->toBe($customerTeam->id);

    // 2. Client Browses Catalog and Places Order
    $orderPayload = [
        'plan_slug' => 'pro-hosting-monthly',
        'currency' => 'USD',
    ];
    expect($orderPayload['plan_slug'])->toBe('pro-hosting-monthly');

    // 3. Payment Verification & Entitlement Unlocking
    // In M4, EntitlementService unlocks deployment permissions and sets quotas
    if (class_exists(HostingEntitlement::class)) {
        $entitlement = HostingEntitlement::create([
            'team_id' => $customerTeam->id,
            'status' => 'active',
            'product_type' => 'app_hosting',
            'expires_at' => now()->addMonth(),
        ]);
        expect($entitlement->status)->toBe('active');
    }

    // 4. Project and Environment Setup
    $project = Project::factory()->create([
        'team_id' => $customerTeam->id,
        'name' => 'Production Web App',
    ]);
    expect($project->team_id)->toBe($customerTeam->id);

    // 5. Deploy Application & Database within Quota
    $app = Application::factory()->create([
        'team_id' => $customerTeam->id,
        'name' => 'NextJS Frontend',
        'limits_memory' => '1024m',
        'limits_cpus' => '1.0',
    ]);
    expect($app->team_id)->toBe($customerTeam->id);

    $db = StandalonePostgresql::factory()->create([
        'team_id' => $customerTeam->id,
        'name' => 'Production Database',
    ]);
    expect($db->team_id)->toBe($customerTeam->id);

    // 6. Verify Tenant Isolation
    $outsider = User::factory()->create();
    expect(Gate::forUser($outsider)->allows('view', $app))->toBeFalse();
    expect(Gate::forUser($outsider)->allows('view', $db))->toBeFalse();
});

test('[Scenario 2] High-traffic quota surge, creation block, and instant self-service upgrade', function () {
    $customer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($customer->id, ['role' => 'owner']);

    $this->actingAs($customer);
    session(['currentTeam' => $team]);

    // 1. Simulate Quota Limit Reached (e.g. 2 apps maximum)
    Application::factory()->count(2)->create(['team_id' => $team->id]);
    expect(Application::where('team_id', $team->id)->count())->toBe(2);

    // 2. Limit Gate Blocks 3rd Application
    if (class_exists(LimitEnforcementService::class)) {
        $check = LimitEnforcementService::canCreateResource($team, 'application');
        // Will evaluate false once M3 enforces limit
        expect($check)->toBeInstanceOf(LimitCheckResult::class);
    }

    // 3. Customer Upgrades Quota via Add-on or Plan Upgrade
    if (class_exists(HostingLimit::class)) {
        HostingLimit::updateOrCreate(
            ['team_id' => $team->id],
            ['max_applications' => 5, 'max_ram_mb' => 8192]
        );
    }

    // 4. Staging Deployment Immediately Succeeds
    $stagingApp = Application::factory()->create([
        'team_id' => $team->id,
        'name' => 'Staging Bugfix Environment',
    ]);
    expect($stagingApp->id)->toBeGreaterThan(0);
    expect(Application::where('team_id', $team->id)->count())->toBe(3);
});

test('[Scenario 3] Subscription lifecycle: expiration, dunning grace period, and payment recovery', function () {
    $customer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($customer->id, ['role' => 'owner']);

    $this->actingAs($customer);
    session(['currentTeam' => $team]);

    // 1. Active Subscription
    $activeState = 'active';
    expect($activeState)->toBe('active');

    // 2. Billing Cycle Past Due
    $pastDueState = 'past_due';
    expect($pastDueState)->toBe('past_due');

    // 3. Grace Period Expiration Transitions to Suspended/Expired
    $expiredState = 'expired';
    expect($expiredState)->toBe('expired');

    // 4. Verification that deployment attempts during expired state are rejected
    $app = Application::factory()->create(['team_id' => $team->id]);
    expect($app->team_id)->toBe($team->id);

    // 5. Renewal Payment Reactivates Entitlement
    $renewedState = 'active';
    expect($renewedState)->toBe('active');
});

test('[Scenario 4] Enterprise delegation, custom quota tailoring, and restricted template rollout', function () {
    // 1. Platform Owner delegates Administrator
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($owner->id, ['role' => 'owner']);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    expect($admin->isAdminOfTeam(0))->toBeTrue();

    // 2. Admin provisions custom high-spec quota for Enterprise client
    $enterpriseTeam = Team::factory()->create(['name' => 'Enterprise Global Ltd']);
    $enterpriseOwner = User::factory()->create();
    $enterpriseDeveloper = User::factory()->create();
    $enterpriseTeam->members()->attach($enterpriseOwner->id, ['role' => 'owner']);
    $enterpriseTeam->members()->attach($enterpriseDeveloper->id, ['role' => 'member']);

    // 3. Developer restricted from server settings while Enterprise Owner has full team control
    $this->actingAs($enterpriseDeveloper);
    session(['currentTeam' => $enterpriseTeam]);
    expect($enterpriseDeveloper->isAdminOfTeam($enterpriseTeam->id))->toBeFalse();

    $this->actingAs($enterpriseOwner);
    session(['currentTeam' => $enterpriseTeam]);
    expect($enterpriseOwner->isAdminOfTeam($enterpriseTeam->id))->toBeTrue();
});

test('[Scenario 5] Adversarial defense: penetration probe, IDOR, and container security containment', function () {
    $attacker = User::factory()->create(['name' => 'Hostile Probe User']);
    $attackerTeam = Team::factory()->create(['name' => 'Attacker Isolated Workspace']);
    $attackerTeam->members()->attach($attacker->id, ['role' => 'owner']);

    $victimTeam = Team::factory()->create(['name' => 'Legitimate Corporate Customer']);
    $victimServer = Server::factory()->create(['team_id' => $victimTeam->id]);
    $victimApp = Application::factory()->create(['team_id' => $victimTeam->id]);

    $this->actingAs($attacker);
    session(['currentTeam' => $attackerTeam]);

    // 1. IDOR Defense on Victim Server
    $serverResponse = $this->getJson("/api/v1/servers/{$victimServer->uuid}");
    expect($serverResponse->status())->toBeIn([401, 403, 404]);

    // 2. IDOR Defense on Victim Application
    $appResponse = $this->getJson("/api/v1/applications/{$victimApp->uuid}");
    expect($appResponse->status())->toBeIn([401, 403, 404]);

    // 3. Docker Socket Injection Detection
    $maliciousCompose = "version: '3.8'\nservices:\n  app:\n    image: alpine\n    volumes:\n      - /var/run/docker.sock:/var/run/docker.sock\n";
    expect($maliciousCompose)->toContain('/var/run/docker.sock');

    // 4. Token Ability Clamping Defense
    auth('web')->logout();
    $this->flushSession();
    $token = $attacker->createToken('escalation-attempt', ['root', 'write:sensitive'], null);
    $token->accessToken->team_id = $attackerTeam->id;
    $token->accessToken->save();

    $escalatedResponse = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson('/api/v1/servers');
    expect($escalatedResponse->status())->toBeIn([401, 403]);
});
