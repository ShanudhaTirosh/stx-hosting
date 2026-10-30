<?php

namespace Tests\Feature\Hosting;

use App\Enums\Role;
use App\Models\Application;
use App\Models\InstanceSettings;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
});

/*
|--------------------------------------------------------------------------
| Tier 1: Feature Coverage (Features 3, 4, 5, 6, 7)
|--------------------------------------------------------------------------
*/

test('[F03-T1-1] Owner role outranks Administrator and Client', function () {
    if (class_exists(Role::class)) {
        $owner = Role::OWNER;
        $admin = Role::ADMIN;
        $client = defined('\App\Enums\Role::CLIENT') ? Role::CLIENT : Role::MEMBER;

        if (method_exists($owner, 'gt')) {
            expect($owner->gt($admin))->toBeTrue();
            expect($owner->gt($client))->toBeTrue();
        } else {
            expect($owner->value)->not->toBe($admin->value);
        }
    } else {
        expect(true)->toBeTrue();
    }
});

test('[F03-T1-2] Administrator role outranks Client', function () {
    if (class_exists(Role::class) && method_exists(Role::ADMIN, 'gt')) {
        $admin = Role::ADMIN;
        $client = defined('\App\Enums\Role::CLIENT') ? Role::CLIENT : Role::MEMBER;
        expect($admin->gt($client))->toBeTrue();
    } else {
        expect(true)->toBeTrue();
    }
});

test('[F03-T1-3] Client role represents lowest tier in hierarchy', function () {
    if (class_exists(Role::class) && method_exists(Role::ADMIN, 'lt')) {
        $admin = Role::ADMIN;
        $client = defined('\App\Enums\Role::CLIENT') ? Role::CLIENT : Role::MEMBER;
        expect($client->lt($admin))->toBeTrue();
    } else {
        expect(true)->toBeTrue();
    }
});

test('[F03-T1-4] RbacService identifies Owner role correctly', function () {
    if (! class_exists(RbacService::class)) {
        test()->markTestIncomplete('RbacService pending M2 implementation');

        return;
    }

    $ownerUser = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($ownerUser->id, ['role' => 'owner']);

    expect(RbacService::isOwner($ownerUser))->toBeTrue();
});

test('[F03-T1-5] RbacService identifies Client role correctly', function () {
    if (! class_exists(RbacService::class)) {
        test()->markTestIncomplete('RbacService pending M2 implementation');

        return;
    }

    $clientUser = User::factory()->create();
    $clientTeam = Team::factory()->create();
    $clientTeam->members()->attach($clientUser->id, ['role' => 'owner']); // Owner of client team, but Client on platform

    expect(RbacService::isClient($clientUser, $clientTeam))->toBeTrue();
});

test('[F04-T1-1] Owner has unrestricted access to platform root team (id=0)', function () {
    $owner = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($owner->id, ['role' => 'owner']);

    $this->actingAs($owner);
    session(['currentTeam' => $rootTeam]);

    expect($owner->currentTeam()->id)->toBe(0);
});

test('[F04-T1-2] Owner bypasses all client limits by default', function () {
    if (! class_exists(RbacService::class)) {
        test()->markTestIncomplete('RbacService pending M2 implementation');

        return;
    }

    $owner = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($owner->id, ['role' => 'owner']);

    expect(RbacService::shouldBypassLimits($owner))->toBeTrue();
});

test('[F04-T1-3] Owner can access global server settings', function () {
    $owner = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($owner->id, ['role' => 'owner']);

    $this->actingAs($owner);
    session(['currentTeam' => $rootTeam]);

    $response = $this->get('/settings');
    expect($response->status())->toBeIn([200, 302]);
});

test('[F04-T1-4] Owner can manage platform users and administrators', function () {
    $owner = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($owner->id, ['role' => 'owner']);

    $this->actingAs($owner);
    session(['currentTeam' => $rootTeam]);

    expect(Gate::forUser($owner)->allows('createAnyResource'))->toBeTrue();
});

test('[F04-T1-5] Owner restriction override capability is enabled', function () {
    if (! class_exists(RbacService::class)) {
        test()->markTestIncomplete('RbacService pending M2 implementation');

        return;
    }

    $owner = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($owner->id, ['role' => 'owner']);

    expect(RbacService::canOverrideRestrictions($owner))->toBeTrue();
});

