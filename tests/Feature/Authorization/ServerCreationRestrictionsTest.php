<?php

use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Livewire\GlobalSearch;
use App\Livewire\Server\New\ByDigitalOcean;
use App\Livewire\Server\New\ByHetzner;
use App\Livewire\Server\New\ByHostinger;
use App\Livewire\Server\New\ByIp;
use App\Livewire\Server\New\ByVultr;
use App\Models\CloudProviderToken;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestsDuringMaintenance::class);

    InstanceSettings::unguarded(fn () => InstanceSettings::updateOrCreate(['id' => 0], [
        'id' => 0,
        'is_api_enabled' => true,
    ]));

    // Instance Admin (attached to root team 0)
    $this->rootTeam = Team::find(0) ?? Team::factory()->create(['id' => 0]);
    $this->adminTeam = Team::factory()->create();
    $this->admin = User::factory()->create();
    $this->admin->teams()->attach($this->adminTeam, ['role' => 'admin']);
    $this->admin->teams()->attach($this->rootTeam, ['role' => 'admin']);

    // Client user (tenant owner, NOT attached to root team 0)
    $this->clientTeam = Team::factory()->create();
    $this->client = User::factory()->create();
    $this->client->teams()->attach($this->clientTeam, ['role' => 'owner']);

    $this->clientToken = $this->client->createToken('client-token', ['*'])->plainTextToken;
    $this->adminToken = $this->admin->createToken('admin-token', ['*'])->plainTextToken;

    $this->vultrToken = CloudProviderToken::create([
        'team_id' => $this->clientTeam->id,
        'provider' => 'vultr',
        'token' => 'test-vultr-key',
        'name' => 'Client Vultr Token',
    ]);

    $this->clientKey = PrivateKey::factory()->create([
        'team_id' => $this->clientTeam->id,
        'name' => 'Client Key',
    ]);
});

test('client cannot create server via Vultr API endpoint', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$this->clientToken,
        'Content-Type' => 'application/json',
    ])->postJson('/api/v1/servers/vultr', [
        'cloud_provider_token_id' => $this->vultrToken->uuid,
        'region' => 'ewr',
        'plan' => 'vc2-1c-1gb',
        'os_id' => 2284,
        'name' => 'client-vultr-server',
        'private_key_uuid' => $this->clientKey->uuid,
    ]);

    $response->assertForbidden();
});

test('global search canCreateResource excludes server for clients but includes for instance admins', function () {
    $this->actingAs($this->client);
    session(['currentTeam' => $this->clientTeam]);

    $clientSearch = Livewire::test(GlobalSearch::class);
    $canCreateServerClient = (new ReflectionClass(GlobalSearch::class))
        ->getMethod('canCreateResource');
    $canCreateServerClient->setAccessible(true);

    expect($canCreateServerClient->invoke($clientSearch->instance(), 'server'))->toBeFalse();

    $this->actingAs($this->admin);
    session(['currentTeam' => $this->adminTeam]);

    $adminSearch = Livewire::test(GlobalSearch::class);
    expect($canCreateServerClient->invoke($adminSearch->instance(), 'server'))->toBeTrue();
});

test('client cannot directly mount server creation subcomponents', function (string $componentClass) {
    $this->actingAs($this->client);
    session(['currentTeam' => $this->clientTeam]);

    Livewire::test($componentClass)
        ->assertForbidden();
})->with([
    ByIp::class,
    ByHetzner::class,
    ByVultr::class,
    ByDigitalOcean::class,
    ByHostinger::class,
]);
