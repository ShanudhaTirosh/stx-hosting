<?php

use App\Livewire\Dashboard;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));

    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->user->teams()->attach($this->team, ['role' => 'owner']);

    // Also attach user to root team as owner so they pass ServerPolicy::create() (isInstanceAdmin check)
    $rootTeam = Team::find(0) ?? Team::factory()->create(['id' => 0]);
    $this->user->teams()->attach($rootTeam, ['role' => 'owner']);

    $this->actingAs($this->user);
    session(['currentTeam' => $this->team]);
});

it('shows an add private key button when no private keys exist', function () {
    Livewire::test(Dashboard::class)
        ->assertSee('A private key is required')
        ->assertSee('Add an SSH private key before connecting your first server.')
        ->assertSee('Add private key')
        ->assertSee(route('security.private-key.index'), false);
});

it('shows a new server button when private keys exist but servers do not', function () {
    PrivateKey::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Deploy Key',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee('No servers yet')
        ->assertSee('Connect infrastructure for your deployments.')
        ->assertSee('New server')
        ->assertSee(route('server.create'), false)
        ->assertDontSee('A private key is required')
        ->assertDontSee('Add private key');
});

it('does not show server empty state when servers exist', function () {
    $privateKey = PrivateKey::factory()->create([
        'team_id' => $this->team->id,
    ]);

    Server::factory()->create([
        'team_id' => $this->team->id,
        'private_key_id' => $privateKey->id,
        'name' => 'Production Host',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee('Production Host')
        ->assertDontSee('A private key is required')
        ->assertDontSee('No servers yet');
});

it('hides the add private key button for members without create permission', function () {
    $rootTeam = Team::find(0) ?? Team::factory()->create(['id' => 0]);
    $member = User::factory()->create();
    $member->teams()->attach($this->team, ['role' => 'member']);
    $member->teams()->attach($rootTeam, ['role' => 'admin']);

    $this->actingAs($member);
    session(['currentTeam' => $this->team]);

    Livewire::test(Dashboard::class)
        ->assertSee('A private key is required')
        ->assertDontSee('Add private key');
});

it('shows no servers yet empty state without private key prompt for clients who cannot create servers', function () {
    $client = User::factory()->create();
    $client->teams()->attach($this->team, ['role' => 'owner']);

    $this->actingAs($client);
    session(['currentTeam' => $this->team]);

    Livewire::test(Dashboard::class)
        ->assertSee('No servers yet')
        ->assertDontSee('A private key is required')
        ->assertDontSee('Add an SSH private key before connecting your first server.')
        ->assertDontSee('New server');
});