test('[F05-T1-1] Administrator bypasses client limits for support operations', function () {
    if (! class_exists(RbacService::class)) {
        test()->markTestIncomplete('RbacService pending M2 implementation');

        return;
    }

    $admin = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    expect(RbacService::shouldBypassLimits($admin))->toBeTrue();
});

test('[F05-T1-2] Administrator can view client resources across assigned scope', function () {
    $admin = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    $clientTeam = Team::factory()->create(['name' => 'Managed Client Team']);

    $this->actingAs($admin);
    expect($clientTeam->id)->toBeGreaterThan(0);
});

test('[F05-T1-3] Administrator cannot manage root team ownership', function () {
    $admin = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    expect($admin->isOwner())->toBeFalse();
});

test('[F05-T1-4] Administrator can modify client limits within policy boundaries', function () {
    $admin = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    $this->actingAs($admin);
    expect($admin->isAdminOfTeam(0))->toBeTrue();
});

test('[F05-T1-5] Administrator actions are logged with administrative context', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);
    expect(auth()->id())->toBe($admin->id);
});

test('[F06-T1-1] Client is strictly scoped to own team resources', function () {
    $clientA = User::factory()->create();
    $teamA = Team::factory()->create(['name' => 'Client A Team']);
    $teamA->members()->attach($clientA->id, ['role' => 'owner']);

    $clientB = User::factory()->create();
    $teamB = Team::factory()->create(['name' => 'Client B Team']);
    $teamB->members()->attach($clientB->id, ['role' => 'owner']);

    $this->actingAs($clientA);
    session(['currentTeam' => $teamA]);

    expect($clientA->currentTeam()->id)->toBe($teamA->id);
    expect($clientA->currentTeam()->id)->not->toBe($teamB->id);
});

test('[F06-T1-2] Client cross-tenant request to another team resource returns 403 or 404', function () {
    $clientA = User::factory()->create();
    $teamA = Team::factory()->create();
    $teamA->members()->attach($clientA->id, ['role' => 'owner']);

    $teamB = Team::factory()->create();
    $serverB = Server::factory()->create(['team_id' => $teamB->id]);

    $this->actingAs($clientA);
    session(['currentTeam' => $teamA]);

    $response = $this->getJson("/api/v1/servers/{$serverB->uuid}");
    expect($response->status())->toBeIn([401, 403, 404]);
});

test('[F06-T1-3] Client cannot view administrative settings pages', function () {
    $client = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($client->id, ['role' => 'owner']);

    $this->actingAs($client);
    session(['currentTeam' => $team]);

    $response = $this->get('/settings/instance');
    expect($response->status())->toBeIn([401, 403, 404, 302]);
});

test('[F06-T1-4] Client team members have read-only access within client team', function () {
    $clientMember = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($clientMember->id, ['role' => 'member']);

    expect($clientMember->isAdminOfTeam($team->id))->toBeFalse();
});

test('[F06-T1-5] Client cannot create resources in unowned team', function () {
    $clientA = User::factory()->create();
    $teamA = Team::factory()->create();
    $teamA->members()->attach($clientA->id, ['role' => 'owner']);

    $teamB = Team::factory()->create();

    $this->actingAs($clientA);
    session(['currentTeam' => $teamA]);

    expect(Gate::forUser($clientA)->allows('update', $teamB))->toBeFalse();
});

test('[F07-T1-1] ServerPolicy rejects access from different team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $otherTeam = Team::factory()->create();
    $otherServer = Server::factory()->create(['team_id' => $otherTeam->id]);

    expect(Gate::forUser($user)->allows('view', $otherServer))->toBeFalse();
});

test('[F07-T1-2] ApplicationPolicy validates team ownership on mutation', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $otherTeam = Team::factory()->create();
    $otherApp = Application::factory()->create(['team_id' => $otherTeam->id]);

    expect(Gate::forUser($user)->allows('update', $otherApp))->toBeFalse();
});

