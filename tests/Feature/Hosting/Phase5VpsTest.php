<?php

/*
|--------------------------------------------------------------------------
| Phase 5: VPS delivery, panel access, restart, usage, files, terminal
|--------------------------------------------------------------------------
| The SSH layer itself (phpseclib) needs a real server, so it is replaced by a fake shell here; everything
| around it (rules, authorization, commands sent, data stored) is tested.
*/

use App\Exceptions\VpsAccessException;
use App\Jobs\BootstrapVpsPanelAccessJob;
use App\Livewire\Admin\Operations\ProvisioningQueue;
use App\Livewire\Portal\VpsPanel;
use App\Livewire\Portal\VpsTerminal;
use App\Models\Commerce\Order;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceCredential;
use App\Models\Commerce\ServiceInstance;
use App\Models\Commerce\VpsStatSample;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Commerce\VpsDeliveryOverdueNotification;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\PaymentReviewService;
use App\Services\Commerce\ProofStorageService;
use App\Services\Commerce\VpsFulfillmentService;
use App\Services\TerminalSessionService;
use App\Services\Vps\RemoteShell;
use App\Services\Vps\VpsConnectionFactory;
use App\Services\Vps\VpsPaths;
use App\Services\Vps\VpsStats;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

uses(RefreshDatabase::class);

/** A shell that records what it was asked to do. */
class P5FakeShell implements RemoteShell
{
    public array $commands = [];

    public array $uploads = [];

    public array $deleted = [];

    public array $listing = [];

    public string $statsOutput = '';

    public string $fingerprint = 'FP-ONE';

    public bool $disconnected = false;

    public function exec(string $command, int $timeoutSeconds = 15): string
    {
        $this->commands[] = $command;

        return str_contains($command, 'cpu_a=') ? $this->statsOutput : '';
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
        $this->commands[] = "rename {$oldPath} -> {$newPath}";
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
        $this->disconnected = true;
    }
}

class P5Factory extends VpsConnectionFactory
{
    public function __construct(public P5FakeShell $shell, public ?\Throwable $failWith = null) {}

    public array $credentialsSeen = [];

    protected function makeShell(string $host, int $port, string $user, mixed $credential): RemoteShell
    {
        $this->credentialsSeen[] = is_string($credential) ? 'password' : 'key';

        if ($this->failWith) {
            throw $this->failWith;
        }

        return $this->shell;
    }
}

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    Server::forceCreate(['id' => 0, 'name' => 'localhost', 'ip' => '127.0.0.1', 'team_id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
    Storage::fake('payment_proofs');
    Storage::fake('ssh-keys');
    config(['hosting.enforce_entitlements' => false]);
    $this->shell = new P5FakeShell;
});

function p5Staff(string $role = 'admin'): User
{
    $user = User::factory()->create();
    (Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]))
        ->members()->attach($user->id, ['role' => $role]);

    return $user->fresh();
}

function p5Client(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    return [$user->fresh(), $team];
}

function p5Png(int $unused = 0): UploadedFile
{
    static $n = 0;
    $n++;
    // A different size each time, so no two receipts in a test are ever identical.
    $img = imagecreatetruecolor(4 + $n, 4);
    imagefill($img, 0, 0, imagecolorallocate($img, 40, 90, 160));
    ob_start();
    imagepng($img);

    return UploadedFile::fake()->createWithContent('slip.png', ob_get_clean());
}

/** Place and pay an order for one product category. Returns the order. */
function p5PaidOrder(User $client, Team $team, string $category, int $quantity = 1): Order
{
    $variant = ProductVariant::with('pricingTiers')
        ->whereHas('product', fn ($q) => $q->where('category', $category))
        ->whereHas('pricingTiers', fn ($q) => $q->where('is_active', true))
        ->firstOrFail();
    $tier = $variant->pricingTiers->firstWhere('is_active', true);

    [, $invoice] = app(OrderService::class)->place($client, $team, [['variant' => $variant, 'billing_type' => $tier->billing_type, 'quantity' => $quantity]], 'USD');
    $proof = app(ProofStorageService::class)->store(p5Png(), $invoice, $client);
    app(PaymentReviewService::class)->approve($proof, p5Staff());

    return $invoice->order->fresh();
}

