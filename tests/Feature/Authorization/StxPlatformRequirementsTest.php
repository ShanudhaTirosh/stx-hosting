<?php

use App\Enums\ApplicationDeploymentStatus;
use App\Enums\Role;
use App\Jobs\ApplicationDeploymentJob;
use App\Livewire\Dashboard\ActiveDeployments;
use App\Livewire\Server\Create as ServerCreate;
use App\Livewire\Team\Member as TeamMemberComponent;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\TemplatePermission;
use App\Models\User;
use App\Services\RbacService;
use App\Services\TemplateAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::create(['id' => 0]);

    $this->rootTeam = Team::find(0) ?? Team::factory()->create(['id' => 0]);

    $this->owner = User::factory()->create();
    $this->rootTeam->members()->attach($this->owner->id, ['role' => Role::OWNER->value]);

    $this->admin = User::factory()->create();
    $this->rootTeam->members()->attach($this->admin->id, ['role' => Role::ADMIN->value]);

    $this->clientTeam = Team::factory()->create();
    $this->client = User::factory()->create();
    $this->clientTeam->members()->attach($this->client->id, ['role' => Role::MEMBER->value]);

    $this->privateKey = PrivateKey::factory()->create([
        'team_id' => $this->rootTeam->id,
    ]);

    $this->server = Server::factory()->create([
        'team_id' => $this->rootTeam->id,
        'private_key_id' => $this->privateKey->id,
        'ip' => '1.2.3.4',
        'port' => 22,
        'user' => 'root',
    ]);

    $this->destination = $this->server->standaloneDockers->first()
        ?? StandaloneDocker::factory()->create([
            'server_id' => $this->server->id,
            'network' => 'custom-net-1',
        ]);
});

test('platform owner account is protected from admin modifications or removal', function () {
    $this->actingAs($this->admin);
    session(['currentTeam' => $this->rootTeam]);

    // Admin attempts to demote owner to member
    Livewire::test(TeamMemberComponent::class, ['member' => $this->owner])
        ->call('makeReadonly')
        ->assertDispatched('error', 'The platform Owner account cannot be modified or demoted.');

    // Admin attempts to remove owner
    Livewire::test(TeamMemberComponent::class, ['member' => $this->owner])
        ->call('remove')
        ->assertDispatched('error', 'The platform Owner account cannot be removed.');

    // Owner role is untouched in root team
    expect($this->owner->teams()->where('teams.id', 0)->first()->pivot->role)->toBe(Role::OWNER->value);
});

test('non-owner admin cannot promote another user to owner', function () {
    $this->actingAs($this->admin);
    session(['currentTeam' => $this->rootTeam]);

    $targetUser = User::factory()->create();
    $this->rootTeam->members()->attach($targetUser->id, ['role' => Role::MEMBER->value]);

    Livewire::test(TeamMemberComponent::class, ['member' => $targetUser])
        ->call('makeOwner')
        ->assertDispatched('error', 'Only the platform Owner can transfer or assign the Owner role.');
});

test('server creation renders direct manual connection flow without cloud provider cards', function () {
    $this->actingAs($this->admin);
    session(['currentTeam' => $this->rootTeam]);

    Livewire::test(ServerCreate::class)
        ->assertSee('Connect Server')
        ->assertSee('Connect a server')
        ->assertSee('IP address or domain')
        ->assertDontSee('alt="Hetzner"', false)
        ->assertDontSee('alt="Vultr"', false)
        ->assertDontSee('DigitalOcean');
});

test('all authenticated users can access web terminal and file manager', function () {
    $this->actingAs($this->client);
    session(['currentTeam' => $this->clientTeam]);

    expect(auth()->user()->can('canAccessTerminal'))->toBeTrue();
});

test('admin-initiated deployment sets status to pending approval awaiting owner', function () {
    Queue::fake();

    $this->actingAs($this->admin);
    session(['currentTeam' => $this->rootTeam]);

    $app = Application::factory()->create([
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
    ]);

    $result = queue_application_deployment(
        application: $app,
        deployment_uuid: 'dep-test-admin-approval-1',
        commit: 'main',
    );

    expect($result['status'])->toBe('pending_approval');

    $deployment = ApplicationDeploymentQueue::where('deployment_uuid', 'dep-test-admin-approval-1')->first();
    expect($deployment)->not->toBeNull();
    expect($deployment->status)->toBe(ApplicationDeploymentStatus::PENDING_APPROVAL->value);
});

test('owner can approve pending deployment from active deployments dashboard', function () {
    Queue::fake();

    $app = Application::factory()->create([
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
    ]);

    $deployment = ApplicationDeploymentQueue::create([
        'application_id' => $app->id,
        'application_name' => $app->name,
        'server_id' => $this->server->id,
        'server_name' => $this->server->name,
        'destination_id' => $this->destination->id,
        'deployment_uuid' => 'dep-test-owner-approval-2',
        'deployment_url' => '/app/test/deployment/dep-test-owner-approval-2',
        'status' => ApplicationDeploymentStatus::PENDING_APPROVAL->value,
    ]);

    $this->actingAs($this->owner);
    session(['currentTeam' => $this->rootTeam]);

    Livewire::test(ActiveDeployments::class)
        ->call('approveDeployment', $deployment->id)
        ->assertDispatched('success', 'Deployment approved and dispatched.');

    expect($deployment->fresh()->status)->toBe(ApplicationDeploymentStatus::IN_PROGRESS->value);
    Queue::assertPushed(ApplicationDeploymentJob::class);
});

test('templates can be restricted for clients via TemplateAccessService', function () {
    $this->actingAs($this->client);
    session(['currentTeam' => $this->clientTeam]);

    // Explicitly disallow a template for client team
    TemplatePermission::create([
        'team_id' => $this->clientTeam->id,
        'template_name' => 'wordpress',
        'is_allowed' => false,
    ]);

    expect(TemplateAccessService::canDeployTemplate($this->clientTeam, 'wordpress'))->toBeFalse();

    // Owner and admin bypass restrictions
    expect(RbacService::shouldBypassLimits($this->owner))->toBeTrue();
    expect(RbacService::shouldBypassLimits($this->admin))->toBeTrue();
});
