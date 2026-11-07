<?php

/*
|--------------------------------------------------------------------------
| Phase 4: paid plans unlock deployments; domains
|--------------------------------------------------------------------------
*/

use App\Exceptions\PlatformRestrictionException;
use App\Livewire\Portal\Domains;
use App\Models\Application;
use App\Models\Commerce\ProductVariant;
use App\Models\CustomDomain;
use App\Models\Environment;
use App\Models\HostingEntitlement;
use App\Models\HostingLimit;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\StandalonePostgresql;
use App\Models\Team;
use App\Models\User;
use App\Services\Commerce\CustomDomainService;
use App\Services\Commerce\DeploymentGate;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\PaymentReviewService;
use App\Services\Commerce\PlatformDomainService;
use App\Services\Commerce\ProofStorageService;
use App\Services\LimitEnforcementService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'hosting.enforce_entitlements' => true,
        'hosting.apps_domain' => 'apps.example.test',
        'hosting.ingress_ip' => '203.0.113.10',
    ]);
    CustomDomainService::$txtResolver = null;

    InstanceSettings::forceCreate(['id' => 0]);
    $this->server = Server::forceCreate(['id' => 0, 'name' => 'localhost', 'ip' => '127.0.0.1', 'team_id' => 0]);
    $this->destination = StandaloneDocker::where('server_id', 0)->first()
        ?? StandaloneDocker::create(['name' => 'default-docker', 'network' => 'net-'.Str::random(6), 'server_id' => 0]);
    Storage::fake('payment_proofs');
});

function p4Root(): Team
{
    return Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
}

function p4Client(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    return [$user->fresh(), $team];
}

function p4Staff(string $role): User
{
    $user = User::factory()->create();
    p4Root()->members()->attach($user->id, ['role' => $role]);

    return $user->fresh();
}

function p4Env(Team $team): Environment
{
    $project = Project::create(['name' => 'p-'.Str::random(5), 'team_id' => $team->id]);

    return $project->environments()->first() ?? Environment::create(['name' => 'production', 'project_id' => $project->id]);
}

/** Built without events so setup does not depend on the rules under test. */
function p4App(Team $team, array $attrs = []): Application
{
    $env = p4Env($team);

    return Application::withoutEvents(fn () => Application::factory()->create(array_merge([
        'environment_id' => $env->id,
        'destination_id' => test()->destination->id,
        'destination_type' => StandaloneDocker::class,
    ], $attrs)));
}

function p4Entitle(Team $team, string $type = 'app_hosting', ?\DateTimeInterface $expires = null, array $meta = []): HostingEntitlement
{
    return HostingEntitlement::create([
        'team_id' => $team->id,
        'product_type' => $type,
        'status' => 'active',
        'expires_at' => $expires ?? now()->addDays(30),
        'metadata' => $meta,
    ]);
}

/* ---------- deployments need a paid plan ---------- */

test('[P4-GATE] a team with no plan cannot deploy, from any entry point', function () {
    Queue::fake();
    [, $team] = p4Client();
    $app = p4App($team);

    $result = queue_application_deployment(application: $app, deployment_uuid: (string) Str::uuid(), is_webhook: true);

    expect($result['status'])->toBe('queue_full');
    expect($result['denied'])->toBeTrue();
    expect($result['message'])->toContain('no active plan');
    expect(\App\Models\ApplicationDeploymentQueue::count())->toBe(0);
});

test('[P4-GATE] an active plan lets the team deploy, including from a push webhook', function () {
    Queue::fake();
    [, $team] = p4Client();
    p4Entitle($team);
    $app = p4App($team);

    $result = queue_application_deployment(application: $app, deployment_uuid: (string) Str::uuid(), is_webhook: true);

    expect($result)->toHaveKey('deployment');
    expect($result['status'] ?? null)->not->toBe('queue_full');
});

test('[P4-GATE] an expired plan stops deployments', function () {
    Queue::fake();
    [, $team] = p4Client();
    p4Entitle($team, 'app_hosting', now()->subDay());
    $app = p4App($team);

    $result = queue_application_deployment(application: $app, deployment_uuid: (string) Str::uuid(), is_webhook: true);

    expect($result['denied'] ?? false)->toBeTrue();
});