function p5Vps(User $user, Team $team, array $overrides = []): ServiceInstance
{
    $order = Order::create(['team_id' => $team->id, 'user_id' => $user->id, 'status' => 'completed', 'subtotal_cents' => 1, 'total_cents' => 1, 'currency' => 'USD']);
    $variant = ProductVariant::firstOrFail();

    $service = ServiceInstance::create(array_merge([
        'team_id' => $team->id, 'user_id' => $user->id, 'order_id' => $order->id,
        'product_variant_id' => $variant->id, 'service_type' => 'vps', 'status' => 'active',
        'name' => 'VPS-test', 'hostname' => 'vps.test', 'ip_address' => '203.0.113.50', 'os_image' => 'debian-12',
        'activated_at' => now(), 'renews_at' => now()->addDays(30),
    ], $overrides));

    $cred = new ServiceCredential;
    $cred->service_instance_id = $service->id;
    $cred->credential_type = 'root_password';
    $cred->username = 'root';
    $cred->setEncryptedSecretAttribute('delivered-secret');
    $cred->ssh_port = 22;
    $cred->save();

    return $service->fresh();
}

function p5PanelKey(ServiceInstance $service): PrivateKey
{
    $pair = generateSSHKey('ed25519');
    $key = PrivateKey::createAndStore(['name' => 'k-'.$service->uuid, 'description' => 't', 'private_key' => $pair['private'], 'team_id' => 0]);
    $service->forceFill(['panel_private_key_id' => $key->id])->save();

    return $key;
}

function p5UseShell(P5FakeShell $shell, ?\Throwable $fail = null): P5Factory
{
    $factory = new P5Factory($shell, $fail);
    app()->instance(VpsConnectionFactory::class, $factory);

    return $factory;
}

/* ---------- delivery queue ---------- */

test('[P5-QUEUE] a paid VPS order waits for hand delivery; an apps-only order is finished when paid', function () {
    Notification::fake();
    [$client, $team] = p5Client();

    $vps = p5PaidOrder($client, $team, 'vps');
    $app = p5PaidOrder($client, $team, 'app_hosting');

    expect($vps->status)->toBe('paid');
    expect($app->status)->toBe('completed');

    $pending = VpsFulfillmentService::pendingQuery()->pluck('id');
    expect($pending)->toContain($vps->id)->not->toContain($app->id);
});

test('[P5-QUEUE] delivering servers one by one moves the order to provisioning and then completed', function () {
    Notification::fake();
    Bus::fake([BootstrapVpsPanelAccessJob::class]);
    [$client, $team] = p5Client();
    $order = p5PaidOrder($client, $team, 'vps', 2);

    $deliver = function (string $ip) use ($order) {
        Livewire::actingAs(p5Staff())->test(ProvisioningQueue::class)
            ->call('selectOrder', $order->id)
            ->set('manual_ipv4', $ip)
            ->set('temporaryRootPassword', 'temp-pass-1')
            ->call('fulfill', $order->id);
    };

    $deliver('203.0.113.61');
    expect($order->fresh()->status)->toBe('provisioning');
    expect(VpsFulfillmentService::pendingQuery()->pluck('id'))->toContain($order->id);

    $deliver('203.0.113.62');
    expect($order->fresh()->status)->toBe('completed');
    expect(VpsFulfillmentService::pendingQuery()->count())->toBe(0);
    expect(ServiceInstance::where('order_id', $order->id)->count())->toBe(2);

    Bus::assertDispatched(BootstrapVpsPanelAccessJob::class, 2);
});

test('[P5-QUEUE] a client cannot open the provisioning queue', function () {
    [$client] = p5Client();
    $this->actingAs($client)->get('/admin/operations/provisioning')->assertForbidden();
});

