<?php

namespace Tests\Feature\Commerce;

use App\Exceptions\PlatformRestrictionException;
use App\Jobs\Commerce\PollCustomDomainDnsJob;
use App\Livewire\Admin\Commerce\TemplateCatalogStudio;
use App\Livewire\Portal\DeployWizard;
use App\Models\Commerce\Product;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceInstance;
use App\Models\CustomDomain;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\TemplatePermission;
use App\Models\User;
use App\Services\Commerce\AppProvisioningService;
use App\Services\Commerce\CustomDomainService;
use App\Services\Commerce\HostingCatalogSeederService;
use App\Services\TemplateAccessService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
    config()->set('hosting.ingress_ip', '192.0.2.10');
    config()->set('hosting.apps_domain', 'apps.stxapp.io');
});

test('hosting catalog seeder sets up 3 git app hosting plans and curated templates', function () {
    $product = Product::where('slug', 'git-app-hosting')->firstOrFail();
    expect($product->category)->toBe('app_hosting');

    $starter = ProductVariant::where('slug', 'git-starter')->firstOrFail();
    expect($starter->vcpu)->toBe(1);
    expect($starter->ram_mb)->toBe(512);

    $standard = ProductVariant::where('slug', 'git-standard')->firstOrFail();
    expect($standard->vcpu)->toBe(1);
    expect($standard->ram_mb)->toBe(1024);

    $pro = ProductVariant::where('slug', 'git-pro')->firstOrFail();
    expect($pro->vcpu)->toBe(2);
    expect($pro->ram_mb)->toBe(2048);

    // Curated templates
    expect(TemplatePermission::where('template_name', 'wordpress')->exists())->toBeTrue();
    expect(TemplatePermission::where('template_name', 'ghost')->exists())->toBeTrue();
    expect(TemplatePermission::where('template_name', 'n8n')->exists())->toBeTrue();
});

test('app provisioning calculates strict cgroups limits for git plans and templates', function () {
    $starter = ProductVariant::where('slug', 'git-starter')->firstOrFail();
    $starterLimits = AppProvisioningService::getPlanLimits($starter);
    expect($starterLimits['limits_memory'])->toBe('512m');
    expect($starterLimits['limits_cpus'])->toBe('1');

    $pro = ProductVariant::where('slug', 'git-pro')->firstOrFail();
    $proLimits = AppProvisioningService::getPlanLimits($pro);
    expect($proLimits['limits_memory'])->toBe('2048m');
    expect($proLimits['limits_cpus'])->toBe('2');

    // Template limits
    $wpReqs = TemplateAccessService::getTemplateRequirements('wordpress');
    $wpLimits = AppProvisioningService::getPlanLimits($wpReqs);
    expect($wpLimits['limits_memory'])->toBe('512m');
});

test('app provisioning generates collision-free instant wildcard https hostnames', function () {
    $host1 = AppProvisioningService::generateHostname('My Awesome Blog');
    expect($host1)->toBe('my-awesome-blog.apps.stxapp.io');

    // Simulate collision
    ServiceInstance::forceCreate([
        'uuid' => 'test-collision-uuid',
        'name' => 'My Awesome Blog',
        'hostname' => 'my-awesome-blog.apps.stxapp.io',
        'team_id' => 1,
        'user_id' => 1,
        'service_type' => 'git_app',
        'status' => 'active',
    ]);

    $host2 = AppProvisioningService::generateHostname('My Awesome Blog');
    expect($host2)->toStartWith('my-awesome-blog-');
    expect($host2)->toEndWith('.apps.stxapp.io');
    expect($host2)->not->toBe($host1);
});

test('customer can provision custom git app bound to fixed-price plan', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $variant = ProductVariant::where('slug', 'git-standard')->firstOrFail();

    $service = AppProvisioningService::provision(
        $team,
        $user,
        $variant,
        'my-laravel-saas',
        'https://github.com/example/saas.git',
        'main',
        'nixpacks',
        ['APP_ENV' => 'production']
    );

    expect($service->name)->toBe('my-laravel-saas');
    expect($service->service_type)->toBe('git_app');
    expect($service->hostname)->toBe('my-laravel-saas.apps.stxapp.io');
    expect($service->network_config['cgroup_limits']['limits_memory'])->toBe('1024m');
    expect($service->network_config['env_vars']['APP_ENV'])->toBe('production');
});

test('customer can provision curated template and blocked if template is disabled', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $service = AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'wordpress',
        'my-company-wp',
        ['WORDPRESS_TITLE' => 'Acme Corp']
    );

    expect($service->name)->toBe('my-company-wp');
    expect($service->service_type)->toBe('template_app');
    expect($service->template_name)->toBe('wordpress');
    expect($service->hostname)->toBe('my-company-wp.apps.stxapp.io');
    expect($service->network_config['port'])->toBe(80);

    // Disable template and verify restriction
    TemplatePermission::where('template_name', 'wordpress')->whereNull('team_id')->update(['is_globally_enabled' => false]);

    expect(fn () => AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'wordpress',
        'my-second-wp'
    ))->toThrow(PlatformRestrictionException::class);
});

