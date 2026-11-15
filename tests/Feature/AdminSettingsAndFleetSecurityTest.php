<?php

use App\Models\CloudProviderToken;
use App\Models\InstanceSettings;
use App\Models\IntegrationToken;
use App\Models\OauthSetting;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Once;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config()->set('app.maintenance.store', 'array');
    config()->set('security.require_staff_2fa', false);

    InstanceSettings::forceCreate(['id' => 0]);
    Once::flush();
    OauthSetting::firstOrCreate(['provider' => 'authentik']);

    $this->rootTeam = Team::find(0) ?? Team::forceCreate([
        'id' => 0,
        'name' => 'Root Team',
        'personal_team' => false,
        'show_boarding' => false,
    ]);

    Server::forceCreate([
        'id' => 0,
        'name' => 'localhost',
        'ip' => '127.0.0.1',
        'team_id' => 0,
    ]);

    $this->clientTeam = Team::factory()->create([
        'name' => 'Client Team',
        'personal_team' => true,
        'show_boarding' => false,
    ]);
});

function createStaffMember(string $role): User
{
    $team = Team::find(0);
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
    $user->teams()->detach();
    $user->teams()->attach($team, ['role' => $role]);
    Team::query()->update(['show_boarding' => false]);
    Cache::flush();

    session(['currentTeam' => $team]);

    return $user;
}

function createClientUser(): User
{
    $team = Team::where('id', '!=', 0)->first();
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $user->teams()->detach();
    $user->teams()->attach($team, ['role' => 'owner']);
    Team::query()->update(['show_boarding' => false]);
    Cache::flush();

    session(['currentTeam' => $team]);

    return $user;
}

test('both admin and owner staff can access all admin settings routes', function (string $role) {
    $staff = createStaffMember($role);
    $this->actingAs($staff);

    $routes = [
        'admin.settings.index',
        'admin.settings.advanced',
        'admin.settings.updates',
        'admin.settings.oauth',
        'admin.settings.email',
        'admin.settings.backup',
        'admin.security.private-key.index',
        'admin.security.cloud-tokens',
        'admin.security.integration-tokens',
        'admin.security.api-tokens',
    ];

    foreach ($routes as $routeName) {
        $response = $this->get(route($routeName));
        if ($response->status() !== 200) {
            dump("FAILED ROUTE: {$routeName} returned status {$response->status()}");
        }
        $response->assertSuccessful();
    }
})->with(['admin', 'owner']);

test('both admin and owner staff can access fleet servers and terminal routes', function (string $role) {
    $staff = createStaffMember($role);
    $this->actingAs($staff);

    $this->get(route('admin.servers'))->assertSuccessful();
    $this->get(route('admin.terminal'))->assertSuccessful();
    $this->get(route('terminal'))->assertSuccessful();
})->with(['admin', 'owner']);

test('client users are strictly forbidden from admin settings, servers, and terminal routes', function () {
    $client = createClientUser();
    $this->actingAs($client);

    $forbiddenRoutes = [
        'admin.settings.index',
        'admin.settings.advanced',
        'admin.settings.updates',
        'admin.settings.oauth',
        'admin.settings.email',
        'admin.settings.backup',
        'admin.security.private-key.index',
        'admin.security.cloud-tokens',
        'admin.security.integration-tokens',
        'admin.security.api-tokens',
        'admin.servers',
        'admin.terminal',
        'terminal',
    ];

    foreach ($forbiddenRoutes as $routeName) {
        $this->get(route($routeName))
            ->assertForbidden();
    }
});

test('client users are strictly forbidden from terminal auth and session endpoints', function () {
    $client = createClientUser();
    $this->actingAs($client);

    $this->postJson(route('terminal.auth'))
        ->assertForbidden();

    $this->postJson(route('terminal.auth.ips'))
        ->assertForbidden();

    $this->postJson(route('terminal.session'), ['token' => str_repeat('a', 64)])
        ->assertForbidden();
});

test('legacy settings routes redirect staff to admin settings and forbid client users', function () {
    $staff = createStaffMember('admin');
    $this->actingAs($staff);

    $this->get('/settings')->assertRedirect(route('admin.settings.index'));
    $this->get('/settings/oauth')->assertRedirect(route('admin.settings.oauth'));
    $this->get('/settings/advanced')->assertRedirect(route('admin.settings.advanced'));
    $this->get('/settings/updates')->assertRedirect(route('admin.settings.updates'));
    $this->get('/settings/backup')->assertRedirect(route('admin.settings.backup'));
    $this->get('/settings/email')->assertRedirect(route('admin.settings.email'));

    $client = createClientUser();
    $this->actingAs($client);

    $this->get('/settings')->assertForbidden();
    $this->get('/settings/oauth')->assertForbidden();
    $this->get('/settings/advanced')->assertForbidden();
    $this->get('/settings/updates')->assertForbidden();
    $this->get('/settings/backup')->assertForbidden();
    $this->get('/settings/email')->assertForbidden();
});

test('only team owners and instance admins can view and create private keys, tokens, and credentials', function () {
    $member = User::factory()->create();
    $this->clientTeam->members()->attach($member->id, ['role' => 'member']);
    $this->actingAs($member);
    session(['currentTeam' => $this->clientTeam]);

    expect($member->can('viewAny', PrivateKey::class))->toBeFalse()
        ->and($member->can('create', PrivateKey::class))->toBeFalse()
        ->and($member->can('viewAny', CloudProviderToken::class))->toBeFalse()
        ->and($member->can('create', CloudProviderToken::class))->toBeFalse()
        ->and($member->can('viewAny', IntegrationToken::class))->toBeFalse()
        ->and($member->can('create', IntegrationToken::class))->toBeFalse()
        ->and($member->can('viewAny', PersonalAccessToken::class))->toBeFalse()
        ->and($member->can('create', PersonalAccessToken::class))->toBeFalse();

    $this->get(route('security.private-key.index'))->assertForbidden();
    $this->get(route('security.cloud-tokens'))->assertForbidden();
    $this->get(route('security.integration-tokens'))->assertForbidden();
    $this->get(route('security.api-tokens'))->assertForbidden();

    $owner = User::factory()->create();
    $this->clientTeam->members()->attach($owner->id, ['role' => 'owner']);
    $this->actingAs($owner);
    session(['currentTeam' => $this->clientTeam]);

    expect($owner->can('viewAny', PrivateKey::class))->toBeTrue()
        ->and($owner->can('create', PrivateKey::class))->toBeTrue()
        ->and($owner->can('viewAny', CloudProviderToken::class))->toBeTrue()
        ->and($owner->can('create', CloudProviderToken::class))->toBeTrue()
        ->and($owner->can('viewAny', IntegrationToken::class))->toBeTrue()
        ->and($owner->can('create', IntegrationToken::class))->toBeTrue()
        ->and($owner->can('viewAny', PersonalAccessToken::class))->toBeTrue()
        ->and($owner->can('create', PersonalAccessToken::class))->toBeTrue();

    $this->get(route('security.private-key.index'))->assertSuccessful();
    $this->get(route('security.cloud-tokens'))->assertSuccessful();
    $this->get(route('security.integration-tokens'))->assertSuccessful();
    $this->get(route('security.api-tokens'))->assertSuccessful();
});