test('[P5-SLA] staff are reminded once at 24, 48 and 72 hours', function () {
    Notification::fake();
    $staff = p5Staff('owner');
    [$client, $team] = p5Client();
    $order = p5PaidOrder($client, $team, 'vps');
    $invoice = $order->invoice;

    $invoice->update(['paid_at' => now()->subHours(5)]);
    $this->artisan('vps:check-delivery-sla');
    Notification::assertNotSentTo($staff, VpsDeliveryOverdueNotification::class);

    $invoice->update(['paid_at' => now()->subHours(30)]);
    $this->artisan('vps:check-delivery-sla');
    $this->artisan('vps:check-delivery-sla'); // second run must not repeat
    Notification::assertSentToTimes($staff, VpsDeliveryOverdueNotification::class, 1);
    expect((int) $order->fresh()->delivery_alert_hours)->toBe(24);

    $invoice->update(['paid_at' => now()->subHours(50)]);
    $this->artisan('vps:check-delivery-sla');
    expect((int) $order->fresh()->delivery_alert_hours)->toBe(48);

    $invoice->update(['paid_at' => now()->subHours(80)]);
    $this->artisan('vps:check-delivery-sla');
    expect((int) $order->fresh()->delivery_alert_hours)->toBe(72);
    Notification::assertSentToTimes($staff, VpsDeliveryOverdueNotification::class, 3);
});

test('[P5-SLA] the due time is 72 hours after payment approval', function () {
    Notification::fake();
    [$client, $team] = p5Client();
    $order = p5PaidOrder($client, $team, 'vps');
    $order->invoice->update(['paid_at' => now()->subHours(10)]);

    $due = VpsFulfillmentService::dueAt($order->fresh('invoice'));

    expect((int) round(now()->diffInHours($due, false)))->toBe(62);
});

/* ---------- usage readings ---------- */

test('[P5-STATS] a reading is parsed into CPU, memory, disk, load, uptime and traffic', function () {
    $output = <<<'OUT'
cpu_a=cpu  1000 0 500 8000 100 0 0 0 0 0
cpu_b=cpu  1100 0 550 8050 100 0 0 0 0 0
mem=2048000 1024000
disk=42949672960 10737418240
load=0.52
uptime=86400
net=5000000 2000000
OUT;

    $r = VpsStats::parse($output);

    expect($r['cpu_percent'])->toBe(75.0); // 150 busy of 200 ticks
    expect($r['mem_total_mb'])->toBe(2000);
    expect($r['mem_used_mb'])->toBe(1000);
    expect($r['disk_total_gb'])->toBe(40);
    expect($r['disk_used_gb'])->toBe(10.0);
    expect($r['load1'])->toBe(0.52);
    expect($r['uptime_seconds'])->toBe(86400);
    expect($r['rx_bytes'])->toBe(5000000)->and($r['tx_bytes'])->toBe(2000000);
});

test('[P5-STATS] garbage output does not crash and gives no CPU figure', function () {
    $r = VpsStats::parse("nonsense\n");
    expect($r['cpu_percent'])->toBeNull()->and($r['mem_total_mb'])->toBe(0);
});

test('[P5-STATS] traffic is the change between samples, and a reboot resets the baseline', function () {
    [$user, $team] = p5Client();
    $service = p5Vps($user, $team);
    $shell = new P5FakeShell;
    $mk = fn (int $rx, int $tx) => "cpu_a=cpu 1 0 1 100 0 0 0\ncpu_b=cpu 2 0 2 200 0 0 0\nmem=1024 512\ndisk=1073741824 536870912\nload=0.1\nuptime=60\nnet={$rx} {$tx}\n";

    $shell->statsOutput = $mk(1_000_000_000, 500_000_000);
    $first = VpsStats::collect($service, $shell);
    expect($first->rx_delta)->toBeNull();

    $shell->statsOutput = $mk(1_600_000_000, 700_000_000);
    $second = VpsStats::collect($service, $shell);
    expect($second->rx_delta)->toBe(600_000_000)->and($second->tx_delta)->toBe(200_000_000);

    $shell->statsOutput = $mk(10_000, 5_000); // counters restarted: server rebooted
    $third = VpsStats::collect($service, $shell);
    expect($third->rx_delta)->toBeNull();

    expect(VpsStats::monthlyTrafficGb($service))->toBe(0.8);
    expect(VpsStatSample::count())->toBe(3);
});

