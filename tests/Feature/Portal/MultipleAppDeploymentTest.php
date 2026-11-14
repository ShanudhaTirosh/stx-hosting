<?php

namespace Tests\Feature\Portal;

use App\Http\Controllers\Api\DomainTlsValidationController;
use App\Livewire\Portal\Apps\AppDetail;
use App\Livewire\Portal\DeployWizard;
use App\Models\Commerce\Product;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceInstance;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Models\Team;
use App\Models\TemplatePermission;
use App\Models\User;
use App\Services\Commerce\AppProvisioningService;
use App\Services\Commerce\HostingCatalogSeederService;
use App\Services\Commerce\PlatformDomainService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate([
        'id' => 0,
        'base_platform_domain' => 'stxhostings.com',
        'apps_domain' => 'app.stxhostings.com',
    ]);

    $this->seed(HostingCatalogSeeder::class);
    HostingCatalogSeederService::seedCuratedTemplates();

    config()->set('hosting.apps_domain', 'app.stxhostings.com');
    config()->set('hosting.ingress_ip', '198.51.100.10');
    config()->set('hosting.reserved_subdomains', ['api', 'admin', 'portal', 'webmail', 'mail', 'root']);
});

test('can deploy two instances of the same template on the same vps with isolated subdomains and endpoints', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    // Instance 1: Supabase Stack 1
    $supabase1 = AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'supabase',
        'supabase-prod',
        ['PROJECT_NAME' => 'Production Database'],
        null,
        'sb-prod'
    );

    expect($supabase1->hostname)->toBe('sb-prod.app.stxhostings.com');
    expect(data_get($supabase1->network_config, 'assigned_endpoints.supabase-kong.host'))->toBe('sb-prod.app.stxhostings.com');
    expect(data_get($supabase1->network_config, 'assigned_endpoints.supabase-studio.host'))->toBe('studio.sb-prod.app.stxhostings.com');
    expect(data_get($supabase1->network_config, 'env_vars.PROJECT_NAME'))->toBe('Production Database');

    // Instance 2: Supabase Stack 2 (on the same team and VPS)
    $supabase2 = AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'supabase',
        'supabase-staging',
        ['PROJECT_NAME' => 'Staging Database'],
        null,
        'sb-staging'
    );

    expect($supabase2->hostname)->toBe('sb-staging.app.stxhostings.com');
    expect(data_get($supabase2->network_config, 'assigned_endpoints.supabase-kong.host'))->toBe('sb-staging.app.stxhostings.com');
    expect(data_get($supabase2->network_config, 'assigned_endpoints.supabase-studio.host'))->toBe('studio.sb-staging.app.stxhostings.com');
    expect(data_get($supabase2->network_config, 'env_vars.PROJECT_NAME'))->toBe('Staging Database');

    // Ensure completely separate and non-conflicting addresses
    expect($supabase1->hostname)->not->toBe($supabase2->hostname);
    expect(data_get($supabase1->network_config, 'assigned_endpoints.supabase-studio.host'))
        ->not->toBe(data_get($supabase2->network_config, 'assigned_endpoints.supabase-studio.host'));
});

test('can deploy two instances of the same git repository with different branches and custom subdomains', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $variant = ProductVariant::first();
    $this->actingAs($user);

    $gitRepo = 'https://github.com/my-org/core-api.git';

    // Instance 1: Production
    $prodApp = AppProvisioningService::provision(
        $team,
        $user,
        $variant,
        'core-api-prod',
        $gitRepo,
        'main',
        'node',
        ['ENV' => 'production'],
        'api-prod'
    );

    expect($prodApp->hostname)->toBe('api-prod.app.stxhostings.com');
    expect(data_get($prodApp->network_config, 'git_branch'))->toBe('main');

    // Instance 2: Staging from same repo
    $stagingApp = AppProvisioningService::provision(
        $team,
        $user,
        $variant,
        'core-api-staging',
        $gitRepo,
        'develop',
        'node',
        ['ENV' => 'staging'],
        'api-staging'
    );

    expect($stagingApp->hostname)->toBe('api-staging.app.stxhostings.com');
    expect(data_get($stagingApp->network_config, 'git_branch'))->toBe('develop');
    expect($prodApp->hostname)->not->toBe($stagingApp->hostname);
});

test('deploy wizard rejects explicit custom subdomain collision without silent renaming', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $user->update(['current_team_id' => $team->id]);
    $this->actingAs($user);

    // Deploy first app claiming 'my-blog'
    AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'wordpress',
        'first-blog',
        [],
        null,
        'my-blog'
    );

    // Attempt to deploy second app with identical custom subdomain
    Livewire::test(DeployWizard::class)
        ->call('setMode', 'template')
        ->call('selectTemplate', 'wordpress')
        ->set('appName', 'second-blog')
        ->set('customSubdomain', 'my-blog')
        ->call('deploy')
        ->assertHasErrors(['customSubdomain']);

    // Ensure no second service was created with hijacked or renamed domain
    $second = ServiceInstance::where('name', 'second-blog')->first();
    expect($second)->toBeNull();
});

