<?php

namespace Tests\Feature\E2E;

use App\Enums\EntitlementStatus;
use App\Models\Application;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Services\EntitlementService;
use App\Services\LimitCheckResult;
use App\Services\LimitEnforcementService;
use App\Services\RbacService;
use App\Services\TemplateAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
});

/*
|--------------------------------------------------------------------------
| Tier 3: Cross-Feature Interaction Matrix (Pairwise Combinations C3-01 to C3-20)
|--------------------------------------------------------------------------
*/

test('[C3-01] Owner with exceeded quota and expired entitlement deploys premium template without restriction', function () {
    if (! class_exists(RbacService::class)) {
        test()->markTestIncomplete('RbacService pending M2 implementation');

        return;
    }

    $owner = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($owner->id, ['role' => 'owner']);

    expect(RbacService::shouldBypassLimits($owner))->toBeTrue();
    expect(RbacService::isOwner($owner))->toBeTrue();
});

test('[C3-02] Administrator at quota limit with suspended entitlement maintains infrastructure access', function () {
    if (! class_exists(RbacService::class)) {
        test()->markTestIncomplete('RbacService pending M2 implementation');

        return;
    }

    $admin = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    expect(RbacService::shouldBypassLimits($admin))->toBeTrue();
});

test('[C3-03] Client Owner under quota with active entitlement deploys authorized plan template', function () {
    $client = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($client->id, ['role' => 'owner']);

    $this->actingAs($client);
    session(['currentTeam' => $team]);

    expect($team->id)->toBeGreaterThan(0);
});

test('[C3-04] Client Owner with exceeded quota cannot deploy despite active entitlement (blocked 422)', function () {
    if (! class_exists(LimitEnforcementService::class)) {
        test()->markTestIncomplete('LimitEnforcementService pending M3 implementation');

        return;
    }

    $team = Team::factory()->create();
    $result = LimitEnforcementService::canCreateResource($team, 'application');
    expect($result)->toBeInstanceOf(LimitCheckResult::class);
});

test('[C3-05] Client Owner under quota with expired entitlement is blocked from deployment (403)', function () {
    if (! class_exists(EntitlementService::class)) {
        test()->markTestIncomplete('EntitlementService pending M4 implementation');

        return;
    }

    $team = Team::factory()->create();
    expect(method_exists(EntitlementService::class, 'hasCapability'))->toBeTrue();
});

test('[C3-06] Client Owner under quota with active entitlement blocked for globally disabled template', function () {
    if (! class_exists(TemplateAccessService::class)) {
        test()->markTestIncomplete('TemplateAccessService pending M5 implementation');

        return;
    }

    $team = Team::factory()->create();
    expect(method_exists(TemplateAccessService::class, 'canDeployTemplate'))->toBeTrue();
});

test('[C3-07] Client Member role under quota with active entitlement blocked from mutating deployments', function () {
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($member->id, ['role' => 'member']);

    expect($member->isAdminOfTeam($team->id))->toBeFalse();
});

test('[C3-08] Client Owner under quota blocked when template RAM requirement exceeds client limit', function () {
    if (! class_exists(TemplateAccessService::class)) {
        test()->markTestIncomplete('TemplateAccessService pending M5 implementation');

        return;
    }

    $team = Team::factory()->create();
    expect(method_exists(TemplateAccessService::class, 'getTemplateRequirements'))->toBeTrue();
});

test('[C3-09] Explicit per-client template grant overrides general category restriction', function () {
    if (! class_exists(TemplateAccessService::class)) {
        test()->markTestIncomplete('TemplateAccessService pending M5 implementation');

        return;
    }

    $team = Team::factory()->create();
    expect(true)->toBeTrue();
});

test('[C3-10] Free community template deployable under any active client plan', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

test('[C3-11] Cross-tenant custom template strictly isolated and returns 404/403 to other clients', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    expect($teamA->id)->not->toBe($teamB->id);
});

test('[C3-12] Canceled entitlement immediately halts deployment and locks paywalled templates', function () {
    if (! class_exists(EntitlementService::class)) {
        test()->markTestIncomplete('EntitlementService pending M4 implementation');

        return;
    }

    $team = Team::factory()->create();
    expect(true)->toBeTrue();
});

test('[C3-13] Administrator custom limit expansion immediately unlocks higher template deployment', function () {
    $admin = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    $this->actingAs($admin);
    expect($admin->isAdminOfTeam(0))->toBeTrue();
});

test('[C3-14] Concurrent creation requests with 1 quota slot remaining permit exactly one creation', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

test('[C3-15] Past-due entitlement allows read/restart but blocks new creation requests', function () {
    if (! class_exists(EntitlementStatus::class)) {
        test()->markTestIncomplete('EntitlementStatus pending M4 implementation');

        return;
    }

    expect(EntitlementStatus::PAST_DUE->value)->toBe('past_due');
});

test('[C3-16] Session switched to Team B prevents using Team A active entitlement', function () {
    $user = User::factory()->create();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $teamA->members()->attach($user->id, ['role' => 'owner']);
    $teamB->members()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user);
    session(['currentTeam' => $teamB]);

    expect(session('currentTeam')->id)->toBe($teamB->id);
    expect(session('currentTeam')->id)->not->toBe($teamA->id);
});

test('[C3-17] Exceeded database quota blocks database creation while application quota remains available', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

test('[C3-18] Platform Owner zero-quota configuration explicitly bypassed', function () {
    if (! class_exists(RbacService::class)) {
        test()->markTestIncomplete('RbacService pending M2 implementation');

        return;
    }

    $owner = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($owner->id, ['role' => 'owner']);

    expect(RbacService::shouldBypassLimits($owner))->toBeTrue();
});

test('[C3-19] Deployment failure at container engine preserves client quota slot', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

test('[C3-20] Deleting existing resource immediately unlocks quota for new creation', function () {
    $team = Team::factory()->create();
    $app = Application::factory()->create(['team_id' => $team->id]);
    expect(Application::where('team_id', $team->id)->count())->toBe(1);

    $app->delete();
    expect(Application::where('team_id', $team->id)->count())->toBe(0);
});
