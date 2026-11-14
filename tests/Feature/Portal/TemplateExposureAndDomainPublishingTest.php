<?php

namespace Tests\Feature\Portal;

use App\Livewire\Portal\Apps\AppDetail;
use App\Livewire\Portal\DeployWizard;
use App\Models\Commerce\ServiceInstance;
use App\Models\CustomDomain;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\TemplatePermission;
use App\Models\User;
use App\Services\Commerce\AppProvisioningService;
use App\Services\Commerce\CustomDomainService;
use App\Services\Commerce\HostingCatalogSeederService;
use App\Services\Commerce\PlatformDomainService;
use App\Services\TemplateAccessService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
    HostingCatalogSeederService::seedCuratedTemplates();

    config()->set('hosting.apps_domain', 'stxapp.io');
    config()->set('hosting.ingress_ip', '198.51.100.10');
    config()->set('hosting.reserved_subdomains', ['api', 'admin', 'portal', 'webmail', 'mail', 'root']);
});

test('template access service returns declarative exposable services for multi-container templates', function () {
    // Supabase
    $supabaseServices = TemplateAccessService::getExposableServices('supabase');
    expect($supabaseServices)->toBeArray();
    expect(count($supabaseServices))->toBe(2);

    $kong = collect($supabaseServices)->firstWhere('service_name', 'supabase-kong');
    expect($kong)->not->toBeNull();
    expect($kong['is_default'])->toBeTrue();
    expect($kong['internal_port'])->toBe(8000);
    expect($kong['access_type'])->toBe('public');
    expect($kong['env_bindings'])->toContain('SUPABASE_PUBLIC_URL');

    $studio = collect($supabaseServices)->firstWhere('service_name', 'supabase-studio');
    expect($studio)->not->toBeNull();
    expect($studio['is_default'])->toBeFalse();
    expect($studio['subdomain_prefix'])->toBe('studio');
    expect($studio['access_type'])->toBe('admin_only');

    // MinIO
    $minioServices = TemplateAccessService::getExposableServices('minio');
    expect(count($minioServices))->toBe(2);
    $s3 = collect($minioServices)->firstWhere('internal_port', 9000);
    expect($s3['is_default'])->toBeTrue();
    $console = collect($minioServices)->firstWhere('internal_port', 9001);
    expect($console['access_type'])->toBe('admin_only');

    // n8n
    $n8nServices = TemplateAccessService::getExposableServices('n8n');
    expect(count($n8nServices))->toBe(1);
    expect($n8nServices[0]['internal_port'])->toBe(5678);
});

test('platform domain service assigns subdomains and cascades env rewrites for multi-container templates', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $instance = ServiceInstance::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'service_type' => 'template_app',
        'template_name' => 'supabase',
        'status' => 'active',
        'name' => 'my-project',
        'network_config' => [
            'template_name' => 'supabase',
            'env_vars' => [],
            'custom_domains' => [],
        ],
    ]);

    $assigned = PlatformDomainService::assignTemplateSubdomains($instance, 'supabase', 'acme-backend');

    expect($assigned)->toHaveKey('supabase-kong');
    expect($assigned)->toHaveKey('supabase-studio');

    expect($assigned['supabase-kong']['host'])->toBe('acme-backend.stxapp.io');
    expect($assigned['supabase-kong']['url'])->toBe('https://acme-backend.stxapp.io');
    expect($assigned['supabase-kong']['is_default'])->toBeTrue();

    expect($assigned['supabase-studio']['host'])->toBe('studio.acme-backend.stxapp.io');
    expect($assigned['supabase-studio']['url'])->toBe('https://studio.acme-backend.stxapp.io');
    expect($assigned['supabase-studio']['access_type'])->toBe('admin_only');

    $instance->refresh();
    expect($instance->hostname)->toBe('acme-backend.stxapp.io');
    expect(data_get($instance->network_config, 'env_vars.SUPABASE_PUBLIC_URL'))->toBe('https://acme-backend.stxapp.io');
    expect(data_get($instance->network_config, 'env_vars.NEXT_PUBLIC_SUPABASE_URL'))->toBe('https://acme-backend.stxapp.io');
});

test('provision template automatically wires up subdomains and exposable services', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    $service = AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'supabase',
        'supabase-saas',
        ['CUSTOM_VAR' => '123'],
        null,
        'saas-api'
    );

    expect($service)->toBeInstanceOf(ServiceInstance::class);
    expect($service->hostname)->toBe('saas-api.stxapp.io');
    expect(data_get($service->network_config, 'assigned_endpoints'))->toHaveKey('supabase-kong');
    expect(data_get($service->network_config, 'assigned_endpoints'))->toHaveKey('supabase-studio');
    expect(data_get($service->network_config, 'env_vars.CUSTOM_VAR'))->toBe('123');
    expect(data_get($service->network_config, 'env_vars.SUPABASE_PUBLIC_URL'))->toBe('https://saas-api.stxapp.io');
});

test('deploy wizard renders exposable services and provisions template with custom subdomain', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $user->update(['current_team_id' => $team->id]);
    $this->actingAs($user);

    Livewire::test(DeployWizard::class)
        ->call('setMode', 'template')
        ->call('selectTemplate', 'supabase')
        ->assertSee('Main API Gateway')
        ->assertSee('Admin Studio Dashboard')
        ->assertSee('Public Endpoints')
        ->assertSee('Internal Security Guarantee')
        ->set('appName', 'super-backend')
        ->set('customSubdomain', 'custom-slug')
        ->assertSee('https://custom-slug.stxapp.io')
        ->call('deploy')
        ->assertHasNoErrors()
        ->assertRedirect();

    $instance = ServiceInstance::where('team_id', $team->id)->where('name', 'super-backend')->first();
    expect($instance)->not->toBeNull();
    expect($instance->hostname)->toBe('custom-slug.stxapp.io');
});

test('app detail allows connecting, verifying, and removing custom domains with dns guidance', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $user->update(['current_team_id' => $team->id]);
    $this->actingAs($user);

    $service = AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'supabase',
        'prod-app',
        [],
        null,
        'prod-app'
    );

    // Mock DNS resolver to simulate successful verification
    CustomDomainService::$dnsResolver = fn (string $d, string $ip) => $d === 'api.customer.com';

    Livewire::test(AppDetail::class, ['uuid' => $service->uuid])
        ->assertSee('Published HTTPS Endpoints')
        ->assertSee('Main API Gateway')
        ->assertSee('Admin Studio Dashboard')
        ->set('newDomain', 'api.customer.com')
        ->call('addDomain')
        ->assertHasNoErrors();

    $service->refresh();
    expect(data_get($service->network_config, 'custom_domains'))->toContain('api.customer.com');

    // Test verify domain action
    Livewire::test(AppDetail::class, ['uuid' => $service->uuid])
        ->call('verifyDomain', 'api.customer.com')
        ->assertDispatched('success');

    // Test make primary
    Livewire::test(AppDetail::class, ['uuid' => $service->uuid])
        ->call('setPrimaryDomain', 'api.customer.com')
        ->assertDispatched('success');

    $service->refresh();
    expect($service->hostname)->toBe('api.customer.com');

    // Test remove domain
    Livewire::test(AppDetail::class, ['uuid' => $service->uuid])
        ->call('removeDomain', 'api.customer.com')
        ->assertDispatched('success');

    $service->refresh();
    expect(data_get($service->network_config, 'custom_domains'))->not->toContain('api.customer.com');

    CustomDomainService::$dnsResolver = null;
});

