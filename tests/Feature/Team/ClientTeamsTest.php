<?php

use App\Enums\Role;
use App\Livewire\Team\Create;
use App\Livewire\Team\DangerZone;
use App\Livewire\Team\InviteLink;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Once;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->updateOrCreate(['id' => 0], [
        'fqdn' => 'https://test.stx.cloud',
        'smtp_enabled' => true,
        'smtp_from_address' => 'admin@example.com',
        'smtp_host' => 'coolify-mail',
        'smtp_port' => 1025,
    ]));
    Once::flush();
    Mail::fake();

    $this->owner = User::factory()->create(['name' => 'Owner User', 'email' => 'owner@example.com']);
    $this->team = Team::factory()->create([
        'name' => 'Acme Cloud',
        'seat_limit' => 5,
        'personal_team' => false,
    ]);
    $this->team->members()->attach($this->owner, ['role' => 'owner']);

    $this->actingAs($this->owner);
    session(['currentTeam' => $this->team]);
});

test('client can create a team with auto-assigned uuid and seat limit', function () {
    Livewire::test(Create::class)
        ->set('name', 'Stark Industries')
        ->set('description', 'Defense and cloud technology')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('team.index'));

    $createdTeam = Team::where('name', 'Stark Industries')->first();
    expect($createdTeam)->not->toBeNull();
    expect($createdTeam->uuid)->not->toBeNull();
    expect($createdTeam->seat_limit)->toBe(5);
    expect($createdTeam->personal_team)->toBeFalse();

    $pivot = $createdTeam->members()->where('users.id', $this->owner->id)->first()?->pivot;
    expect($pivot?->role)->toBe('owner');
});

test('role enum enforces 5-tier capability matrix', function () {
    // Developer
    $dev = Role::DEVELOPER;
    expect($dev->canDeploy())->toBeTrue();
    expect($dev->canManageDomains())->toBeTrue();
    expect($dev->canAccessServers())->toBeTrue();
    expect($dev->canViewBilling())->toBeFalse();
    expect($dev->canManageMembers())->toBeFalse();
    expect($dev->canDeleteTeam())->toBeFalse();

    // Billing
    $billing = Role::BILLING;
    expect($billing->canDeploy())->toBeFalse();
    expect($billing->canManageDomains())->toBeFalse();
    expect($billing->canAccessServers())->toBeFalse();
    expect($billing->canViewBilling())->toBeTrue();
    expect($billing->canManageBilling())->toBeTrue();
    expect($billing->canManageMembers())->toBeFalse();
    expect($billing->canDeleteTeam())->toBeFalse();

    // Viewer
    $viewer = Role::VIEWER;
    expect($viewer->canDeploy())->toBeFalse();
    expect($viewer->canManageDomains())->toBeFalse();
    expect($viewer->canAccessServers())->toBeFalse();
    expect($viewer->canViewBilling())->toBeFalse();
    expect($viewer->canManageMembers())->toBeFalse();
    expect($viewer->canDeleteTeam())->toBeFalse();

    // Admin
    $admin = Role::ADMIN;
    expect($admin->canDeploy())->toBeTrue();
    expect($admin->canManageDomains())->toBeTrue();
    expect($admin->canAccessServers())->toBeTrue();
    expect($admin->canViewBilling())->toBeTrue();
    expect($admin->canManageMembers())->toBeTrue();
    expect($admin->canDeleteTeam())->toBeFalse();

    // Owner
    $owner = Role::OWNER;
    expect($owner->canDeploy())->toBeTrue();
    expect($owner->canManageDomains())->toBeTrue();
    expect($owner->canAccessServers())->toBeTrue();
    expect($owner->canViewBilling())->toBeTrue();
    expect($owner->canManageMembers())->toBeTrue();
    expect($owner->canDeleteTeam())->toBeTrue();
});

test('user model canPerformInTeam reflects assigned role', function () {
    $devUser = User::factory()->create();
    $this->team->members()->attach($devUser, ['role' => 'developer']);

    expect($devUser->canPerformInTeam($this->team->id, 'deploy'))->toBeTrue();
    expect($devUser->canPerformInTeam($this->team->id, 'domains.manage'))->toBeTrue();
    expect($devUser->canPerformInTeam($this->team->id, 'billing.view'))->toBeFalse();
    expect($devUser->canPerformInTeam($this->team->id, 'members.manage'))->toBeFalse();

    $billingUser = User::factory()->create();
    $this->team->members()->attach($billingUser, ['role' => 'billing']);

    expect($billingUser->canPerformInTeam($this->team->id, 'billing.view'))->toBeTrue();
    expect($billingUser->canPerformInTeam($this->team->id, 'billing.manage'))->toBeTrue();
    expect($billingUser->canPerformInTeam($this->team->id, 'deploy'))->toBeFalse();
});