test('[P4-GATE] a VPS plan does not unlock app hosting, and an app plan does not unlock databases', function () {
    [, $team] = p4Client();
    p4Entitle($team, 'vps');
    $app = p4App($team);

    expect(DeploymentGate::checkDeploy($app)->isAllowed())->toBeFalse();

    p4Entitle($team, 'app_hosting');
    expect(DeploymentGate::checkDeploy($app)->isAllowed())->toBeTrue();

    $db = new StandalonePostgresql(['environment_id' => $app->environment_id]);
    expect(DeploymentGate::checkCreate($db)->isAllowed())->toBeFalse();

    p4Entitle($team, 'managed_database');
    expect(DeploymentGate::checkCreate($db)->isAllowed())->toBeTrue();
});

test('[P4-GATE] the platform team and staff are never blocked', function () {
    Queue::fake();
    $rootApp = p4App(p4Root());
    expect(DeploymentGate::checkDeploy($rootApp)->isAllowed())->toBeTrue();

    [, $team] = p4Client();
    $clientApp = p4App($team);
    $this->actingAs(p4Staff('owner'));
    expect(DeploymentGate::checkDeploy($clientApp)->isAllowed())->toBeTrue();
});

test('[P4-GATE] redeploying a running app does not count against the container quota', function () {
    [, $team] = p4Client();
    p4Entitle($team);
    HostingLimit::create(['team_id' => $team->id, 'max_running_containers' => 1]);

    $running = p4App($team, ['status' => 'running:healthy']);
    $stopped = p4App($team, ['status' => 'exited']);

    expect(DeploymentGate::checkDeploy($running)->isAllowed())->toBeTrue();
    expect(DeploymentGate::checkDeploy($stopped)->isAllowed())->toBeFalse();
});

test('[P4-GATE] turning enforcement off lets everything through (development only)', function () {
    config(['hosting.enforce_entitlements' => false]);
    [, $team] = p4Client();

    expect(DeploymentGate::checkDeploy(p4App($team))->isAllowed())->toBeTrue();
});

/* ---------- creating resources ---------- */

test('[P4-CREATE] a signed-in client without a plan cannot create an application', function () {
    [$user, $team] = p4Client();
    $env = p4Env($team);
    $this->actingAs($user);

    expect(fn () => Application::factory()->create([
        'environment_id' => $env->id, 'destination_id' => $this->destination->id, 'destination_type' => StandaloneDocker::class,
    ]))->toThrow(PlatformRestrictionException::class);
});

test('[P4-CREATE] with a plan the client can create it, and system work is not blocked', function () {
    [$user, $team] = p4Client();
    $env = p4Env($team);
    $attrs = ['environment_id' => $env->id, 'destination_id' => $this->destination->id, 'destination_type' => StandaloneDocker::class];

    // No signed-in person (queue, webhook, seeder): never blocked.
    expect(Application::factory()->create($attrs)->exists)->toBeTrue();

    p4Entitle($team);
    $this->actingAs($user);
    expect(Application::factory()->create($attrs)->exists)->toBeTrue();
});

/* ---------- plan specs become the team's limits ---------- */

test('[P4-LIMITS] what the team bought sets its CPU and memory caps; an explicit override wins', function () {
    [, $team] = p4Client();
    p4Entitle($team, 'app_hosting', null, ['specs' => ['vcpu' => 2, 'ram_mb' => 2048, 'disk_gb' => 20, 'bandwidth_gb' => 500]]);
    p4Entitle($team, 'app_hosting', null, ['specs' => ['vcpu' => 1, 'ram_mb' => 512, 'disk_gb' => 5, 'bandwidth_gb' => 100]]);

    $limits = LimitEnforcementService::getEffectiveLimits($team);
    expect((float) $limits['max_cpu'])->toBe(3.0);
    expect((int) $limits['max_ram_mb'])->toBe(2560);
    expect((int) $limits['max_storage_gb'])->toBe(25);

    HostingLimit::create(['team_id' => $team->id, 'max_cpu' => 8.0, 'max_ram_mb' => 16384, 'max_applications' => 50]);
    expect((float) LimitEnforcementService::getEffectiveLimits($team)['max_cpu'])->toBe(8.0);
});

