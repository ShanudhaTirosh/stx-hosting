<?php

use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Livewire\Dashboard;
use App\Livewire\GlobalSearch;
use App\Livewire\Server\Canvas as ServerCanvas;
use App\Livewire\Server\Create as ServerCreate;
use App\Livewire\Server\CreatePage as ServerCreatePage;
use App\Livewire\Server\Index as ServerIndex;
use App\Livewire\Server\New\ByDigitalOcean;
use App\Livewire\Server\New\ByHetzner;
use App\Livewire\Server\New\ByHostinger;
use App\Livewire\Server\New\ByIp;
use App\Livewire\Server\New\ByVultr;
use App\Models\CloudProviderToken;
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
    $this->withoutMiddleware(PreventRequestsDuringMaintenance::class);

    InstanceSettings::unguarded(fn () => InstanceSettings::updateOrCreate(['id' => 0], [
        'id' => 0,
        'is_api_enabled' => true,
    ]));

    // Instance Admin (attached to root team 0 as admin/owner)
    $this->rootTeam = Team::find(0) ?? Team::factory()->create(['id' => 0]);
    $this->instanceAdmin = User::factory()->create();
    $this->instanceAdmin->teams()->attach($this->rootTeam, ['role' => 'admin']);
    $this->instanceAdminToken = $this->instanceAdmin->createToken('admin-token', ['*'])->plainTextToken;

    // Client user (tenant owner, NOT in root team 0)
    $this->clientTeam = Team::factory()->create();
    $this->client = User::factory()->create();
    $this->client->teams()->attach($this->clientTeam, ['role' => 'owner']);
    $this->clientToken = $this->client->createToken('client-token', ['*'])->plainTextToken;

    // Client member (regular member of client team)
    $this->clientMember = User::factory()->create();
    $this->clientMember->teams()->attach($this->clientTeam, ['role' => 'member']);
    $this->clientMemberToken = $this->clientMember->createToken('client-member-token', ['*'])->plainTextToken;

    // Shared mock cloud tokens and private keys under client team
    $this->clientKey = PrivateKey::factory()->create([
        'team_id' => $this->clientTeam->id,
        'name' => 'Client Key',
    ]);

    $this->hetznerToken = CloudProviderToken::create([
        'team_id' => $this->clientTeam->id,
        'provider' => 'hetzner',
        'token' => 'hetzner-test-token',
        'name' => 'Client Hetzner Token',
    ]);

    $this->vultrToken = CloudProviderToken::create([
        'team_id' => $this->clientTeam->id,
        'provider' => 'vultr',
        'token' => 'vultr-test-token',
        'name' => 'Client Vultr Token',
    ]);

    $this->digitalOceanToken = CloudProviderToken::create([
        'team_id' => $this->clientTeam->id,
        'provider' => 'digitalocean',
        'token' => 'do-test-token',
        'name' => 'Client DO Token',
    ]);

    $this->hostingerToken = CloudProviderToken::create([
        'team_id' => $this->clientTeam->id,
        'provider' => 'hostinger',
        'token' => 'hostinger-test-token',
        'name' => 'Client Hostinger Token',
    ]);
});

// =========================================================================
// Dimension 1: REST API Server Creation Endpoints Authorization Probing
// =========================================================================

test('adversarial: client receives 403 Forbidden on POST /api/v1/servers', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$this->clientToken,
    ])->postJson('/api/v1/servers', [
        'name' => 'Adversarial Server',
        'ip' => '1.2.3.4',
        'port' => 22,
        'user' => 'root',
        'private_key_uuid' => $this->clientKey->uuid,
    ]);

    $response->assertForbidden();
});

test('adversarial: client receives 403 on POST /api/v1/servers even with invalid or empty payload', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$this->clientToken,
    ])->postJson('/api/v1/servers', []);

    // Authorization must block before validation (422) is reached
    $response->assertForbidden();
});

test('adversarial: client receives 403 Forbidden on POST /api/v1/servers/hetzner', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$this->clientToken,
    ])->postJson('/api/v1/servers/hetzner', [
        'cloud_provider_token_uuid' => $this->hetznerToken->uuid,
        'location' => 'nbg1',
        'server_type' => 'cpx11',
        'image' => 12345,
        'name' => 'adversarial-hetzner',
    ]);

    $response->assertForbidden();
});

test('adversarial: client receives 403 Forbidden on POST /api/v1/servers/vultr', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$this->clientToken,
    ])->postJson('/api/v1/servers/vultr', [
        'cloud_provider_token_uuid' => $this->vultrToken->uuid,
        'region' => 'ewr',
        'plan' => 'vc2-1c-1gb',
        'os_id' => 2284,
        'name' => 'adversarial-vultr',
        'private_key_uuid' => $this->clientKey->uuid,
    ]);

    $response->assertForbidden();
});

test('adversarial: client receives 403 Forbidden on POST /api/v1/servers/digitalocean', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$this->clientToken,
    ])->postJson('/api/v1/servers/digitalocean', [
        'cloud_provider_token_uuid' => $this->digitalOceanToken->uuid,
        'region' => 'nyc1',
        'size' => 's-1vcpu-1gb',
        'image' => 'ubuntu-22-04-x64',
        'name' => 'adversarial-do',
    ]);

    $response->assertForbidden();
});

