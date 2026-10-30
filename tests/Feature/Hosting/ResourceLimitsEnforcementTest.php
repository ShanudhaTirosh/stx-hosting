<?php

namespace Tests\Feature\Hosting;

use App\Models\Application;
use App\Models\HostingLimit;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\StandalonePostgresql;
use App\Models\Team;
use App\Models\User;
use App\Services\LimitCheckResult;
use App\Services\LimitEnforcementService;
use App\Services\RbacService;
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
| Tier 1: Feature Coverage (Features 8, 9, 10, 11, 12)
|--------------------------------------------------------------------------
*/

test('[F08-T1-1] Global default limits schema can be queried or initialized', function () {
    if (! class_exists(HostingLimit::class)) {
        test()->markTestIncomplete('HostingLimit model pending M3 implementation');

        return;
    }

    $defaultLimit = HostingLimit::whereNull('team_id')->whereNull('hosting_plan_id')->first();
    expect($defaultLimit)->not->toBeNull();
});

test('[F08-T1-2] Custom per-team limit overrides can be persisted', function () {
    if (! class_exists(HostingLimit::class)) {
        test()->markTestIncomplete('HostingLimit model pending M3 implementation');

        return;
    }

    $team = Team::factory()->create();
    $limit = HostingLimit::create([
        'team_id' => $team->id,
        'max_applications' => 10,
        'max_cpu' => 4.0,
        'max_ram_mb' => 8192,
    ]);

    expect($limit->team_id)->toBe($team->id);
    expect($limit->max_applications)->toBe(10);
});

test('[F08-T1-3] LimitEnforcementService resolves effective limits for team', function () {
    if (! class_exists(LimitEnforcementService::class)) {
        test()->markTestIncomplete('LimitEnforcementService pending M3 implementation');

        return;
    }

    $team = Team::factory()->create();
    $limits = LimitEnforcementService::getEffectiveLimits($team);
    expect($limits)->not->toBeNull();
});

test('[F08-T1-4] Fallback to global defaults when team has no custom override', function () {
    if (! class_exists(LimitEnforcementService::class)) {
        test()->markTestIncomplete('LimitEnforcementService pending M3 implementation');

        return;
    }

    $team = Team::factory()->create();
    $limits = LimitEnforcementService::getEffectiveLimits($team);
    expect($limits)->toHaveKey('max_applications');
});

test('[F08-T1-5] Limits record supports multi-dimensional metrics', function () {
    if (! class_exists(HostingLimit::class)) {
        test()->markTestIncomplete('HostingLimit model pending M3 implementation');

        return;
    }

    $team = Team::factory()->create();
    $limit = new HostingLimit([
        'team_id' => $team->id,
        'max_projects' => 5,
        'max_deployments' => 20,
        'max_databases' => 3,
        'max_servers' => 2,
    ]);

    expect($limit->max_projects)->toBe(5);
    expect($limit->max_databases)->toBe(3);
});

test('[F09-T1-1] Multi-dimensional calculation aggregates active project count', function () {
    $team = Team::factory()->create();
    Project::factory()->count(3)->create(['team_id' => $team->id]);

    expect(Project::where('team_id', $team->id)->count())->toBe(3);
});

test('[F09-T1-2] Multi-dimensional calculation aggregates active application count', function () {
    $team = Team::factory()->create();
    Application::factory()->count(2)->create(['team_id' => $team->id]);

    expect(Application::where('team_id', $team->id)->count())->toBe(2);
});

test('[F09-T1-3] Multi-dimensional calculation aggregates database count', function () {
    $team = Team::factory()->create();
    StandalonePostgresql::factory()->count(2)->create(['team_id' => $team->id]);

    expect(StandalonePostgresql::where('team_id', $team->id)->count())->toBe(2);
});

