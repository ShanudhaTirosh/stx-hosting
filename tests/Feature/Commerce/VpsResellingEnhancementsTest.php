<?php

namespace Tests\Feature\Commerce;

use App\Livewire\Admin\Operations\ProvisioningQueue;
use App\Livewire\Portal\ServiceDetail;
use App\Livewire\Portal\ServicesList;
use App\Livewire\Portal\VpsPanel;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\Order;
use App\Models\Commerce\Product;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceCredential;
use App\Models\Commerce\ServiceInstance;
use App\Models\Commerce\VpsAuthorizedKey;
use App\Models\Commerce\VpsInventory;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Services\Commerce\CartService;
use App\Services\Commerce\VpsFulfillmentService;
use App\Services\Vps\RemoteShell;
use App\Services\Vps\VpsConnectionFactory;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

class FakeVpsShell implements RemoteShell
{
    public array $commands = [];
    public array $uploads = [];
    public array $renames = [];
    public array $deleted = [];
    public array $listing = [];
    public string $fingerprint = 'TEST-SHA256-FINGERPRINT';

    public function exec(string $command, int $timeoutSeconds = 15): string
    {
        $this->commands[] = $command;
        return 'ok';
    }

    public function listDirectory(string $path): array
    {
        return $this->listing[$path] ?? [];
    }

    public function download(string $path, int $maxBytes): string
    {
        return "contents of {$path}";
    }

    public function upload(string $path, string $contents): void
    {
        $this->uploads[$path] = $contents;
    }

    public function rename(string $oldPath, string $newPath): void
    {
        $this->renames[] = ['old' => $oldPath, 'new' => $newPath];
    }

    public function delete(string $path): void
    {
        $this->deleted[] = $path;
    }

    public function hostKeyFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function disconnect(): void
    {
    }
}

class FakeVpsFactory extends VpsConnectionFactory
{
    public bool $shouldRejectPassword = false;

    public function connect(ServiceInstance $service): RemoteShell
    {
        return new FakeVpsShell;
    }

    public function testCredentials(ServiceInstance $service, string $user, string $password): bool
    {
        if ($this->shouldRejectPassword) {
            throw new \RuntimeException('The server rejected that login. Check the password or key.');
        }

        return true;
    }

    public function bootstrap(ServiceInstance $service, ?string $password = null): void
    {
        $service->forceFill(['panel_private_key_id' => 1])->save();
    }
}

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

test('sold out plans are rejected by cart service', function () {
    $variant = ProductVariant::where('slug', 'cx22')->firstOrFail();
    $variant->update(['stock_status' => 'sold_out']);

    $cart = app(CartService::class);

    expect(fn () => $cart->add($variant->uuid, 'monthly'))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('paid VPS order displays pending delivery countdown banner in customer portal', function () {
    $customer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($customer->id, ['role' => 'owner']);

    $cx22 = ProductVariant::where('slug', 'cx22')->firstOrFail();
    $tier = $cx22->pricingTiers()->firstOrFail();

    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $customer->id,
        'status' => 'paid',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
    ]);

    $order->items()->create([
        'product_variant_id' => $cx22->id,
        'pricing_tier_id' => $tier->id,
        'quantity' => 1,
        'unit_price_cents' => $tier->price_cents,
        'total_price_cents' => $tier->price_cents,
        'hostname' => 'my-cloud-vps.internal',
        'datacenter' => 'fsn1',
    ]);

    $this->actingAs($customer);
    session(['currentTeam' => $team]);

    Livewire::test(ServicesList::class)
        ->assertSee('VPS Deployment in Progress')
        ->assertSee('24–72h Delivery Guarantee')
        ->assertSee('my-cloud-vps.internal');
});

