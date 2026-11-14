<?php

namespace Tests\Feature\Commerce;

use App\Livewire\Admin\Cluster\ClusterNodeStudio;
use App\Models\Commerce\ClusterNode;
use App\Models\Commerce\NodePlacementRule;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceInstance;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Services\Commerce\AppProvisioningService;
use App\Services\Commerce\ClusterPlacementService;
use App\Services\Commerce\HostingCatalogSeederService;
use App\Services\Commerce\NodeMigrationService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
});

test('1-line installer endpoint returns valid bash installation script', function () {
    $response = $this->get('/install-node.sh');
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/x-shellscript; charset=UTF-8');
    expect($response->getContent())->toContain('STX Hosting - 1-Line Cluster Worker Node Onboarding');
    expect($response->getContent())->toContain('docker network create --attachable coolify');
});

test('can register a new worker node via enrollment token handshake', function () {
    $node = ClusterNode::create([
        'name' => 'worker-frankfurt-1',
        'status' => 'provisioning',
        'public_ip' => '195.201.40.10',
        'enrollment_token' => 'stx_test_token_123',
    ]);

    $response = $this->postJson('/api/v1/cluster/nodes/register', [
        'token' => 'stx_test_token_123',
        'name' => 'worker-frankfurt-1',
        'region' => 'eu-central',
        'cpu_cores' => 8,
        'total_ram_mb' => 16384,
        'total_disk_gb' => 320,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'success']);

    $node->refresh();
    expect($node->status)->toBe('ready');
    expect($node->cpu_cores)->toBe(8);
    expect($node->total_ram_mb)->toBe(16384);
    expect($node->total_disk_gb)->toBe(320);
    expect($node->region)->toBe('eu-central');
});

test('can ingest heartbeat telemetry from node agent', function () {
    $node = ClusterNode::create([
        'name' => 'worker-us-1',
        'status' => 'ready',
        'public_ip' => '142.93.10.20',
        'enrollment_token' => 'stx_heartbeat_token_456',
        'current_cpu_load' => 0.0,
    ]);

    $response = $this->postJson('/api/v1/cluster/nodes/heartbeat', [
        'token' => 'stx_heartbeat_token_456',
        'cpu_load' => 0.75,
        'ram_used_mb' => 2048,
        'disk_used_gb' => 35,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'acknowledged', 'node_status' => 'ready']);

    $node->refresh();
    expect($node->current_cpu_load)->toBe(0.75);
    expect($node->current_ram_used_mb)->toBe(2048);
    expect($node->current_disk_used_gb)->toBe(35);
    expect($node->last_heartbeat_at)->not->toBeNull();
});

test('placement engine automatically selects the least-loaded node based on multi-factor scoring', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    // Node 1: Heavy CPU load (8.0), small available RAM (1GB free)
    $heavyNode = ClusterNode::create([
        'name' => 'node-overloaded',
        'status' => 'ready',
        'public_ip' => '10.0.0.1',
        'cpu_cores' => 8,
        'total_ram_mb' => 4096,
        'reserved_ram_mb' => 3072, // Only 1GB available
        'total_disk_gb' => 100,
        'current_cpu_load' => 7.8,
        'last_heartbeat_at' => now(),
    ]);

    // Node 2: Light CPU load (0.2), abundant available RAM (14GB free)
    $coolNode = ClusterNode::create([
        'name' => 'node-cool-breeze',
        'status' => 'ready',
        'public_ip' => '10.0.0.2',
        'cpu_cores' => 8,
        'total_ram_mb' => 16384,
        'reserved_ram_mb' => 2048, // 14GB available
        'total_disk_gb' => 500,
        'current_cpu_load' => 0.2,
        'last_heartbeat_at' => now(),
    ]);

    // Automatically select node for deployment
    $selected = ClusterPlacementService::selectOptimalNode($team, 'my-test-app');

    expect($selected->id)->toBe($coolNode->id);
    expect($selected->name)->toBe('node-cool-breeze');
});

test('placement rules respect owner template pinning', function () {
    $team = Team::factory()->create();

    $generalNode = ClusterNode::create([
        'name' => 'general-worker',
        'status' => 'ready',
        'public_ip' => '10.0.0.10',
        'total_ram_mb' => 16384,
        'last_heartbeat_at' => now(),
    ]);

    $supabaseDedicatedNode = ClusterNode::create([
        'name' => 'supabase-dedicated-node',
        'status' => 'ready',
        'public_ip' => '10.0.0.20',
        'total_ram_mb' => 16384,
        'last_heartbeat_at' => now(),
    ]);

    // Owner creates rule: pin all supabase templates to supabase-dedicated-node
    NodePlacementRule::create([
        'rule_type' => 'pin_template',
        'target_identifier' => 'supabase',
        'cluster_node_id' => $supabaseDedicatedNode->id,
        'priority' => 100,
        'is_active' => true,
    ]);

    // Deploying wordpress goes to general node
    $wpTarget = ClusterPlacementService::selectOptimalNode($team, 'wp-app', 'wordpress');
    expect($wpTarget->id)->toBe($generalNode->id);

    // Deploying supabase goes to pinned dedicated node
    $sbTarget = ClusterPlacementService::selectOptimalNode($team, 'sb-app', 'supabase');
    expect($sbTarget->id)->toBe($supabaseDedicatedNode->id);
});

test('draining a node relocates all hosted applications to healthy nodes', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $node1 = ClusterNode::create([
        'name' => 'node-to-retire',
        'status' => 'ready',
        'public_ip' => '10.0.0.30',
        'total_ram_mb' => 8192,
        'last_heartbeat_at' => now(),
    ]);

    $node2 = ClusterNode::create([
        'name' => 'node-destination',
        'status' => 'ready',
        'public_ip' => '10.0.0.40',
        'total_ram_mb' => 8192,
        'last_heartbeat_at' => now(),
    ]);

    // Provision App on Node 1
    $app = AppProvisioningService::provisionTemplate(
        $team,
        $user,
        'supabase',
        'prod-database',
        [],
        null,
        'prod-db',
        [],
        $node1->id
    );

    expect($app->cluster_node_id)->toBe($node1->id);
    expect($app->ip_address)->toBe('10.0.0.30');

    // Owner drains Node 1
    $results = NodeMigrationService::drainNode($node1);

    expect(count($results))->toBe(1);
    expect($results[0]['status'])->toBe('success');

    $node1->refresh();
    $app->refresh();

    expect($node1->status)->toBe('draining');
    expect($app->cluster_node_id)->toBe($node2->id);
    expect($app->ip_address)->toBe('10.0.0.40');
});

test('cluster node studio livewire component renders and handles cordon actions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $node = ClusterNode::create([
        'name' => 'studio-test-node',
        'status' => 'ready',
        'public_ip' => '10.0.0.50',
        'cpu_cores' => 4,
        'total_ram_mb' => 8192,
        'last_heartbeat_at' => now(),
    ]);

    Livewire::test(ClusterNodeStudio::class)
        ->assertSee('studio-test-node')
        ->assertSee('READY')
        ->call('cordonNode', $node->id);

    $node->refresh();
    expect($node->status)->toBe('maintenance');
});