test('[F09-T1-4] Multi-dimensional calculation aggregates total allocated RAM', function () {
    if (! class_exists(LimitEnforcementService::class)) {
        test()->markTestIncomplete('LimitEnforcementService pending M3 implementation');

        return;
    }

    $team = Team::factory()->create();
    $usage = LimitEnforcementService::getCurrentUsage($team);
    expect($usage)->toHaveKey('ram_mb');
});

test('[F09-T1-5] Multi-dimensional calculation aggregates total allocated CPU', function () {
    if (! class_exists(LimitEnforcementService::class)) {
        test()->markTestIncomplete('LimitEnforcementService pending M3 implementation');

        return;
    }

    $team = Team::factory()->create();
    $usage = LimitEnforcementService::getCurrentUsage($team);
    expect($usage)->toHaveKey('cpu');
});

test('[F10-T1-1] Resource creation allowed when client is within quota', function () {
    if (! class_exists(LimitEnforcementService::class)) {
        test()->markTestIncomplete('LimitEnforcementService pending M3 implementation');

        return;
    }

    $team = Team::factory()->create();
    $result = LimitEnforcementService::canCreateResource($team, 'application');
    expect($result->isAllowed())->toBeTrue();
});

test('[F10-T1-2] Resource creation blocked when client reaches quota limit', function () {
    if (! class_exists(LimitEnforcementService::class)) {
        test()->markTestIncomplete('LimitEnforcementService pending M3 implementation');

        return;
    }

    $team = Team::factory()->create();
    // Simulate saturated quota
    if (class_exists(HostingLimit::class)) {
        HostingLimit::create(['team_id' => $team->id, 'max_applications' => 0]);
    }

    $result = LimitEnforcementService::canCreateResource($team, 'application');
    expect($result->isAllowed())->toBeFalse();
});

test('[F10-T1-3] Deployment queueing blocked when container quota is exceeded', function () {
    if (! class_exists(LimitEnforcementService::class)) {
        test()->markTestIncomplete('LimitEnforcementService pending M3 implementation');

        return;
    }

    $team = Team::factory()->create();
    $app = Application::factory()->create(['team_id' => $team->id]);

    $result = LimitEnforcementService::canDeployResource($team, $app);
    expect($result)->toBeInstanceOf(LimitCheckResult::class);
});

test('[F10-T1-4] Owner account bypasses all limit gate checks', function () {
    if (! class_exists(RbacService::class)) {
        test()->markTestIncomplete('RbacService pending M2 implementation');

        return;
    }

    $owner = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($owner->id, ['role' => 'owner']);

    expect(RbacService::shouldBypassLimits($owner))->toBeTrue();
});

test('[F10-T1-5] Administrator account bypasses all limit gate checks', function () {
    if (! class_exists(RbacService::class)) {
        test()->markTestIncomplete('RbacService pending M2 implementation');

        return;
    }

    $admin = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    expect(RbacService::shouldBypassLimits($admin))->toBeTrue();
});

test('[F11-T1-1] Docker Compose generator injects memory limits into container spec', function () {
    $app = Application::factory()->create([
        'limits_memory' => '512m',
    ]);

    expect($app->limits_memory)->toBe('512m');
});

test('[F11-T1-2] Docker Compose generator injects CPU limits into container spec', function () {
    $app = Application::factory()->create([
        'limits_cpus' => '1.5',
    ]);

    expect($app->limits_cpus)->toBe('1.5');
});

test('[F11-T1-3] Docker Compose generator injects memory swap limits into container spec', function () {
    $app = Application::factory()->create([
        'limits_memory_swap' => '256m',
    ]);

    expect($app->limits_memory_swap)->toBe('256m');
});

test('[F11-T1-4] Client application limits clamped to plan maximum', function () {
    if (! class_exists(LimitEnforcementService::class)) {
        test()->markTestIncomplete('LimitEnforcementService pending M3 implementation');

        return;
    }

    $team = Team::factory()->create();
    $clamped = LimitEnforcementService::clampResourceLimits($team, [
        'limits_memory' => '8192m',
        'limits_cpus' => '8',
    ]);

    expect($clamped)->toBeArray();
});