test('[P5-STATS] the collector only queues servers the panel can sign in to', function () {
    Bus::fake();
    [$user, $team] = p5Client();
    $with = p5Vps($user, $team);
    p5PanelKey($with);
    p5Vps($user, $team, ['ip_address' => '203.0.113.51']);

    $this->artisan('vps:collect-stats');

    Bus::assertDispatchedTimes(\App\Jobs\CollectVpsStatsJob::class, 1);
});

/* ---------- panel access and host key ---------- */

test('[P5-ACCESS] without panel access nothing connects and the customer is told what to do', function () {
    [$user, $team] = p5Client();
    $service = p5Vps($user, $team);
    $factory = p5UseShell($this->shell);

    expect(fn () => $factory->connect($service))->toThrow(VpsAccessException::class, 'Enter your current root password');
    expect($factory->credentialsSeen)->toBe([]);
});

test('[P5-ACCESS] bootstrap installs only the public key, stores the private key once, and uses the delivered password by default', function () {
    [$user, $team] = p5Client();
    $service = p5Vps($user, $team);
    $factory = p5UseShell($this->shell);

    $factory->bootstrap($service);

    $service->refresh();
    expect($service->panel_private_key_id)->not->toBeNull();
    $installed = collect($this->shell->commands)->first(fn ($c) => str_contains($c, 'authorized_keys'));
    expect($installed)->toContain('ssh-ed25519 ')->toContain('stx-panel')->not->toContain('PRIVATE KEY');
    expect($factory->credentialsSeen)->toBe(['password', 'key']); // installs with the password, then proves the key works

    // Idempotent
    $factory->bootstrap($service);
    expect(PrivateKey::where('name', 'like', 'VPS panel key%')->count())->toBe(1);
});

test('[P5-ACCESS] a password the customer types in is used and not stored', function () {
    [$user, $team] = p5Client();
    $service = p5Vps($user, $team);
    $service->credentials()->delete();
    p5UseShell($this->shell)->bootstrap($service, 'typed-once');

    expect($service->fresh()->panel_private_key_id)->not->toBeNull();
    expect(ServiceCredential::where('service_instance_id', $service->id)->count())->toBe(0);
});

test('[P5-ACCESS] no stored password and none typed means we cannot set it up', function () {
    [$user, $team] = p5Client();
    $service = p5Vps($user, $team);
    $service->credentials()->delete();

    expect(fn () => p5UseShell($this->shell)->bootstrap($service))->toThrow(VpsAccessException::class);
});

test('[P5-ACCESS] the server identity is pinned on first use and a change stops the connection', function () {
    [$user, $team] = p5Client();
    $service = p5Vps($user, $team);
    p5PanelKey($service);
    $factory = p5UseShell($this->shell);

    $factory->connect($service)->disconnect();
    expect($service->fresh()->host_key_sha256)->toBe('FP-ONE');

    $this->shell->fingerprint = 'FP-ATTACKER';
    expect(fn () => $factory->connect($service->fresh()))->toThrow(VpsAccessException::class, 'identity has changed');
    expect($this->shell->disconnected)->toBeTrue();
});

test('[P5-ACCESS] staff can reset a pinned host key after a legitimate reinstall', function () {
    [$user, $team] = p5Client();
    $service = p5Vps($user, $team, ['host_key_sha256' => 'OLD']);
    p5PanelKey($service);

    $this->artisan('vps:reset-host-key', ['uuid' => $service->uuid])->assertSuccessful();

    expect($service->fresh()->host_key_sha256)->toBeNull();
    expect($service->fresh()->panel_private_key_id)->toBeNull();
});

/* ---------- the client panel ---------- */

