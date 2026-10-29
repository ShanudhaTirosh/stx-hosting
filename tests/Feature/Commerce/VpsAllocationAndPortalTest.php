<?php

namespace Tests\Feature\Commerce;

use App\Livewire\Admin\Operations\ProvisioningQueue;
use App\Livewire\Portal\ServiceDetail;
use App\Livewire\Portal\ServicesList;
use App\Models\Commerce\Order;
use App\Models\Commerce\PricingTier;
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
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

test('vps allocation service locates and matches next available node with sufficient specs', function () {
    $service = new VpsAllocationService();

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

    $cred = new ServiceCredential();
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
