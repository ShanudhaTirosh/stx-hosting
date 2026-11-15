<?php

use App\Livewire\Portal\Teams\TeamList;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->updateOrCreate(['id' => 0], [
        'fqdn' => 'https://test.stx.cloud',
        'smtp_enabled' => false,
    ]));

    $this->user = User::factory()->create(['name' => 'Test Client', 'email' => 'client@example.com']);
    $this->team = Team::factory()->create([
        'name' => 'Acme Personal',
        'personal_team' => true,
        'seat_limit' => 5,
    ]);
    $this->team->members()->attach($this->user, ['role' => 'owner']);

    $this->actingAs($this->user);
    session(['currentTeam' => $this->team]);
});

test('client user can access portal teams and create team', function () {
    $response = $this->get(route('portal.teams.index'));
    $response->assertOk();

    Livewire::test(TeamList::class)
        ->set('name', 'Alpha DevOps Team')
        ->set('description', 'Client team for test deployments')
        ->call('createTeam')
        ->assertHasNoErrors();

    $createdTeam = Team::where('name', 'Alpha DevOps Team')->first();
    expect($createdTeam)->not->toBeNull();
    expect($createdTeam->personal_team)->toBeFalse();
    expect($createdTeam->members()->where('users.id', $this->user->id)->exists())->toBeTrue();
    expect($createdTeam->userRole($this->user))->toBe('owner');
});

test('sentinel root team cannot be deleted', function () {
    DB::table('teams')->updateOrInsert(['id' => 0], [
        'name' => 'Root Team',
        'personal_team' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $rootTeam = Team::find(0);

    expect($rootTeam->canBeDeletedBy($this->user))->toBeFalse();

    expect(fn () => $rootTeam->delete())->toThrow(Exception::class);
});

test('non-staff client cannot access admin teams', function () {
    $response = $this->get(route('admin.teams'));
    $response->assertForbidden();
});

test('platform staff can access admin teams', function () {
    DB::table('teams')->updateOrInsert(['id' => 0], [
        'name' => 'Root Team',
        'personal_team' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $rootTeam = Team::find(0);

    $staffUser = User::factory()->create(['email' => 'staff@example.com']);
    DB::table('team_user')->insert([
        'team_id' => 0,
        'user_id' => $staffUser->id,
        'role' => 'admin',
    ]);

    $staffUser->two_factor_confirmed_at = now();
    $staffUser->save();

    $this->actingAs($staffUser);
    session(['currentTeam' => $rootTeam]);

    $response = $this->get(route('admin.teams'));
    $response->assertOk();
});

test('team owner can transfer ownership safely', function () {
    $developer = User::factory()->create(['email' => 'dev@example.com']);

    $team = Team::factory()->create(['name' => 'Transfer Test Team']);
    $team->members()->attach($this->user->id, ['role' => 'owner']);
    $team->members()->attach($developer->id, ['role' => 'member']);

    expect($team->userRole($this->user))->toBe('owner');
    expect($team->userRole($developer))->toBe('member');

    $team->transferOwnership($developer, $this->user);

    $team->refresh();
    expect($team->userRole($this->user))->toBe('admin');
    expect($team->userRole($developer))->toBe('owner');
});

test('invitation accept for user attaches member with access', function () {
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);

    $uuid = (string) Str::uuid();
    $invitation = TeamInvitation::create([
        'team_id' => $this->team->id,
        'invited_by_user_id' => $this->user->id,
        'target_user_id' => $invitee->id,
        'uuid' => $uuid,
        'email' => strtolower($invitee->email),
        'role' => 'member',
        'access' => ['console' => true, 'start_stop' => true],
        'max_uses' => 1,
        'uses_count' => 0,
        'expires_at' => now()->addDays(7),
        'link' => 'https://example.com/invites/'.$uuid,
    ]);

    $accepted = $invitation->acceptFor($invitee);
    expect($accepted)->toBeTrue();

    $this->team->refresh();
    expect($this->team->members->contains('id', $invitee->id))->toBeTrue();
    expect($this->team->userRole($invitee))->toBe('member');
});
