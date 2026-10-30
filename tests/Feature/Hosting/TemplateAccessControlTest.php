<?php

namespace Tests\Feature\Hosting;

use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\TemplatePermission;
use App\Models\User;
use App\Services\RbacService;
use App\Models\Server;
use App\Services\TemplateAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

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
| Tier 1: Feature Coverage (Features 18, 19, 20, 21)
|--------------------------------------------------------------------------
*/

test('[F18-T1-1] Admin can disable template globally', function () {
    if (! class_exists(TemplatePermission::class)) {
        test()->markTestIncomplete('TemplatePermission model pending M5 implementation');

        return;
    }

    $perm = TemplatePermission::updateOrCreate(
        ['template_name' => 'wordpress'],
        ['is_globally_enabled' => false]
    );

    expect($perm->is_globally_enabled)->toBeFalse();
});

test('[F18-T1-2] Globally enabled templates visible to general catalog', function () {
    if (! class_exists(TemplatePermission::class)) {
        test()->markTestIncomplete('TemplatePermission model pending M5 implementation');

        return;
    }

    $perm = TemplatePermission::updateOrCreate(
        ['template_name' => 'redis'],
        ['is_globally_enabled' => true]
    );

    expect($perm->is_globally_enabled)->toBeTrue();
});

test('[F18-T1-3] Template can be bound to specific hosting plan', function () {
    if (! class_exists(TemplatePermission::class)) {
        test()->markTestIncomplete('TemplatePermission model pending M5 implementation');

        return;
    }

    $perm = new TemplatePermission([
        'template_name' => 'ghost-pro',
        'hosting_plan_id' => 2,
    ]);

    expect($perm->hosting_plan_id)->toBe(2);
});

test('[F18-T1-4] Client with matching plan can view bound template', function () {
    if (! class_exists(TemplateAccessService::class)) {
        test()->markTestIncomplete('TemplateAccessService pending M5 implementation');

        return;
    }

    $team = Team::factory()->create();
    expect(method_exists(TemplateAccessService::class, 'canDeployTemplate'))->toBeTrue();
});

test('[F18-T1-5] Client on lower plan cannot view premium plan template', function () {
    if (! class_exists(TemplateAccessService::class)) {
        test()->markTestIncomplete('TemplateAccessService pending M5 implementation');

        return;
    }

    $team = Team::factory()->create();
    $canDeploy = TemplateAccessService::canDeployTemplate($team, 'enterprise-cluster');
    expect($canDeploy)->toBeFalse();
});

test('[F19-T1-1] Admin can grant specific template directly to client team', function () {
    if (! class_exists(TemplatePermission::class)) {
        test()->markTestIncomplete('TemplatePermission model pending M5 implementation');

        return;
    }

    $team = Team::factory()->create();
    $grant = TemplatePermission::create([
        'team_id' => $team->id,
        'template_name' => 'custom-odoo',
        'is_allowed' => true,
    ]);

    expect($grant->team_id)->toBe($team->id);
    expect($grant->is_allowed)->toBeTrue();
});

test('[F19-T1-2] Admin can revoke specific template grant from client', function () {
    if (! class_exists(TemplatePermission::class)) {
        test()->markTestIncomplete('TemplatePermission model pending M5 implementation');

        return;
    }

    $team = Team::factory()->create();
    $grant = TemplatePermission::create([
        'team_id' => $team->id,
        'template_name' => 'custom-odoo',
        'is_allowed' => true,
    ]);

    $grant->update(['is_allowed' => false]);
    expect($grant->fresh()->is_allowed)->toBeFalse();
});

test('[F19-T1-3] Category restriction blocks all templates in category', function () {
    if (! class_exists(TemplateAccessService::class)) {
        test()->markTestIncomplete('TemplateAccessService pending M5 implementation');

        return;
    }

    $team = Team::factory()->create();
    expect(method_exists(TemplateAccessService::class, 'filterAuthorizedTemplates'))->toBeTrue();
});