test('[P4-LIMITS] approving an app plan records its specs on the entitlement', function () {
    Notification::fake();
    $this->seed(HostingCatalogSeeder::class);
    $staff = p4Staff('admin');
    [$client, $team] = p4Client();

    $variant = ProductVariant::with(['product', 'pricingTiers'])
        ->whereHas('product', fn ($q) => $q->where('category', 'app_hosting'))
        ->whereHas('pricingTiers', fn ($q) => $q->where('is_active', true))
        ->firstOrFail();
    $tier = $variant->pricingTiers->firstWhere('is_active', true);

    [, $invoice] = app(OrderService::class)->place($client, $team, [['variant' => $variant, 'billing_type' => $tier->billing_type, 'quantity' => 1]], 'USD');
    $png = imagecreatetruecolor(3, 3);
    ob_start();
    imagepng($png);
    $bytes = ob_get_clean();
    $proof = app(ProofStorageService::class)->store(UploadedFile::fake()->createWithContent('s.png', $bytes), $invoice, $client);
    app(PaymentReviewService::class)->approve($proof, $staff);

    $entitlement = HostingEntitlement::where('team_id', $team->id)->firstOrFail();
    expect($entitlement->product_type)->toBe('app_hosting');
    expect($entitlement->metadata['specs']['ram_mb'])->toBe((int) $variant->ram_mb);
    expect(app(\App\Services\EntitlementService::class)::hasCapability($team, 'app_hosting'))->toBeTrue();
});

/* ---------- hostnames ---------- */

test('[P4-DOMAIN] hosts are read from comma separated fqdn values', function () {
    expect(PlatformDomainService::hostsOf('https://A.example.com, b.example.com:8080/path'))->toBe(['a.example.com', 'b.example.com']);
    expect(PlatformDomainService::hostsOf(null))->toBe([]);
});

test('[P4-DOMAIN] platform names: free ones pass, reserved and taken ones do not', function () {
    [, $team] = p4Client();
    [, $other] = p4Client();

    PlatformDomainService::assertAllowed($team, 'https://my-site.apps.example.test');

    expect(fn () => PlatformDomainService::assertAllowed($team, 'https://admin.apps.example.test'))->toThrow(PlatformRestrictionException::class);
    expect(fn () => PlatformDomainService::assertAllowed($team, 'https://apps.example.test'))->toThrow(PlatformRestrictionException::class);

    p4App($other, ['fqdn' => 'https://taken.apps.example.test']);
    expect(fn () => PlatformDomainService::assertAllowed($team, 'https://taken.apps.example.test'))->toThrow(PlatformRestrictionException::class);
});

test('[P4-DOMAIN] a custom domain works only after the team verified it, and only for that team', function () {
    [, $team] = p4Client();
    [, $other] = p4Client();

    expect(fn () => PlatformDomainService::assertAllowed($team, 'https://shop.example.org'))->toThrow(PlatformRestrictionException::class);

    $domain = CustomDomain::create(['team_id' => $team->id, 'domain' => 'example.org', 'token' => 'abc', 'verified_at' => now()]);

    PlatformDomainService::assertAllowed($team, 'https://example.org');
    PlatformDomainService::assertAllowed($team, 'https://www.example.org, https://shop.example.org');
    expect(fn () => PlatformDomainService::assertAllowed($other, 'https://example.org'))->toThrow(PlatformRestrictionException::class);
    expect(fn () => PlatformDomainService::assertAllowed($team, 'https://evil-example.org'))->toThrow(PlatformRestrictionException::class);
    expect($domain->isVerified())->toBeTrue();
});

test('[P4-DOMAIN] Coolify generated sslip hosts are allowed', function () {
    [, $team] = p4Client();

    PlatformDomainService::assertAllowed($team, 'http://abc123.203.0.113.10.sslip.io');
    expect(true)->toBeTrue();
});

test('[P4-DOMAIN] a signed-in client cannot put an unproven domain on their app', function () {
    [$user, $team] = p4Client();
    p4Entitle($team);
    $app = p4App($team);
    $this->actingAs($user);

    $app->fqdn = 'https://not-mine.example.com';
    expect(fn () => $app->save())->toThrow(PlatformRestrictionException::class);

    CustomDomain::create(['team_id' => $team->id, 'domain' => 'mine.example.com', 'token' => 't', 'verified_at' => now()]);
    $app->fqdn = 'https://mine.example.com';
    $app->save();
    expect($app->fresh()->fqdn)->toBe('https://mine.example.com');
});

test('[P4-DOMAIN] new client apps get <name>.<apps domain>, unique and never reserved', function () {
    [, $team] = p4Client();
    p4Entitle($team);
    $env = p4Env($team);
    $attrs = fn ($name) => ['name' => $name, 'environment_id' => $env->id, 'destination_id' => $this->destination->id, 'destination_type' => StandaloneDocker::class, 'fqdn' => null];

    $first = Application::factory()->create($attrs('My Shop'));
    expect($first->fresh()->fqdn)->toBe('https://my-shop.apps.example.test');

    $second = Application::factory()->create($attrs('My Shop'));
    expect($second->fresh()->fqdn)->toStartWith('https://my-shop-')->and($second->fresh()->fqdn)->not->toBe($first->fresh()->fqdn);

    $reserved = Application::factory()->create($attrs('admin'));
    expect($reserved->fresh()->fqdn)->not->toBe('https://admin.apps.example.test');
});

