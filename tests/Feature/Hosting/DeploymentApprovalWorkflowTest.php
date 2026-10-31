<?php

use App\Enums\ApplicationDeploymentStatus;
use App\Jobs\ApplicationDeploymentJob;
use App\Livewire\Dashboard\ActiveDeployments;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate([
        'id' => 0,
        'is_registration_enabled' => true,
    ]);

    // Root team sentinel (id = 0)
    $this->rootTeam = Team::find(0) ?? Team::forceCreate([
        'id' => 0,
        'name' => 'Root Team',
        'personal_team' => false,
    ]);

    // Valid test OpenSSH private key
    $validKey = "-----BEGIN OPENSSH PRIVATE KEY-----\nb3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW\nQyNTUxOQAAACBbhpqHhqv6aI67Mj9abM3DVbmcfYhZAhC7ca4d9UCevAAAAJi/QySHv0Mk\nhwAAAAtzc2gtZWQyNTUxOQAAACBbhpqHhqv6aI67Mj9abM3DVbmcfYhZAhC7ca4d9UCevA\nAAAECBQw4jg1WRT2IGHMncCiZhURCts2s24HoDS0thHnnRKVuGmoeGq/pojrsyP1pszcNV\nuZx9iFkCELtxrh31QJ68AAAAEXNhaWxANzZmZjY2ZDJlMmRkAQIDBA==\n-----END OPENSSH PRIVATE KEY-----\n";

    $this->privateKey = PrivateKey::create([
        'uuid' => 'test-ssh-key',
        'team_id' => 0,
        'name' => 'Testing Host Key',
        'description' => 'Test SSH key',
        'private_key' => $validKey,
    ]);

    // Platform Owner: member of root team with role 'owner'
    $this->owner = User::factory()->create();
    $this->owner->teams()->attach(0, ['role' => 'owner']);

    // Platform Administrator: member of root team with role 'admin'
    $this->admin = User::factory()->create();
    $this->admin->teams()->attach(0, ['role' => 'admin']);

    // Client: non-root user
    $this->client = User::factory()->create();
    $this->clientTeam = Team::factory()->create(['name' => 'Client Team']);
    $this->client->teams()->attach($this->clientTeam->id, ['role' => 'owner']);

    // Managed server node on root team
    $this->server = Server::factory()->create([
        'team_id' => 0,
        'name' => 'Managed Production Node 1',
        'private_key_id' => $this->privateKey->id,
    ]);

    $this->destination = StandaloneDocker::where('server_id', $this->server->id)->first();
    if (! $this->destination) {
        $this->destination = StandaloneDocker::create([
            'name' => 'default-docker',
            'network' => 'unique-net-'.Str::random(6),
            'server_id' => $this->server->id,
        ]);
    }
});

test('administrator cannot delete server nodes', function () {
    $this->actingAs($this->admin);
    session(['currentTeam' => $this->rootTeam]);

    expect(RbacService::isAdmin($this->admin))->toBeTrue()
        ->and(RbacService::isOwner($this->admin))->toBeFalse()
        ->and($this->admin->can('delete', $this->server))->toBeFalse();
});

test('owner can delete server nodes', function () {
    $this->actingAs($this->owner);
    session(['currentTeam' => $this->rootTeam]);

    expect(RbacService::isOwner($this->owner))->toBeTrue()
        ->and($this->owner->can('delete', $this->server))->toBeTrue();
});

test('administrator-initiated deployment requires owner approval and does not dispatch job immediately', function () {
    Queue::fake();

    $this->actingAs($this->admin);
    session(['currentTeam' => $this->rootTeam]);

    $application = Application::factory()->create([
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
    ]);

    $deploymentUuid = (string) Str::uuid();

    $result = queue_application_deployment(
        application: $application,
        deployment_uuid: $deploymentUuid,
    );

    expect($result['status'])->toBe('pending_approval')
        ->and($result['message'])->toContain('Awaiting platform Owner approval');

    $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $deploymentUuid)->firstOrFail();

    expect($deployment->status)->toBe(ApplicationDeploymentStatus::PENDING_APPROVAL->value)
        ->and($deployment->requested_by_user_id)->toBe($this->admin->id)
        ->and($deployment->reviewed_by_user_id)->toBeNull();

    // Verify job was NOT dispatched
    Queue::assertNotPushed(ApplicationDeploymentJob::class);
});

test('owner can approve pending deployment', function () {
    Queue::fake();

    $application = Application::factory()->create([
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
    ]);

    $deployment = ApplicationDeploymentQueue::create([
        'application_id' => $application->id,
        'application_name' => $application->name,
        'server_id' => $this->server->id,
        'server_name' => $this->server->name,
        'destination_id' => $this->destination->id,
        'deployment_uuid' => (string) Str::uuid(),
        'status' => ApplicationDeploymentStatus::PENDING_APPROVAL->value,
        'requested_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->owner);
    session(['currentTeam' => $this->rootTeam]);

    $component = Livewire::test(ActiveDeployments::class);
    $component->call('approveDeployment', $deployment->id);

    $deployment->refresh();

    expect($deployment->status)->toBe(ApplicationDeploymentStatus::IN_PROGRESS->value)
        ->and($deployment->reviewed_by_user_id)->toBe($this->owner->id)
        ->and($deployment->reviewed_at)->not->toBeNull();

    Queue::assertPushed(ApplicationDeploymentJob::class);
});

test('owner can reject pending deployment with reason', function () {
    Queue::fake();

    $application = Application::factory()->create([
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
    ]);

    $deployment = ApplicationDeploymentQueue::create([
        'application_id' => $application->id,
        'application_name' => $application->name,
        'server_id' => $this->server->id,
        'server_name' => $this->server->name,
        'destination_id' => $this->destination->id,
        'deployment_uuid' => (string) Str::uuid(),
        'status' => ApplicationDeploymentStatus::PENDING_APPROVAL->value,
        'requested_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->owner);
    session(['currentTeam' => $this->rootTeam]);

    $component = Livewire::test(ActiveDeployments::class);
    $component->call('rejectDeployment', $deployment->id, 'Security review failed for target node.');

    $deployment->refresh();

    expect($deployment->status)->toBe(ApplicationDeploymentStatus::REJECTED->value)
        ->and($deployment->reviewed_by_user_id)->toBe($this->owner->id)
        ->and($deployment->rejection_reason)->toBe('Security review failed for target node.');

    Queue::assertNotPushed(ApplicationDeploymentJob::class);
});
