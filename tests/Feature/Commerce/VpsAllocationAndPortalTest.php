<?php

namespace Tests\Feature\Commerce;

use App\Actions\Commerce\DecommissionVpsAction;
use App\Livewire\Admin\Operations\ProvisioningQueue;
use App\Livewire\Portal\ServiceDetail;
use App\Livewire\Portal\ServicesList;
use App\Models\Commerce\Order;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceCredential;
use App\Models\Commerce\ServiceInstance;
use App\Models\Commerce\VpsInventory;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Services\Commerce\VpsAllocationService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

test('vps allocation service locates and matches next available node with sufficient specs', function () {
    $service = new VpsAllocationService;

    $node1 = VpsInventory::create([
        'ipv4_address' => '198.51.100.10',
        'vcpu' => 2,
        'ram_mb' => 2048,
        'disk_gb' => 40,
        'datacenter' => 'fsn1',
        'status' => 'available',
    ]);

    $node2 = VpsInventory::create([
        'ipv4_address' => '198.51.100.20',
        'vcpu' => 4,
        'ram_mb' => 8192,
        'disk_gb' => 80,
        'datacenter' => 'fsn1',
        'status' => 'available',
    ]);

    $cx32Variant = ProductVariant::where('slug', 'cx32')->firstOrFail(); // 4 vCPU, 8192 MB

    $allocatedNode = $service->allocateNextAvailable($cx32Variant, 'fsn1');
    expect($allocatedNode->id)->toBe($node2->id);
    expect($allocatedNode->ipv4_address)->toBe('198.51.100.20');
});

test('administrator can fulfill order in provisioning queue allocating node and storing encrypted credentials', function () {
    $admin = User::factory()->create();
    $customer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($admin->id, ['role' => 'admin']);
    $team->members()->attach($customer->id, ['role' => 'owner']);

    $cx22Variant = ProductVariant::where('slug', 'cx22')->firstOrFail();
    $tier = $cx22Variant->pricingTiers()->firstOrFail();

    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $customer->id,
        'status' => 'completed',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
    ]);

    $order->items()->create([
        'product_variant_id' => $cx22Variant->id,
        'pricing_tier_id' => $tier->id,
        'quantity' => 1,
        'unit_price_cents' => $tier->price_cents,
        'total_price_cents' => $tier->price_cents,
        'hostname' => 'test-customer-node.shanutechx.internal',
        'datacenter' => 'fsn1',
    ]);

    $availableNode = VpsInventory::create([
        'ipv4_address' => '198.51.100.99',
        'vcpu' => 2,
        'ram_mb' => 4096,
        'disk_gb' => 40,
        'datacenter' => 'fsn1',
        'status' => 'available',
    ]);

    $this->actingAs($admin);

    Livewire::test(ProvisioningQueue::class)
        ->call('selectOrder', $order->id)
        ->set('selectedNodeId', $availableNode->id)
        ->set('temporaryRootPassword', 'SuperSecretTemporaryRoot123!')
        ->set('osImage', 'debian-12')
        ->call('fulfill', $order->id)
        ->assertDispatched('success');

    $availableNode->refresh();
    expect($availableNode->status)->toBe('allocated');

    $service = ServiceInstance::where('order_id', $order->id)->firstOrFail();
    expect($service->status)->toBe('active');
    expect($service->ip_address)->toBe('198.51.100.99');
    expect($service->team_id)->toBe($team->id);

    $cred = $service->credentials()->firstOrFail();
    expect($cred->getDecryptedSecret())->toBe('SuperSecretTemporaryRoot123!');
});

test('customer can view service and reveal encrypted root password by confirming account password', function () {
    $customer = User::factory()->create([
        'password' => bcrypt('ValidCustomerPassword123!'),
    ]);
    $team = Team::factory()->create();
    $team->members()->attach($customer->id, ['role' => 'owner']);

    $cx22Variant = ProductVariant::where('slug', 'cx22')->firstOrFail();

    $service = ServiceInstance::create([
        'team_id' => $team->id,
        'user_id' => $customer->id,
        'product_variant_id' => $cx22Variant->id,
        'service_type' => 'vps',
        'status' => 'active',
        'name' => 'My Production VPS',
        'hostname' => 'prod.customer.io',
        'ip_address' => '198.51.100.150',
        'os_image' => 'debian-12',
    ]);

    $cred = new ServiceCredential;
    $cred->service_instance_id = $service->id;
    $cred->credential_type = 'root_password';
    $cred->username = 'root';
    $cred->setEncryptedSecretAttribute('DecryptedRootSecret9988!');
    $cred->save();

    $this->actingAs($customer);
    session(['currentTeam' => $team]);

    // Test ServicesList
    Livewire::test(ServicesList::class)
        ->assertSee('prod.customer.io')
        ->assertSee('198.51.100.150');

    // Test ServiceDetail password reveal with wrong password
    Livewire::test(ServiceDetail::class, ['uuid' => $service->uuid])
        ->set('accountPassword', 'WrongPassword!')
        ->call('revealRootPassword')
        ->assertHasErrors(['accountPassword']);

    // Test ServiceDetail password reveal with correct password
    Livewire::test(ServiceDetail::class, ['uuid' => $service->uuid])
        ->set('accountPassword', 'ValidCustomerPassword123!')
        ->call('revealRootPassword')
        ->assertSet('revealedPassword', 'DecryptedRootSecret9988!')
        ->assertDispatched('success');
});