test('custom domain service detects record types and performs dual-mode verification', function () {
    $team = Team::factory()->create();
    $service = app(CustomDomainService::class);

    // Apex domain -> A record
    $apex = $service->request($team, 'mybrand.com');
    expect($apex->record_type)->toBe('A');
    expect($apex->expected_target)->toBe('192.0.2.10');

    // Subdomain -> CNAME record
    $sub = $service->request($team, 'app.mybrand.com');
    expect($sub->record_type)->toBe('CNAME');
    expect($sub->expected_target)->toBe('apps.stxapp.io');

    // Verification via DNS resolver mock
    CustomDomainService::$dnsResolver = fn (string $domain, string $expectedIp) => $domain === 'app.mybrand.com' && $expectedIp === '192.0.2.10';

    $verified = $service->verify($sub);
    expect($verified)->toBeTrue();
    expect($sub->isSslActive())->toBeTrue();
    expect($sub->status)->toBe(CustomDomain::STATUS_ACTIVE);
    expect($sub->ssl_issued_at)->not->toBeNull();

    CustomDomainService::$dnsResolver = null;
});

test('custom domain service prevents cross-team domain collisions', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $service = app(CustomDomainService::class);

    $domainA = $service->request($teamA, 'takenbrand.com');
    $domainA->forceFill(['verified_at' => now(), 'status' => CustomDomain::STATUS_ACTIVE])->save();

    // Team B attempting to claim takenbrand.com is blocked
    expect(fn () => $service->request($teamB, 'takenbrand.com'))
        ->toThrow(PlatformRestrictionException::class, 'already verified by another account');
});

test('poll custom domain dns job verifies domain automatically', function () {
    $team = Team::factory()->create();
    $service = app(CustomDomainService::class);
    $domain = $service->request($team, 'autocheck.com');

    CustomDomainService::$dnsResolver = fn ($d, $ip) => true;

    $job = new PollCustomDomainDnsJob($domain);
    $job->handle($service);

    $domain->refresh();
    expect($domain->isVerified())->toBeTrue();
    expect($domain->status)->toBe(CustomDomain::STATUS_ACTIVE);

    CustomDomainService::$dnsResolver = null;
});

test('template catalog studio allows staff to toggle and edit templates while blocking non-staff', function () {
    $client = User::factory()->create();
    $clientTeam = Team::factory()->create();
    $clientTeam->members()->attach($client->id, ['role' => 'owner']);

    // Non-staff blocked
    $this->actingAs($client);
    session(['currentTeam' => $clientTeam]);

    Livewire::test(TemplateCatalogStudio::class)
        ->assertForbidden();

    // Staff allowed
    $staff = User::factory()->create();
    $rootTeam = Team::find(0);
    $rootTeam->members()->attach($staff->id, ['role' => 'admin']);

    $this->actingAs($staff);
    session(['currentTeam' => $rootTeam]);

    $wp = TemplatePermission::where('template_name', 'wordpress')->whereNull('team_id')->firstOrFail();

    Livewire::test(TemplateCatalogStudio::class)
        ->assertSuccessful()
        ->call('toggleEnabled', $wp->id)
        ->assertDispatched('success');

    $wp->refresh();
    expect($wp->is_globally_enabled)->toBeFalse();

    // Staff can add new template
    Livewire::test(TemplateCatalogStudio::class)
        ->set('template_name', 'rust-microservice')
        ->set('display_name', 'Rust High-Speed API')
        ->set('category', 'automation')
        ->set('default_port', 8080)
        ->set('min_ram_mb', 256)
        ->set('min_vcpu', 0.5)
        ->call('save')
        ->assertDispatched('success');

    expect(TemplatePermission::where('template_name', 'rust-microservice')->exists())->toBeTrue();
});

test('deploy wizard livewire component deploys template and git app successfully', function () {
    $customer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($customer->id, ['role' => 'owner']);

    $this->actingAs($customer);
    session(['currentTeam' => $team]);

    // Deploy template
    Livewire::test(DeployWizard::class)
        ->set('mode', 'template')
        ->set('selectedTemplate', 'ghost')
        ->set('appName', 'my-ghost-publication')
        ->call('deploy')
        ->assertRedirect();

    expect(ServiceInstance::where('name', 'my-ghost-publication')->exists())->toBeTrue();

    // Deploy Git app with Standard plan
    $standardPlan = ProductVariant::where('slug', 'git-standard')->firstOrFail();

    Livewire::test(DeployWizard::class)
        ->set('mode', 'git')
        ->set('selectedPlanId', $standardPlan->id)
        ->set('appName', 'my-node-service')
        ->set('gitRepo', 'https://github.com/myorg/nodeservice.git')
        ->set('gitBranch', 'main')
        ->set('buildPack', 'node')
        ->call('deploy')
        ->assertRedirect();

    expect(ServiceInstance::where('name', 'my-node-service')->exists())->toBeTrue();
});

