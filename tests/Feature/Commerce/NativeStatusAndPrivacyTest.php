<?php

namespace Tests\Feature\Commerce;

use App\Jobs\Commerce\ExecuteStatusProbesJob;
use App\Livewire\Status\PublicStatus;
use App\Models\Commerce\Monitor;
use App\Models\Commerce\SystemIncident;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Policies\ServerPolicy;
use Database\Seeders\NativeStatusMonitorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(NativeStatusMonitorSeeder::class);
});

test('native status monitor seeder populates monitors and incidents accurately', function () {
    expect(Monitor::count())->toBeGreaterThanOrEqual(8);
    expect(SystemIncident::count())->toBeGreaterThanOrEqual(1);

    $frankfurt = Monitor::where('name', 'edge-frankfurt-01')->firstOrFail();
    expect($frankfurt->public_label)->toBe('Edge Gateway — Frankfurt (DE-01)');
    expect($frankfurt->current_status)->toBe('operational');
    expect($frankfurt->is_public)->toBeTrue();
});

test('status probe job executes health checks and records latency without failing', function () {
    $monitor = Monitor::where('name', 'service-db-postgres')->firstOrFail();
    $initialCheckCount = $monitor->checks()->count();

    (new ExecuteStatusProbesJob())->handle();

    $monitor->refresh();
    expect($monitor->checks()->count())->toBe($initialCheckCount + 1);
    expect($monitor->last_checked_at)->not->toBeNull();
    expect($monitor->last_latency_ms)->toBeGreaterThanOrEqual(1);
});

test('public status livewire component renders operational status and hides private ip addresses', function () {
    $privateIp = '198.51.100.222';
    Monitor::create([
        'name' => 'secret-internal-node',
        'public_label' => 'Shielded Cloud Node — Singapore (SG-02)',
        'group_name' => 'Core Compute Infrastructure',
        'type' => 'tcp',
        'target_host' => $privateIp,
        'port' => 22,
        'is_public' => true,
        'is_active' => true,
        'current_status' => 'operational',
    ]);

    $component = Livewire::test(PublicStatus::class)
        ->assertSee('STX Enterprises')
        ->assertSee('Shielded Cloud Node — Singapore (SG-02)')
        ->assertSee('All Systems Operational')
        ->assertDontSee($privateIp); // Strict privacy assertion: internal IP is never leaked!

    expect($component)->not->toBeNull();
});

test('public status api returns json with hidden target host and port fields', function () {
    $privateIp = '203.0.113.99';
    Monitor::create([
        'name' => 'confidential-edge-node',
        'public_label' => 'Edge Gateway — Tokyo (TY-01)',
        'group_name' => 'Global Edge & Routing',
        'type' => 'tcp',
        'target_host' => $privateIp,
        'port' => 8443,
        'is_public' => true,
        'is_active' => true,
        'current_status' => 'operational',
    ]);

    $response = $this->getJson('/api/status');
    $response->assertOk()
        ->assertJsonFragment(['name' => 'Edge Gateway — Tokyo (TY-01)'])
        ->assertJsonMissing(['target_host' => $privateIp])
        ->assertJsonMissing(['port' => 8443]);
});

test('regular customer is prevented from creating raw servers under server policy', function () {
    $customer = User::factory()->create();
    $personalTeam = Team::factory()->create();
    $personalTeam->members()->attach($customer->id, ['role' => 'owner']);

    $policy = new ServerPolicy();

    // Regular customer cannot create servers
    expect($policy->create($customer))->toBeFalse();

    // Instance root admin CAN create servers
    $rootAdmin = User::factory()->create();
    $rootTeam = Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($rootAdmin->id, ['role' => 'admin']);

    expect($policy->create($rootAdmin))->toBeTrue();
});