test('administrator can fulfill order via direct manual details entry without pre-existing pool inventory', function () {
    $admin = User::factory()->create();
    $customer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($admin->id, ['role' => 'admin']);
    $team->members()->attach($customer->id, ['role' => 'owner']);

    $cx22Variant = ProductVariant::where('slug', 'cx22')->firstOrFail();
    $tier = $cx22Variant->pricingTiers()->firstOrFail();

    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $customer->id,
        'status' => 'payment_verified',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
    ]);

    $order->items()->create([
        'product_variant_id' => $cx22Variant->id,
        'pricing_tier_id' => $tier->id,
        'quantity' => 1,
        'unit_price_cents' => $tier->price_cents,
        'total_price_cents' => $tier->price_cents,
        'hostname' => 'manual-custom-vps.shanutechx.internal',
    ]);

    $this->actingAs($admin);

    Livewire::test(ProvisioningQueue::class)
        ->call('selectOrder', $order->id)
        ->set('fulfillmentMode', 'direct')
        ->set('manual_ipv4', '198.51.100.77')
        ->set('manual_ssh_port', 2222)
        ->set('manual_provider', 'Contabo')
        ->set('manual_datacenter', 'Singapore (SIN1)')
        ->set('temporaryRootPassword', 'DirectManualSecretPass#88')
        ->set('osImage', 'ubuntu-24-04')
        ->call('fulfill', $order->id)
        ->assertDispatched('success');

    $service = ServiceInstance::where('order_id', $order->id)->firstOrFail();
    expect($service->status)->toBe('active');
    expect($service->ip_address)->toBe('198.51.100.77');
    expect($service->os_image)->toBe('ubuntu-24-04');
    expect($service->network_config['provider'])->toBe('Contabo');
    expect($service->network_config['ssh_port'])->toBe(2222);
    expect($service->network_config['datacenter'])->toBe('Singapore (SIN1)');

    $cred = $service->credentials()->firstOrFail();
    expect($cred->getDecryptedSecret())->toBe('DirectManualSecretPass#88');
    expect($cred->ssh_port)->toBe(2222);
});

test('multi-tenant boundary isolates service instances between different teams', function () {
    $userA = User::factory()->create();
    $teamA = Team::factory()->create();
    $teamA->members()->attach($userA->id, ['role' => 'owner']);

    $userB = User::factory()->create();
    $teamB = Team::factory()->create();
    $teamB->members()->attach($userB->id, ['role' => 'owner']);

    $cx22Variant = ProductVariant::where('slug', 'cx22')->firstOrFail();

    $serviceA = ServiceInstance::create([
        'team_id' => $teamA->id,
        'user_id' => $userA->id,
        'product_variant_id' => $cx22Variant->id,
        'service_type' => 'vps',
        'status' => 'active',
        'name' => 'Team A Confidential VPS',
        'hostname' => 'confidential.team-a.io',
        'ip_address' => '198.51.100.88',
        'os_image' => 'debian-12',
    ]);

    // Acting as User B (Tenant B)
    $this->actingAs($userB);
    session(['currentTeam' => $teamB]);

    // User B attempting to view User A's service detail should fail authorization (403)
    Livewire::test(ServiceDetail::class, ['uuid' => $serviceA->uuid])
        ->assertForbidden();
});

test('decommission vps action transitions instance to terminated and releases inventory node', function () {
    $customer = User::factory()->create();
    $team = Team::factory()->create();
    $cx22Variant = ProductVariant::where('slug', 'cx22')->firstOrFail();

    $node = VpsInventory::create([
        'ipv4_address' => '198.51.100.99',
        'vcpu' => 2,
        'ram_mb' => 4096,
        'disk_gb' => 40,
        'datacenter' => 'fsn1',
        'status' => 'allocated',
    ]);

    $service = ServiceInstance::create([
        'team_id' => $team->id,
        'user_id' => $customer->id,
        'product_variant_id' => $cx22Variant->id,
        'vps_inventory_id' => $node->id,
        'service_type' => 'vps',
        'status' => 'active',
        'name' => 'Terminating Node',
        'hostname' => 'term.customer.io',
        'ip_address' => '198.51.100.99',
        'os_image' => 'debian-12',
    ]);

    DecommissionVpsAction::run($service, 'Customer requested shutdown');

    $service->refresh();
    expect($service->status)->toBe('terminated');

    $node->refresh();
    expect($node->status)->toBe('available');
});