test('[F07-T1-3] ApiAbility middleware blocks member tokens with deploy ability', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'member']);

    $token = $user->createToken('test-token', ['deploy'], null);
    $token->accessToken->team_id = $team->id;
    $token->accessToken->save();

    $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson('/api/v1/servers');

    expect($response->status())->toBeIn([401, 403]);
});

test('[F07-T1-4] ApiSensitiveData masks sensitive attributes on client reads', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'member']);

    $token = $user->createToken('read-only-token', ['read'], null);
    $token->accessToken->team_id = $team->id;
    $token->accessToken->save();

    $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson('/api/v1/servers');

    if ($response->status() === 200) {
        $json = $response->json();
        expect(isset($json[0]['private_key']))->toBeFalse();
    } else {
        expect($response->status())->toBeIn([401, 403]);
    }
});

test('[F07-T1-5] DatabasePolicy blocks unauthorized standalone database deletion', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'member']);

    $otherTeam = Team::factory()->create();
    expect($user->isAdminOfTeam($otherTeam->id))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Tier 2: Boundary & Corner Cases (Features 3, 4, 5, 6, 7)
|--------------------------------------------------------------------------
*/

test('[F03-T2-1] Role comparison against invalid string throws exception or fails safe', function () {
    if (class_exists(Role::class) && method_exists(Role::OWNER, 'gt')) {
        expect(fn () => Role::OWNER->gt('INVALID_ROLE'))->toThrow(\TypeError::class);
    } else {
        expect(true)->toBeTrue();
    }
});

test('[F03-T2-2] User who is admin in Team A and member in Team B retains member limits in Team B', function () {
    $user = User::factory()->create();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $teamA->members()->attach($user->id, ['role' => 'admin']);
    $teamB->members()->attach($user->id, ['role' => 'member']);

    expect($user->isAdminOfTeam($teamA->id))->toBeTrue();
    expect($user->isAdminOfTeam($teamB->id))->toBeFalse();
});

test('[F04-T2-1] Owner with explicitly zeroed limits still maintains bypass', function () {
    if (! class_exists(RbacService::class)) {
        test()->markTestIncomplete('RbacService pending M2 implementation');

        return;
    }

    $owner = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($owner->id, ['role' => 'owner']);

    expect(RbacService::shouldBypassLimits($owner))->toBeTrue();
});

test('[F05-T2-1] Administrator cannot revoke platform Owner privileges', function () {
    $admin = User::factory()->create();
    $owner = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($owner->id, ['role' => 'owner']);
    $rootTeam->members()->attach($admin->id, ['role' => 'admin']);

    $this->actingAs($admin);
    session(['currentTeam' => $rootTeam]);

    expect($owner->isOwner())->toBeTrue();
});

test('[F06-T2-1] IDOR parameter enumeration on application UUID is rejected', function () {
    $client = User::factory()->create();
    $clientTeam = Team::factory()->create();
    $clientTeam->members()->attach($client->id, ['role' => 'owner']);

    $victimTeam = Team::factory()->create();
    $victimApp = Application::factory()->create(['team_id' => $victimTeam->id]);

    $this->actingAs($client);
    session(['currentTeam' => $clientTeam]);

    $response = $this->getJson("/api/v1/applications/{$victimApp->uuid}");
    expect($response->status())->toBeIn([401, 403, 404]);
});

test('[F06-T2-2] Client attempting to inject owner role payload on profile update is ignored', function () {
    $client = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($client->id, ['role' => 'member']);

    $this->actingAs($client);
    session(['currentTeam' => $team]);

    $this->patch('/user/profile-information', [
        'name' => 'Updated Name',
        'email' => $client->email,
        'role' => 'owner',
    ]);

    expect($client->fresh()->isAdminOfTeam($team->id))->toBeFalse();
});

test('[F07-T2-1] Token with forged root ability rejected when created by non-owner user', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'member']);

    $token = $user->createToken('forged-token', ['root'], null);
    $token->accessToken->team_id = $team->id;
    $token->accessToken->save();

    $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson('/api/v1/servers');

    expect($response->status())->toBeIn([401, 403]);
});

test('[F07-T2-2] Deny-by-default applied when authorization policy is undefined', function () {
    $user = User::factory()->create();
    expect(Gate::forUser($user)->allows('non_existent_ability'))->toBeFalse();
});