test('admin can fulfill VPS with upstream cost and provider renewal date tracking', function () {
    $admin = User::factory()->create();
    $customer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($admin->id, ['role' => 'admin']);
    $team->members()->attach($customer->id, ['role' => 'owner']);

    $cx22 = ProductVariant::where('slug', 'cx22')->firstOrFail();
    $tier = $cx22->pricingTiers()->firstOrFail();

    $order = Order::create([
        'team_id' => $team->id,
        'user_id' => $customer->id,
        'status' => 'paid',
        'subtotal_cents' => $tier->price_cents,
        'total_cents' => $tier->price_cents,
        'currency' => 'USD',
    ]);

    $order->items()->create([
        'product_variant_id' => $cx22->id,
        'pricing_tier_id' => $tier->id,
        'quantity' => 1,
        'unit_price_cents' => $tier->price_cents,
        'total_price_cents' => $tier->price_cents,
        'hostname' => 'node-client.internal',
        'datacenter' => 'fsn1',
    ]);

    $this->actingAs($admin);

    Livewire::test(ProvisioningQueue::class)
        ->call('selectOrder', $order->id)
        ->set('manual_ipv4', '198.51.100.45')
        ->set('manual_ssh_port', 22)
        ->set('manual_provider', 'Hetzner')
        ->set('manual_datacenter', 'Falkenstein, Germany')
        ->set('upstream_cost_usd', 4.50)
        ->set('provider_renews_at', '2026-12-15')
        ->set('provider_invoice_ref', 'HETZNER-INV-9988')
        ->set('temporaryRootPassword', 'DeliveredPass123!')
        ->call('fulfill', $order->id)
        ->assertDispatched('success');

    $service = ServiceInstance::where('ip_address', '198.51.100.45')->firstOrFail();
    expect($service->upstream_cost_cents)->toBe(450);
    expect($service->provider_renews_at->format('Y-m-d'))->toBe('2026-12-15');
    expect($service->status)->toBe('active');

    $node = VpsInventory::where('ipv4_address', '198.51.100.45')->firstOrFail();
    expect($node->upstream_cost_cents)->toBe(450);
    expect($node->provider_invoice_ref)->toBe('HETZNER-INV-9988');
});

test('customer can test ssh connection and update changed root password with pre-flight check', function () {
    $customer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($customer->id, ['role' => 'owner']);
    $cx22 = ProductVariant::where('slug', 'cx22')->firstOrFail();

    $fakeFactory = new FakeVpsFactory;
    app()->instance(VpsConnectionFactory::class, $fakeFactory);

    $service = ServiceInstance::create([
        'team_id' => $team->id,
        'user_id' => $customer->id,
        'product_variant_id' => $cx22->id,
        'service_type' => 'vps',
        'status' => 'active',
        'name' => 'My Server',
        'hostname' => 'srv.customer.internal',
        'ip_address' => '198.51.100.77',
        'os_image' => 'debian-12',
        'panel_private_key_id' => 1,
    ]);

    $cred = new ServiceCredential([
        'service_instance_id' => $service->id,
        'credential_type' => 'root_password',
        'username' => 'root',
        'ssh_port' => 22,
    ]);
    $cred->setEncryptedSecretAttribute('OldOriginalPassword123');
    $cred->save();

    $this->actingAs($customer);
    session(['currentTeam' => $team]);

    // 1. Password matches server -> healthy
    $fakeFactory->shouldRejectPassword = false;
    Livewire::test(ServiceDetail::class, ['uuid' => $service->uuid])
        ->call('checkSshConnectivity')
        ->assertSet('sshCheckStatus', 'healthy');

    // 2. Customer changed password on server -> out_of_sync
    $fakeFactory->shouldRejectPassword = true;
    Livewire::test(ServiceDetail::class, ['uuid' => $service->uuid])
        ->call('checkSshConnectivity')
        ->assertSet('sshCheckStatus', 'out_of_sync')
        ->assertSee('Update Changed Password');

    // 3. Customer updates with invalid password -> rejected by server
    Livewire::test(ServiceDetail::class, ['uuid' => $service->uuid])
        ->set('newRootPassword', 'WrongPass')
        ->set('confirmNewRootPassword', 'WrongPass')
        ->call('updateChangedPassword')
        ->assertHasErrors(['newRootPassword']);

    // 4. Customer provides correct new password -> verified & updated
    $fakeFactory->shouldRejectPassword = false;
    Livewire::test(ServiceDetail::class, ['uuid' => $service->uuid])
        ->set('newRootPassword', 'BrandNewValidPass456!')
        ->set('confirmNewRootPassword', 'BrandNewValidPass456!')
        ->call('updateChangedPassword')
        ->assertDispatched('success');

    $cred->refresh();
    expect($cred->getDecryptedSecret())->toBe('BrandNewValidPass456!');

    // Check audit log
    expect(CommerceAuditLog::where('action', 'credential.password_updated')->where('target_id', $service->id)->exists())->toBeTrue();
});