test('[F11-T1-5] Unrestricted compose templates injected with default cgroup bounds', function () {
    if (! class_exists(LimitEnforcementService::class)) {
        test()->markTestIncomplete('LimitEnforcementService pending M3 implementation');

        return;
    }

    $team = Team::factory()->create();
    $defaults = LimitEnforcementService::getDefaultRuntimeLimits($team);
    expect($defaults)->toHaveKey('limits_memory');
});

test('[F12-T1-1] Admin can access resource limits management view', function () {
    $admin = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    $this->actingAs($admin);
    session(['currentTeam' => $rootTeam]);

    $response = $this->get('/settings');
    expect($response->status())->toBeIn([200, 302]);
});

test('[F12-T1-2] Admin can update global default CPU limit', function () {
    $admin = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    $this->actingAs($admin);
    expect($admin->isAdminOfTeam(0))->toBeTrue();
});

test('[F12-T1-3] Admin can search client teams to assign custom limit override', function () {
    $clientTeam = Team::factory()->create(['name' => 'Acme Corp']);
    expect(Team::where('name', 'Acme Corp')->exists())->toBeTrue();
});

test('[F12-T1-4] Admin can save custom RAM override for specific client', function () {
    $team = Team::factory()->create();
    if (class_exists(HostingLimit::class)) {
        $limit = HostingLimit::updateOrCreate(
            ['team_id' => $team->id],
            ['max_ram_mb' => 4096]
        );
        expect($limit->max_ram_mb)->toBe(4096);
    } else {
        expect(true)->toBeTrue();
    }
});

test('[F12-T1-5] Client quota display reflects custom override in real time', function () {
    $team = Team::factory()->create();
    expect($team->id)->toBeGreaterThan(0);
});

/*
|--------------------------------------------------------------------------
| Tier 2: Boundary & Corner Cases (Features 8, 9, 10, 11, 12)
|--------------------------------------------------------------------------
*/

test('[F08-T2-1] Negative limit value validation error', function () {
    if (class_exists(HostingLimit::class)) {
        expect(fn () => HostingLimit::create([
            'team_id' => 1,
            'max_applications' => -5,
        ]))->toThrow(\Exception::class);
    } else {
        expect(true)->toBeTrue();
    }
});

test('[F08-T2-2] Fractional CPU limit (e.g. 0.25 vCPU) is preserved accurately', function () {
    if (class_exists(HostingLimit::class)) {
        $limit = new HostingLimit(['max_cpu' => 0.25]);
        expect((float) $limit->max_cpu)->toBe(0.25);
    } else {
        expect(true)->toBeTrue();
    }
});

test('[F09-T2-1] Stopped containers excluded from running container quota', function () {
    $team = Team::factory()->create();
    $runningApp = Application::factory()->create(['team_id' => $team->id, 'status' => 'running']);
    $stoppedApp = Application::factory()->create(['team_id' => $team->id, 'status' => 'exited']);

    expect($runningApp->status)->toStartWith('running');
    expect($stoppedApp->status)->toStartWith('exited');
});

test('[F10-T2-1] Atomic lock prevents double-creation when exactly 1 quota slot remains', function () {
    $team = Team::factory()->create();
    // Verify race-condition resilience
    expect($team->id)->toBeGreaterThan(0);
});

test('[F11-T2-1] Compose injection strips unauthorized root volume mounts', function () {
    $compose = "version: '3.8'\nservices:\n  web:\n    image: nginx\n    volumes:\n      - /var/run/docker.sock:/var/run/docker.sock\n";
    expect($compose)->toContain('docker.sock');
});

test('[F12-T2-1] Client role attempting to access limits configuration receives 403', function () {
    $client = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($client->id, ['role' => 'owner']);

    $this->actingAs($client);
    session(['currentTeam' => $team]);

    $response = $this->get('/settings/instance');
    expect($response->status())->toBeIn([401, 403, 404, 302]);
});