test('owner can generate open shareable invitation and another user can accept', function () {
    Livewire::test(InviteLink::class)
        ->set('email', '')
        ->set('role', 'developer')
        ->set('expires_in_days', 7)
        ->set('max_uses', 1)
        ->call('viaLink')
        ->assertDispatched('success')
        ->assertDispatched('refreshInvitations');

    $invitation = TeamInvitation::where('team_id', $this->team->id)->whereNull('email')->first();
    expect($invitation)->not->toBeNull();
    expect($invitation->role)->toBe('developer');
    expect($invitation->max_uses)->toBe(1);
    expect($invitation->uses_count)->toBe(0);
    expect($invitation->isShareableLink())->toBeTrue();

    // Second user accepts the invitation
    $memberB = User::factory()->create(['email' => 'devb@example.com']);
    $this->actingAs($memberB);

    $response = $this->get(route('team.invitation.accept', $invitation->uuid));
    $response->assertRedirect(route('team.index'));

    expect($this->team->members()->where('users.id', $memberB->id)->exists())->toBeTrue();
    expect($this->team->members()->where('users.id', $memberB->id)->first()->pivot->role)->toBe('developer');

    // Single use invitation was exhausted and deleted
    expect(TeamInvitation::find($invitation->id))->toBeNull();
});

test('multi-use shareable link tracks uses_count and deletes when max_uses reached', function () {
    $invitation = TeamInvitation::create([
        'team_id' => $this->team->id,
        'uuid' => 'multi-use-test-uuid',
        'email' => null,
        'role' => 'viewer',
        'link' => 'https://test.stx.cloud/invitations/multi-use-test-uuid',
        'via' => 'link',
        'max_uses' => 2,
        'uses_count' => 0,
        'expires_at' => Carbon::now()->addDays(5),
    ]);

    $user1 = User::factory()->create();
    $this->actingAs($user1);
    $this->get(route('team.invitation.accept', $invitation->uuid))->assertRedirect(route('team.index'));

    $invitation->refresh();
    expect($invitation->uses_count)->toBe(1);
    expect(TeamInvitation::find($invitation->id))->not->toBeNull();

    $user2 = User::factory()->create();
    $this->actingAs($user2);
    $this->get(route('team.invitation.accept', $invitation->uuid))->assertRedirect(route('team.index'));

    // Limit reached, invitation should now be deleted
    expect(TeamInvitation::find($invitation->id))->toBeNull();
});

test('team seat limit blocks invitation acceptance when team is full', function () {
    // Set seat limit to 2
    $this->team->update(['seat_limit' => 2]);
    $existingMember = User::factory()->create();
    $this->team->members()->attach($existingMember, ['role' => 'developer']);

    // Team now has 2 members (owner + existingMember), seat_limit is 2
    expect($this->team->hasReachedSeatLimit())->toBeTrue();

    $invitation = TeamInvitation::create([
        'team_id' => $this->team->id,
        'uuid' => 'seat-limit-test-uuid',
        'email' => null,
        'role' => 'viewer',
        'link' => 'https://test.stx.cloud/invitations/seat-limit-test-uuid',
        'via' => 'link',
        'max_uses' => 5,
        'uses_count' => 0,
        'expires_at' => Carbon::now()->addDays(3),
    ]);

    $newUser = User::factory()->create();
    $this->actingAs($newUser);

    $response = $this->get(route('team.invitation.accept', $invitation->uuid));
    $response->assertStatus(403);
});

test('owner can transfer ownership to another member in danger zone', function () {
    $successor = User::factory()->create(['name' => 'Alice Successor']);
    $this->team->members()->attach($successor, ['role' => 'admin']);

    $this->actingAs($this->owner);
    session(['currentTeam' => $this->team]);

    Livewire::test(DangerZone::class)
        ->set('selectedNewOwnerId', $successor->id)
        ->call('transferOwnership')
        ->assertHasNoErrors()
        ->assertRedirect(route('team.index'));

    $this->team->refresh();
    $newOwnerPivot = $this->team->members()->where('users.id', $successor->id)->first()->pivot;
    $demotedPivot = $this->team->members()->where('users.id', $this->owner->id)->first()->pivot;

    expect($newOwnerPivot->role)->toBe('owner');
    expect($demotedPivot->role)->toBe('admin');
});

test('member can leave team in danger zone', function () {
    $member = User::factory()->create(['name' => 'Departing Member']);
    $this->team->members()->attach($member, ['role' => 'developer']);

    $this->actingAs($member);
    session(['currentTeam' => $this->team]);

    Livewire::test(DangerZone::class)
        ->call('leaveTeam')
        ->assertHasNoErrors()
        ->assertRedirect(route('portal.index'));

    expect($this->team->members()->where('users.id', $member->id)->exists())->toBeFalse();
});