test('customer can mark credentials as customer-managed purging password from vault', function () {
    $customer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($customer->id, ['role' => 'owner']);
    $cx22 = ProductVariant::where('slug', 'cx22')->firstOrFail();

    $service = ServiceInstance::create([
        'team_id' => $team->id,
        'user_id' => $customer->id,
        'product_variant_id' => $cx22->id,
        'service_type' => 'vps',
        'status' => 'active',
        'name' => 'Managed Server',
        'hostname' => 'managed.internal',
        'ip_address' => '198.51.100.80',
        'os_image' => 'debian-12',
        'panel_private_key_id' => 1,
    ]);

    $cred = new ServiceCredential([
        'service_instance_id' => $service->id,
        'credential_type' => 'root_password',
        'username' => 'root',
        'ssh_port' => 22,
    ]);
    $cred->setEncryptedSecretAttribute('InitialPass');
    $cred->save();

    $this->actingAs($customer);
    session(['currentTeam' => $team]);

    Livewire::test(ServiceDetail::class, ['uuid' => $service->uuid])
        ->call('markCustomerManaged')
        ->assertDispatched('success');

    $service->refresh();
    expect($service->is_customer_managed_credentials)->toBeTrue();
    expect($service->credentials()->where('credential_type', 'root_password')->exists())->toBeFalse();
});

test('customer can add and remove authorized ssh public keys', function () {
    $customer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($customer->id, ['role' => 'owner']);
    $cx22 = ProductVariant::where('slug', 'cx22')->firstOrFail();

    $fakeFactory = new FakeVpsFactory;
    app()->instance(VpsConnectionFactory::class, $fakeFactory);

    $service = ServiceInstance::create([
        'team_id' => $team->id,
        'user_id' => $customer->id,
        'product_variant_id' => $cx22->id,
        'service_type' => 'vps',
        'status' => 'active',
        'name' => 'SSH Key Server',
        'hostname' => 'sshkey.internal',
        'ip_address' => '198.51.100.90',
        'os_image' => 'debian-12',
        'panel_private_key_id' => 1,
    ]);

    $this->actingAs($customer);
    session(['currentTeam' => $team]);

    $pubKey = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIGdK/jV+5t9Z5F1K9mN+2P customer@macbook';

    Livewire::test(ServiceDetail::class, ['uuid' => $service->uuid])
        ->set('newKeyName', 'My MacBook')
        ->set('newPublicKey', $pubKey)
        ->call('addAuthorizedKey')
        ->assertDispatched('success');

    $installedKey = VpsAuthorizedKey::where('service_instance_id', $service->id)->firstOrFail();
    expect($installedKey->name)->toBe('My MacBook');
    expect($installedKey->public_key)->toBe($pubKey);

    // Delete key
    Livewire::test(ServiceDetail::class, ['uuid' => $service->uuid])
        ->call('deleteAuthorizedKey', $installedKey->id)
        ->assertDispatched('success');

    expect(VpsAuthorizedKey::where('id', $installedKey->id)->exists())->toBeFalse();
});

test('sftp file manager supports editing and renaming files', function () {
    $customer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($customer->id, ['role' => 'owner']);
    $cx22 = ProductVariant::where('slug', 'cx22')->firstOrFail();

    $fakeFactory = new FakeVpsFactory;
    app()->instance(VpsConnectionFactory::class, $fakeFactory);

    $service = ServiceInstance::create([
        'team_id' => $team->id,
        'user_id' => $customer->id,
        'product_variant_id' => $cx22->id,
        'service_type' => 'vps',
        'status' => 'active',
        'name' => 'File Server',
        'hostname' => 'files.internal',
        'ip_address' => '198.51.100.95',
        'os_image' => 'debian-12',
        'panel_private_key_id' => 1,
    ]);

    $this->actingAs($customer);
    session(['currentTeam' => $team]);

    $panel = Livewire::test(VpsPanel::class, ['uuid' => $service->uuid])
        ->call('loadFiles')
        ->call('editFile', 'nginx.conf')
        ->assertSet('showEditModal', true)
        ->assertSet('editingFileName', 'nginx.conf')
        ->set('fileContent', 'server { listen 80; }')
        ->call('saveFileContent')
        ->assertDispatched('success');

    expect(CommerceAuditLog::where('action', 'vps.file_edited')->exists())->toBeTrue();

    // Test rename
    $panel->call('openRename', 'old_app.env')
        ->set('renameNewName', 'new_app.env')
        ->call('renameEntry')
        ->assertDispatched('success');

    expect(CommerceAuditLog::where('action', 'vps.file_renamed')->exists())->toBeTrue();
});