test('[P5-PANEL] only the owning team can open a VPS panel', function () {
    [$user, $team] = p5Client();
    [$other] = p5Client();
    $service = p5Vps($user, $team);

    session(['currentTeam' => $team]);
    Livewire::actingAs($user)->test(VpsPanel::class, ['uuid' => $service->uuid])->assertOk();

    $otherTeam = $other->teams()->first();
    session(['currentTeam' => $otherTeam]);
    Livewire::actingAs($other)->test(VpsPanel::class, ['uuid' => $service->uuid])->assertNotFound();
});

test('[P5-PANEL] inactive servers and non-VPS services have no panel', function () {
    [$user, $team] = p5Client();
    session(['currentTeam' => $team]);

    $suspended = p5Vps($user, $team, ['status' => 'suspended']);
    Livewire::actingAs($user)->test(VpsPanel::class, ['uuid' => $suspended->uuid])->assertNotFound();

    $app = p5Vps($user, $team, ['service_type' => 'web_app']);
    Livewire::actingAs($user)->test(VpsPanel::class, ['uuid' => $app->uuid])->assertNotFound();
});

test('[P5-PANEL] restart needs the word RESTART, sends a reboot (never a power off), and cannot be spammed', function () {
    [$user, $team] = p5Client();
    $service = p5Vps($user, $team);
    p5PanelKey($service);
    p5UseShell($this->shell);
    session(['currentTeam' => $team]);

    $panel = Livewire::actingAs($user)->test(VpsPanel::class, ['uuid' => $service->uuid]);

    $panel->set('restartConfirm', 'yes')->call('restart')->assertHasErrors('restartConfirm');
    expect($this->shell->commands)->toBe([]);

    $panel->set('restartConfirm', 'restart')->call('restart');
    expect($this->shell->commands)->toHaveCount(1);
    expect($this->shell->commands[0])->toContain('/sbin/reboot')->not->toContain('poweroff')->not->toContain('shutdown');

    // again straight away: refused
    $panel->set('restartConfirm', 'RESTART')->call('restart');
    expect($this->shell->commands)->toHaveCount(1);
    expect(\App\Models\Commerce\CommerceAuditLog::where('action', 'vps.restart_requested')->count())->toBe(1);
});

test('[P5-PANEL] a failed connection shows a plain message instead of an error page', function () {
    [$user, $team] = p5Client();
    $service = p5Vps($user, $team);
    p5PanelKey($service);
    p5UseShell($this->shell, VpsAccessException::unreachable());
    session(['currentTeam' => $team]);

    Livewire::actingAs($user)->test(VpsPanel::class, ['uuid' => $service->uuid])
        ->call('loadFiles')
        ->assertSee('could not reach your server');
});

test('[P5-PANEL] files: browse, download, upload and delete go through the shell, with audit entries', function () {
    [$user, $team] = p5Client();
    $service = p5Vps($user, $team);
    p5PanelKey($service);
    $this->shell->listing['/root'] = [['name' => 'notes.txt', 'type' => 'file', 'size' => 12, 'modified' => 1700000000]];
    p5UseShell($this->shell);
    session(['currentTeam' => $team]);

    $panel = Livewire::actingAs($user)->test(VpsPanel::class, ['uuid' => $service->uuid])
        ->call('loadFiles')
        ->assertSee('notes.txt');

    $panel->set('upload', UploadedFile::fake()->createWithContent('hello.txt', 'hi there'))->call('uploadFile');
    expect($this->shell->uploads)->toHaveKey('/root/hello.txt');
    expect($this->shell->uploads['/root/hello.txt'])->toBe('hi there');

    $panel->call('deleteEntry', 'notes.txt');
    expect($this->shell->deleted)->toBe(['/root/notes.txt']);

    expect(\App\Models\Commerce\CommerceAuditLog::whereIn('action', ['vps.file_uploaded', 'vps.file_deleted'])->count())->toBe(2);
});