test('[P4-DOMAIN] the platform team keeps whatever domain it sets', function () {
    $app = p4App(p4Root(), ['fqdn' => 'https://panel.example.test']);
    expect($app->fresh()->fqdn)->toBe('https://panel.example.test');
});

/* ---------- proving ownership ---------- */

test('[P4-VERIFY] input is cleaned up and invalid or platform names are refused', function () {
    expect(CustomDomainService::normalize(' HTTPS://Shop.Example.COM:443/path '))->toBe('shop.example.com');
    expect(fn () => CustomDomainService::normalize('localhost'))->toThrow(PlatformRestrictionException::class);
    expect(fn () => CustomDomainService::normalize('not a domain'))->toThrow(PlatformRestrictionException::class);
    expect(fn () => CustomDomainService::normalize('203.0.113.9'))->toThrow(PlatformRestrictionException::class);

    [, $team] = p4Client();
    expect(fn () => app(CustomDomainService::class)->request($team, 'thing.apps.example.test'))->toThrow(PlatformRestrictionException::class);
});

test('[P4-VERIFY] a domain verifies only when the TXT record carries its token', function () {
    [, $team] = p4Client();
    $service = app(CustomDomainService::class);
    $domain = $service->request($team, 'example.net');

    expect($service->request($team, 'example.net')->id)->toBe($domain->id); // idempotent

    CustomDomainService::$txtResolver = fn () => ['something else'];
    expect($service->verify($domain))->toBeFalse();
    expect($domain->fresh()->isVerified())->toBeFalse();

    CustomDomainService::$txtResolver = fn (string $host) => $host === '_stx-verify.example.net' ? [$domain->txtValue()] : [];
    expect($service->verify($domain))->toBeTrue();
    expect($domain->fresh()->isVerified())->toBeTrue();
});

test('[P4-VERIFY] a domain another account already verified cannot be claimed', function () {
    [, $a] = p4Client();
    [, $b] = p4Client();
    CustomDomain::create(['team_id' => $a->id, 'domain' => 'taken.example.net', 'token' => 'x', 'verified_at' => now()]);

    expect(fn () => app(CustomDomainService::class)->request($b, 'taken.example.net'))->toThrow(PlatformRestrictionException::class);
    expect(fn () => app(CustomDomainService::class)->request($b, 'sub.taken.example.net'))->toThrow(PlatformRestrictionException::class);
});

/* ---------- the Domains page ---------- */

test('[P4-PAGE] a client adds a domain, sees the DNS record, and verifies it', function () {
    [$user, $team] = p4Client();
    session(['currentTeam' => $team]);

    $page = Livewire::actingAs($user)->test(Domains::class)
        ->set('newDomain', 'example.com')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSee('_stx-verify.example.com');

    $domain = CustomDomain::where('team_id', $team->id)->firstOrFail();
    CustomDomainService::$txtResolver = fn () => [$domain->txtValue()];

    $page->call('verify', $domain->uuid)->assertSee('Verified');
    expect($domain->fresh()->isVerified())->toBeTrue();
});

test('[P4-PAGE] a client cannot see, verify or remove another team\'s domain', function () {
    [$user, $team] = p4Client();
    [, $other] = p4Client();
    session(['currentTeam' => $team]);
    $theirs = CustomDomain::create(['team_id' => $other->id, 'domain' => 'theirs.example.com', 'token' => 't']);

    Livewire::actingAs($user)->test(Domains::class)->assertDontSee('theirs.example.com');

    try {
        Livewire::actingAs($user)->test(Domains::class)->call('remove', $theirs->uuid);
    } catch (\Throwable) {
        // a 404 for someone else's domain is the expected outcome
    }
    expect(CustomDomain::count())->toBe(1);
});

test('[P4-PAGE] a domain that an app still uses cannot be removed', function () {
    [$user, $team] = p4Client();
    session(['currentTeam' => $team]);
    $domain = CustomDomain::create(['team_id' => $team->id, 'domain' => 'inuse.example.com', 'token' => 't', 'verified_at' => now()]);
    p4App($team, ['fqdn' => 'https://www.inuse.example.com']);

    Livewire::actingAs($user)->test(Domains::class)->call('remove', $domain->uuid);

    expect(CustomDomain::count())->toBe(1);
});

test('[P4-PAGE] visitors cannot open the Domains page', function () {
    $this->get('/portal/domains')->assertRedirect();
});