test('deploy wizard suggests clean sequential names when app name is taken', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    // First app
    AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'wordpress',
        'blog',
        [],
        null,
        'blog'
    );

    // Next available slug should cleanly increment
    $next = PlatformDomainService::nextAvailableSlug('blog');
    expect($next)->toBe('blog-2');

    // Take blog-2
    AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'wordpress',
        'blog-2',
        [],
        null,
        'blog-2'
    );

    $next3 = PlatformDomainService::nextAvailableSlug('blog');
    expect($next3)->toBe('blog-3');
});

test('1-click duplicateApp creates full clone with inherited env vars and sequential subdomain', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $user->update(['current_team_id' => $team->id]);
    $this->actingAs($user);

    $source = AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'wordpress',
        'client-portal',
        ['DB_PREFIX' => 'wp_portal_', 'FEATURE_FLAG' => 'true'],
        null,
        'portal-one'
    );

    // Duplicate via AppDetail Livewire component
    Livewire::test(AppDetail::class, ['uuid' => $source->uuid])
        ->call('openDuplicateModal')
        ->assertSet('showDuplicateModal', true)
        ->assertSet('duplicateName', 'client-portal-2')
        ->assertSet('duplicateSubdomain', 'client-portal-2')
        ->call('duplicateApp')
        ->assertHasNoErrors()
        ->assertRedirect();

    $clone = ServiceInstance::where('name', 'client-portal-2')->first();
    expect($clone)->not->toBeNull();
    expect($clone->hostname)->toBe('client-portal-2.app.stxhostings.com');
    expect(data_get($clone->network_config, 'env_vars.DB_PREFIX'))->toBe('wp_portal_');
    expect(data_get($clone->network_config, 'env_vars.FEATURE_FLAG'))->toBe('true');
});

test('domain tls validation controller authorizes wildcards and portal service instances', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $app = AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'wordpress',
        'demo-site',
        [],
        null,
        'demo-site'
    );

    $controller = new DomainTlsValidationController();

    // 1. Should authorize any subdomain under *.app.stxhostings.com
    $req1 = Request::create('/api/v1/domain-tls-validation', 'GET', ['domain' => 'demo-site.app.stxhostings.com']);
    $res1 = $controller->validateAsk($req1);
    expect($res1->getStatusCode())->toBe(200);

    // 2. Should authorize exact ServiceInstance hostname
    $req2 = Request::create('/api/v1/domain-tls-validation', 'GET', ['domain' => $app->hostname]);
    $res2 = $controller->validateAsk($req2);
    expect($res2->getStatusCode())->toBe(200);

    // 3. Should reject external unauthorized domain
    $req3 = Request::create('/api/v1/domain-tls-validation', 'GET', ['domain' => 'random-unauthorized-site.com']);
    $res3 = $controller->validateAsk($req3);
    expect($res3->getStatusCode())->toBe(403);
});

test('supports provisioning apps across multiple cluster nodes with node IP and server metadata', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    $privateKey = PrivateKey::create([
        'name' => 'Cluster Key',
        'team_id' => $team->id,
        'private_key' => 'fake-key',
    ]);

    $node1 = Server::create([
        'name' => 'US-East-Node',
        'ip' => '198.51.100.21',
        'team_id' => $team->id,
        'private_key_id' => $privateKey->id,
    ]);
    if ($node1->settings) {
        $node1->settings->update(['is_reachable' => true, 'is_usable' => true]);
    }

    $node2 = Server::create([
        'name' => 'EU-Central-Node',
        'ip' => '198.51.100.22',
        'team_id' => $team->id,
        'private_key_id' => $privateKey->id,
    ]);
    if ($node2->settings) {
        $node2->settings->update(['is_reachable' => true, 'is_usable' => true]);
    }

    // Provision App 1 on Node 1
    $app1 = AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'supabase',
        'supabase-us',
        [],
        null,
        'sb-us',
        [],
        $node1->id
    );

    // Provision App 2 on Node 2
    $app2 = AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'supabase',
        'supabase-eu',
        [],
        null,
        'sb-eu',
        [],
        $node2->id
    );

    expect($app1->ip_address)->toBe('198.51.100.21');
    expect(data_get($app1->network_config, 'server_name'))->toBe('US-East-Node');
    expect(data_get($app1->network_config, 'server_id'))->toBe($node1->id);

    expect($app2->ip_address)->toBe('198.51.100.22');
    expect(data_get($app2->network_config, 'server_name'))->toBe('EU-Central-Node');
    expect(data_get($app2->network_config, 'server_id'))->toBe($node2->id);

    // DeployWizard displays the server picker when multiple servers exist
    Livewire::actingAs($user)
        ->test(DeployWizard::class)
        ->assertSee('US-East-Node')
        ->assertSee('EU-Central-Node');
});

