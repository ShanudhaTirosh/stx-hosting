<?php

use App\Models\InstanceSettings;
use App\Models\OauthSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config()->set('app.maintenance.store', 'array');

    InstanceSettings::forceCreate(['id' => 0]);
    Once::flush();
    OauthSetting::create(['provider' => 'authentik']);

    $this->rootTeam = Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false, 'show_boarding' => false]);
});

function actingAsRootTeamMemberWithRole(string $role): User
{
    $team = Team::find(0);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->teams()->detach();
    $user->teams()->attach($team, ['role' => $role]);
    Team::query()->update(['show_boarding' => false]);
    Cache::flush();

    session(['currentTeam' => $team]);
    test()->actingAs($user);

    return $user;
}

it('forbids team members from the cloud tokens page', function () {
    actingAsRootTeamMemberWithRole('member');

    $this->get(route('security.cloud-tokens'))->assertForbidden();
});

it('shows the cloud tokens page to team admins and owners', function (string $role) {
    actingAsRootTeamMemberWithRole($role);

    $this->get(route('security.cloud-tokens'))
        ->assertSuccessful()
        ->assertSee('No cloud tokens');
})->with(['admin', 'owner']);