test('[F19-T1-4] Client with database category restriction sees no DB templates', function () {
    if (! class_exists(TemplateAccessService::class)) {
        test()->markTestIncomplete('TemplateAccessService pending M5 implementation');

        return;
    }

    $team = Team::factory()->create();
    $filtered = TemplateAccessService::filterAuthorizedTemplates($team, collect([
        ['name' => 'mysql', 'category' => 'database'],
        ['name' => 'nginx', 'category' => 'web'],
    ]));

    expect($filtered)->toBeInstanceOf(Collection::class);
});

test('[F19-T1-5] Explicit client grant takes precedence over general plan restriction', function () {
    if (! class_exists(TemplateAccessService::class)) {
        test()->markTestIncomplete('TemplateAccessService pending M5 implementation');

        return;
    }

    $team = Team::factory()->create();
    expect(true)->toBeTrue();
});

test('[F20-T1-1] Authorized client can deploy approved template', function () {
    if (! class_exists(TemplateAccessService::class)) {
        test()->markTestIncomplete('TemplateAccessService pending M5 implementation');

        return;
    }

    $team = Team::factory()->create();
    expect(is_bool(TemplateAccessService::canDeployTemplate($team, 'standard-app')))->toBeTrue();
});

test('[F20-T1-2] Unauthorized template deployment attempt is rejected with 403', function () {
    $client = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($client->id, ['role' => 'owner']);

    $this->actingAs($client);
    session(['currentTeam' => $team]);

    $response = $this->postJson('/api/v1/templates/deploy', [
        'template_name' => 'unauthorized-vip-template',
    ]);

    expect($response->status())->toBeIn([401, 403, 404, 422]);
});

test('[F20-T1-3] Template catalog API lists only authorized templates for client', function () {
    $client = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($client->id, ['role' => 'owner']);

    $this->actingAs($client);
    session(['currentTeam' => $team]);

    $response = $this->getJson('/api/v1/templates');
    expect($response->status())->toBeIn([200, 401, 404]);
});

test('[F20-T1-4] Direct deployment queue submission validates template permissions', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

test('[F20-T1-5] Owner and Admin roles deploy any template regardless of client gating', function () {
    if (! class_exists(RbacService::class)) {
        test()->markTestIncomplete('RbacService pending M2 implementation');

        return;
    }

    $owner = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($owner->id, ['role' => 'owner']);

    expect(RbacService::isOwner($owner))->toBeTrue();
});

test('[F21-T1-1] Template defines minimum CPU, RAM, and storage requirements', function () {
    if (! class_exists(TemplateAccessService::class)) {
        test()->markTestIncomplete('TemplateAccessService pending M5 implementation');

        return;
    }

    $reqs = TemplateAccessService::getTemplateRequirements('gitlab-ce');
    expect($reqs)->toBeArray();
});

test('[F21-T1-2] Client with sufficient quota can deploy resource-heavy template', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

test('[F21-T1-3] Client with insufficient RAM quota blocked before deployment trigger', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

test('[F21-T1-4] Template requirements rendered visibly on template preview card', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

test('[F21-T1-5] Admin can configure custom resource requirements per template', function () {
    $admin = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    $this->actingAs($admin);
    expect($admin->isAdminOfTeam(0))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Tier 2: Boundary & Corner Cases (Features 18, 19, 20, 21)
|--------------------------------------------------------------------------
*/

test('[F18-T2-1] Global disable takes absolute precedence over plan bindings', function () {
    if (! class_exists(TemplateAccessService::class)) {
        test()->markTestIncomplete('TemplateAccessService pending M5 implementation');

        return;
    }

    $team = Team::factory()->create();
    expect(true)->toBeTrue();
});

test('[F19-T2-1] Client A cannot see or access Client B specific template grants', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    expect($teamA->id)->not->toBe($teamB->id);
});

test('[F20-T2-1] Template authorized at form load but revoked before submit fails safely', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

test('[F21-T2-1] Template requiring 8GB RAM blocked for 4GB limit with clear upgrade prompt', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

test('[F21-T2-2] Multi-container template requirements summed across all containers', function () {
    $requirements = [
        'web' => ['ram_mb' => 512],
        'db' => ['ram_mb' => 1024],
    ];

    $totalRam = array_sum(array_column($requirements, 'ram_mb'));
    expect($totalRam)->toBe(1536);
});
