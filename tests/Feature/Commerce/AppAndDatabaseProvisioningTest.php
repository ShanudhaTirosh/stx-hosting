<?php

namespace Tests\Feature\Commerce;

use App\Livewire\Portal\Apps\AppList;
use App\Livewire\Portal\Databases\DatabaseList;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceInstance;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Services\Commerce\AppProvisioningService;
use App\Services\Commerce\DatabaseProvisioningService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

test('app provisioning service calculates exact cgroups quotas for container plans', function () {
    $starterVariant = ProductVariant::where('slug', 'app-starter')->firstOrFail(); // 512 MB, 1 vCPU
    $starterLimits = AppProvisioningService::getPlanLimits($starterVariant);

    expect($starterLimits['limits_memory'])->toBe('512m');
    expect($starterLimits['limits_memory_swap'])->toBe('256m');
    expect($starterLimits['limits_cpus'])->toBe('1');
    expect($starterLimits['limits_cpu_shares'])->toBe(1024);

    $proVariant = ProductVariant::where('slug', 'app-pro')->firstOrFail(); // 2048 MB, 2 vCPU
    $proLimits = AppProvisioningService::getPlanLimits($proVariant);

    expect($proLimits['limits_memory'])->toBe('2048m');
    expect($proLimits['limits_memory_swap'])->toBe('1024m');
    expect($proLimits['limits_cpus'])->toBe('2');
    expect($proLimits['limits_cpu_shares'])->toBe(2048);
});

test('customer can deploy a web application via portal livewire component', function () {
    $customer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($customer->id, ['role' => 'owner']);

    $this->actingAs($customer);
    session(['currentTeam' => $team]);

    $variant = ProductVariant::where('slug', 'app-standard')->firstOrFail();

    Livewire::test(AppList::class)
        ->set('appName', 'my-ecommerce-api')
        ->set('gitRepo', 'https://github.com/example/api-service')
        ->set('gitBranch', 'main')
        ->set('buildPack', 'nixpacks')
        ->set('selectedVariantId', $variant->id)
        ->call('deployApp')
        ->assertDispatched('success');

    $app = ServiceInstance::where('team_id', $team->id)
        ->where('service_type', 'web_app')
        ->firstOrFail();

    expect($app->name)->toBe('my-ecommerce-api');
    expect($app->status)->toBe('active');
    expect($app->os_image)->toBe('nixpacks');
});

test('customer can deploy a managed database and decrypt database password', function () {
    $customer = User::factory()->create([
        'password' => bcrypt('SecureDbCustomerPass123!'),
    ]);
    $team = Team::factory()->create();
    $team->members()->attach($customer->id, ['role' => 'owner']);

    $this->actingAs($customer);
    session(['currentTeam' => $team]);

    $dbVariant = ProductVariant::where('slug', 'db-postgres')->firstOrFail();

    Livewire::test(DatabaseList::class)
        ->set('dbName', 'analytics_db')
        ->set('engine', 'postgresql')
        ->set('selectedVariantId', $dbVariant->id)
        ->call('createDatabase')
        ->assertDispatched('success');

    $dbService = ServiceInstance::where('team_id', $team->id)
        ->where('service_type', 'database')
        ->firstOrFail();

    expect($dbService->name)->toContain('analytics_db');

    // Test password revelation
    Livewire::test(DatabaseList::class)
        ->call('openPasswordModal', $dbService->id)
        ->set('accountPassword', 'SecureDbCustomerPass123!')
        ->call('revealPassword')
        ->assertDispatched('success');
});