test('adversarial: client receives 403 Forbidden on POST /api/v1/servers/hostinger', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$this->clientToken,
    ])->postJson('/api/v1/servers/hostinger', [
        'cloud_provider_token_uuid' => $this->hostingerToken->uuid,
        'item_id' => 'item_123',
        'data_center_id' => 1,
        'template_id' => 10,
        'password' => 'Secret123!',
        'name' => 'adversarial-hostinger',
    ]);

    $response->assertForbidden();
});

test('adversarial: client team member receives 403 Forbidden across all 5 endpoints', function (string $endpoint) {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$this->clientMemberToken,
    ])->postJson($endpoint, []);

    $response->assertForbidden();
})->with([
    '/api/v1/servers',
    '/api/v1/servers/hetzner',
    '/api/v1/servers/vultr',
    '/api/v1/servers/digitalocean',
    '/api/v1/servers/hostinger',
]);

// =========================================================================
// Dimension 2: Direct HTTP GET Routing and Middleware Enforcement
// =========================================================================

test('adversarial: client cannot access server creation web routes via HTTP GET', function (string $routeName, array $params) {
    $this->actingAs($this->client);
    session(['currentTeam' => $this->clientTeam]);

    $this->get(route($routeName, $params))
        ->assertForbidden();
})->with([
    'server.create' => ['server.create', []],
    'server.create.type manual' => ['server.create.type', ['type' => 'manual']],
    'server.create.type hetzner' => ['server.create.type', ['type' => 'hetzner']],
    'server.create.type vultr' => ['server.create.type', ['type' => 'vultr']],
    'server.create.type digital-ocean' => ['server.create.type', ['type' => 'digital-ocean']],
    'server.create.type hostinger' => ['server.create.type', ['type' => 'hostinger']],
]);

// =========================================================================
// Dimension 3: Direct Livewire Component Mounting Isolation
// =========================================================================

test('adversarial: client cannot mount any server creation Livewire components directly', function (string $componentClass) {
    $this->actingAs($this->client);
    session(['currentTeam' => $this->clientTeam]);

    Livewire::test($componentClass)
        ->assertForbidden();
})->with([
    ServerCreatePage::class,
    ServerCreate::class,
    ByIp::class,
    ByHetzner::class,
    ByVultr::class,
    ByDigitalOcean::class,
    ByHostinger::class,
]);

// =========================================================================
// Dimension 4: GlobalSearch Quick Action Bypass & Privilege Escalation Probing
// =========================================================================

test('adversarial: client cannot trigger server creation through GlobalSearch', function () {
    $this->actingAs($this->client);
    session(['currentTeam' => $this->clientTeam]);

    $search = Livewire::test(GlobalSearch::class);

    // 1. Calling navigateToResource('server') directly on the component
    $search->call('navigateToResource', 'server');

    // Must not redirect to server.create
    $search->assertNoRedirect();

    // 2. Reflection check on canCreateResource('server')
    $reflectionMethod = (new ReflectionClass(GlobalSearch::class))->getMethod('canCreateResource');
    $reflectionMethod->setAccessible(true);
    expect($reflectionMethod->invoke($search->instance(), 'server'))->toBeFalse();

    // 3. detectSpecificResource('new server') must evaluate to null for client
    $detectMethod = (new ReflectionClass(GlobalSearch::class))->getMethod('detectSpecificResource');
    $detectMethod->setAccessible(true);
    expect($detectMethod->invoke($search->instance(), 'new server'))->toBeNull();
});

// =========================================================================
// Dimension 5: UI Leak Prevention (Dashboard, Servers Index, Canvas)
// =========================================================================

test('adversarial: client dashboard does not leak server creation prompts or private key instructions', function () {
    $this->actingAs($this->client);
    session(['currentTeam' => $this->clientTeam]);

    // Scenario A: Client with 0 servers and 0 private keys
    PrivateKey::where('team_id', $this->clientTeam->id)->delete();

    Livewire::test(Dashboard::class)
        ->assertSee('No servers yet')
        ->assertDontSee('A private key is required')
        ->assertDontSee('Add an SSH private key before connecting your first server.')
        ->assertDontSee('Add private key')
        ->assertDontSee('New server')
        ->assertDontSee(route('server.create'), false);

    // Scenario B: Client with 0 servers and existing private key
    PrivateKey::factory()->create([
        'team_id' => $this->clientTeam->id,
        'name' => 'Existing Client Key',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee('No servers yet')
        ->assertDontSee('New server')
        ->assertDontSee(route('server.create'), false);
});

test('adversarial: client server index does not leak server creation prompts or actions', function () {
    $this->actingAs($this->client);
    session(['currentTeam' => $this->clientTeam]);

    Livewire::test(ServerIndex::class)
        ->assertSee('Servers')
        ->assertSee('No servers yet')
        ->assertDontSee('New server')
        ->assertDontSee(route('server.create'), false);
});

test('adversarial: client server canvas does not leak server creation button', function () {
    $this->actingAs($this->client);
    session(['currentTeam' => $this->clientTeam]);

    Livewire::test(ServerCanvas::class)
        ->assertDontSee('New server')
        ->assertDontSee(route('server.create'), false);
});