test('[P5-PANEL] file names cannot escape the folder', function () {
    [$user, $team] = p5Client();
    $service = p5Vps($user, $team);
    p5PanelKey($service);
    p5UseShell($this->shell);
    session(['currentTeam' => $team]);

    Livewire::actingAs($user)->test(VpsPanel::class, ['uuid' => $service->uuid])
        ->call('deleteEntry', '../etc/passwd')
        ->assertSee('not valid');

    expect($this->shell->deleted)->toBe([]);
    expect(VpsPaths::normalize('/root/../../etc/./passwd'))->toBe('/etc/passwd');
    expect(VpsPaths::parent('/'))->toBe('/');
    expect(VpsPaths::safeName('..\\..\\evil.sh'))->toBe('evil.sh');
    expect(fn () => VpsPaths::normalize("/root/a\0b"))->toThrow(RuntimeException::class);
});

/* ---------- terminal ---------- */

test('[P5-TERMINAL] a shell session is a one-use token that turns into a key-only ssh command for the panel key', function () {
    [$user, $team] = p5Client();
    $service = p5Vps($user, $team);
    $key = p5PanelKey($service);
    session(['currentTeam' => $team]);
    $this->actingAs($user);

    $terminal = app(TerminalSessionService::class);
    $token = $terminal->issueForVps($user, $service);
    $command = $terminal->redeem($user, $token);

    // Same shape the terminal server already validates for team servers.
    expect($command)->toContain('ssh_key@'.$key->uuid)
        ->toContain('-o PasswordAuthentication=no')
        ->toContain('-o StrictHostKeyChecking=no')
        ->toContain('-o UserKnownHostsFile=/dev/null')
        ->toContain("'root'@'203.0.113.50'")
        ->toContain(' << \\')
        ->not->toContain('delivered-secret');
    expect(preg_match('/-p \'?22\'? /', $command))->toBe(1);

    // one use only
    expect(fn () => $terminal->redeem($user, $token))->toThrow(AccessDeniedHttpException::class);
});

test('[P5-TERMINAL] another user, another team or a server without panel access cannot get a session', function () {
    [$user, $team] = p5Client();
    [$other] = p5Client();
    $service = p5Vps($user, $team);
    session(['currentTeam' => $team]);
    $this->actingAs($user);
    $terminal = app(TerminalSessionService::class);

    // no panel key yet
    expect(fn () => $terminal->redeem($user, $terminal->issueForVps($user, $service)))->toThrow(AccessDeniedHttpException::class);

    p5PanelKey($service);
    $token = $terminal->issueForVps($user, $service);

    session(['currentTeam' => $other->teams()->first()]);
    $this->actingAs($other);
    expect(fn () => $terminal->redeem($other, $token))->toThrow(AccessDeniedHttpException::class);
});

test('[P5-TERMINAL] the terminal server may only reach VPS the signed-in team owns and the panel can sign in to', function () {
    [$user, $team] = p5Client();
    [$other, $otherTeam] = p5Client();
    $mine = p5Vps($user, $team, ['ip_address' => '203.0.113.70']);
    p5PanelKey($mine);
    $noKey = p5Vps($user, $team, ['ip_address' => '203.0.113.71']);
    $theirs = p5Vps($other, $otherTeam, ['ip_address' => '203.0.113.72']);
    p5PanelKey($theirs);

    session(['currentTeam' => $team]);
    $ips = $this->actingAs($user)->postJson('/terminal/auth/ips')->assertOk()->json('ipAddresses');

    expect($ips)->toContain('203.0.113.70')->not->toContain('203.0.113.71')->not->toContain('203.0.113.72');
});

test('[P5-TERMINAL] the component issues a token for your own VPS and refuses anyone else\'s', function () {
    [$user, $team] = p5Client();
    [$other] = p5Client();
    $service = p5Vps($user, $team);
    p5PanelKey($service);

    session(['currentTeam' => $team]);
    Livewire::actingAs($user)->test(VpsTerminal::class, ['serviceUuid' => $service->uuid])
        ->call('startVpsTerminal')
        ->assertDispatched('send-terminal-token');

    session(['currentTeam' => $other->teams()->first()]);
    Livewire::actingAs($other)->test(VpsTerminal::class, ['serviceUuid' => $service->uuid])
        ->call('startVpsTerminal')
        ->assertNotDispatched('send-terminal-token')
        ->assertDispatched('terminal-session-failed');
});
