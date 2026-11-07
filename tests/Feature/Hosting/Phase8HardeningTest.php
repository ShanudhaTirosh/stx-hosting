<?php

/*
|--------------------------------------------------------------------------
| Phase 8: help center, staff two-factor, headers, audit log, checks, API contract
|--------------------------------------------------------------------------
*/

use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\Commerce\ProductCatalogStudio;
use App\Livewire\Admin\Billing\ProofReviewQueue;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\PaymentMethod;
use App\Models\Commerce\PricingTier;
use App\Models\Commerce\Product;
use App\Models\InstanceSettings;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Services\Docs\DocsRepository;
use App\Services\RbacService;
use App\Services\Security\SecurityChecker;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Symfony\Component\Yaml\Yaml;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['security.require_staff_2fa' => true]);
    InstanceSettings::forceCreate(['id' => 0]);
    Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    Server::forceCreate(['id' => 0, 'name' => 'localhost', 'ip' => '127.0.0.1', 'team_id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
    DocsRepository::$basePath = null;
});

function p8Staff(string $role = 'admin', bool $twoFactor = true): User
{
    $u = User::factory()->create();
    Team::find(0)->members()->attach($u->id, ['role' => $role]);
    $u->forceFill(['two_factor_confirmed_at' => $twoFactor ? now() : null])->save();

    return $u->fresh();
}

function p8Client(): User
{
    $u = User::factory()->create();
    Team::factory()->create()->members()->attach($u->id, ['role' => 'owner']);

    return $u->fresh();
}

/* ---------- staff two-factor ---------- */

test('[P8-2FA] staff without two-factor are treated as customers until they turn it on', function () {
    $without = p8Staff('owner', false);
    $with = p8Staff('owner', true);

    expect(RbacService::isStaff($without))->toBeFalse();
    expect(RbacService::platformRole($without))->toBe('client');
    expect(RbacService::can($without, 'payments.verify'))->toBeFalse();
    expect(RbacService::isPlatformOwner($without))->toBeFalse();
    expect(RbacService::shouldBypassLimits($without))->toBeFalse();
    expect(RbacService::staffNeedsTwoFactor($without))->toBeTrue();

    expect(RbacService::isStaff($with))->toBeTrue();
    expect(RbacService::platformRole($with))->toBe('owner');
    expect(RbacService::staffNeedsTwoFactor($with))->toBeFalse();
});

test('[P8-2FA] a staff page sends them to turn two-factor on instead of showing it', function () {
    $this->actingAs(p8Staff('admin', false))->get('/admin/billing/proofs')->assertRedirect(route('profile'));
    $this->actingAs(p8Staff('owner', false))->get('/admin/emails')->assertRedirect(route('profile'));
    $this->actingAs(p8Staff('admin', true))->get('/admin/billing/proofs')->assertOk();
});

test('[P8-2FA] the staff actions themselves are closed too, not just the pages', function () {
    Livewire::actingAs(p8Staff('admin', false))->test(ProofReviewQueue::class)->assertForbidden();
});

test('[P8-2FA] customers are not affected, and the rule can be switched off for development', function () {
    expect(RbacService::staffNeedsTwoFactor(p8Client()))->toBeFalse();
    $this->actingAs(p8Client())->get('/portal')->assertOk();

    config(['security.require_staff_2fa' => false]);
    expect(RbacService::isStaff(p8Staff('admin', false)))->toBeTrue();
});

/* ---------- headers ---------- */

test('[P8-HEADERS] every page gets the baseline headers; HSTS only over HTTPS', function () {
    $http = $this->get('/login');
    expect($http->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($http->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
    expect($http->headers->get('X-Frame-Options'))->not->toBeNull();
    expect($http->headers->get('Permissions-Policy'))->toContain('camera=()');
    expect($http->headers->has('Strict-Transport-Security'))->toBeFalse();

    $https = $this->withServerVariables(['HTTPS' => 'on'])->get('/login');
    expect($https->headers->get('Strict-Transport-Security'))->toContain('max-age=');
    expect($https->headers->get('Strict-Transport-Security'))->not->toContain('includeSubDomains');
});

test('[P8-HEADERS] the storefront forbids scripts, framing and foreign forms', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->toContain("script-src 'none'")->toContain("frame-ancestors 'none'")->toContain("form-action 'self'")->toContain("object-src 'none'");
    expect($this->get('/')->headers->get('X-Frame-Options'))->toBe('DENY');
});

test('[P8-HEADERS] the storefront really has no scripts, so the strict policy breaks nothing', function (string $path) {
    $html = $this->get($path)->assertOk()->getContent();

    expect(strtolower($html))->not->toContain('<script')->not->toContain(' onclick=')->not->toContain('javascript:');
})->with(['/', '/pricing', '/vps', '/cart', '/about', '/faqs', '/contact', '/terms', '/docs']);

test('[P8-HEADERS] the app itself is not given the storefront policy (it needs scripts)', function () {
    expect($this->get('/login')->headers->has('Content-Security-Policy'))->toBeFalse();
});

/* ---------- rate limits ---------- */

test('[P8-LIMITS] downloads, invoices and the terminal endpoints are rate limited', function () {
    $mw = fn (string $name) => Route::getRoutes()->getByName($name)->gatherMiddleware();

    expect($mw('support.attachment'))->toContain('throttle:60,1');
    expect($mw('invoices.show'))->toContain('throttle:60,1');
    expect($mw('invoices.pdf'))->toContain('throttle:30,1');
    expect($mw('terminal.session'))->toContain('throttle:60,1');
});

/* ---------- audit log ---------- */

test('[P8-AUDIT] entries can be written but never changed or removed', function () {
    $entry = CommerceAuditLog::record('test.thing', null, null, ['a' => 1]);

    expect(fn () => $entry->update(['action' => 'edited']))->toThrow(LogicException::class);
    expect(fn () => $entry->delete())->toThrow(LogicException::class);
    expect(CommerceAuditLog::find($entry->id)->action)->toBe('test.thing');
});

test('[P8-AUDIT] price and catalog changes are recorded with before and after', function () {
    $owner = p8Staff('owner');
    $tier = PricingTier::firstOrFail();
    $product = Product::firstOrFail();
    $studio = Livewire::actingAs($owner)->test(ProductCatalogStudio::class);

    $studio->call('toggleProductActive', $product->id);
    $studio->call('deletePricingTier', $tier->id);

    expect(CommerceAuditLog::where('action', 'catalog.product_toggled')->count())->toBe(1);
    $deleted = CommerceAuditLog::where('action', 'catalog.price_deleted')->firstOrFail();
    expect($deleted->payload['price_cents'])->toBe((int) $tier->price_cents);
    expect($deleted->user_id)->toBe($owner->id);
});

test('[P8-AUDIT] staff can read the log and filter it; customers cannot', function () {
    CommerceAuditLog::record('payment_proof.approved', null, null, ['n' => 1]);
    CommerceAuditLog::record('backup.enabled', null, null, ['n' => 2]);

    $this->actingAs(p8Client())->get('/admin/audit')->assertForbidden();
    $this->actingAs(p8Staff('admin'))->get('/admin/audit')->assertOk();

    Livewire::actingAs(p8Staff('admin'))->test(AuditLog::class)
        ->assertSee('payment_proof.approved')->assertSee('backup.enabled')
        ->set('area', 'backup')->assertSee('backup.enabled')->assertDontSee('payment_proof.approved');
});

/* ---------- the security checklist ---------- */

function p8Rows(): array
{
    return collect(app(SecurityChecker::class)->run())->keyBy('check')->all();
}

test('[P8-CHECK] a production setup with debug on, plans off and a sync queue fails', function () {
    app()['env'] = 'production';
    config(['app.debug' => true, 'hosting.enforce_entitlements' => false, 'queue.default' => 'sync', 'app.url' => 'http://example.test']);

    $rows = p8Rows();

    expect($rows['Debug mode is off in production']['level'])->toBe('fail');
    expect($rows['Plans are enforced (paying unlocks deployments)']['level'])->toBe('fail');
    expect($rows['Background jobs run on a queue']['level'])->toBe('fail');
    expect($rows['Site address uses HTTPS']['level'])->toBe('fail');
});

test('[P8-CHECK] it names staff who have no two-factor and notices missing setup', function () {
    $lazy = p8Staff('admin', false);

    $rows = p8Rows();

    expect($rows['Every staff member has two-factor on']['level'])->toBe('warn');
    expect($rows['Every staff member has two-factor on']['detail'])->toContain($lazy->email);
    expect($rows['At least one payment method is published']['level'])->toBe('warn');
    expect($rows['Customer backups have a working storage']['level'])->toBe('warn');

    PaymentMethod::create(['type' => 'bank_transfer', 'label' => 'Bank', 'currency' => 'ANY', 'is_active' => true]);
    expect(p8Rows()['At least one payment method is published']['level'])->toBe('ok');
});

test('[P8-CHECK] the command fails when something must be fixed and says so in plain words', function () {
    config(['hosting.enforce_entitlements' => false]);
    $this->artisan('stx:security-check')->expectsOutputToContain('to fix now')->assertExitCode(1);

    // Nothing to fix now (only advice left): passes normally, fails when asked to be strict.
    config([
        'hosting.enforce_entitlements' => true,
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'security.queues' => 'high,default',
    ]);
    $this->artisan('stx:security-check')->assertExitCode(0);
    $this->artisan('stx:security-check --strict')->assertExitCode(1);
});

test('[P8-CHECK] a worker that ignores the "high" queue is reported, because customer emails would never go out', function () {
    config(['security.queues' => 'default']);

    expect(p8Rows()['The worker listens to the "high" queue']['level'])->toBe('fail');
});

/* ---------- help center ---------- */

test('[P8-DOCS] the public help center lists the guides and opens each one', function () {
    $this->get('/docs')->assertOk()->assertSee('Getting started')->assertSee('Troubleshooting');
    $this->get('/docs/getting-started')->assertOk()->assertSee('Choose a plan');
    $this->get('/docs/does-not-exist')->assertNotFound();
});

test('[P8-DOCS] a slug cannot reach other files', function () {
    $this->get('/docs/..%2f..%2f.env')->assertNotFound();
    $this->get('/docs/%2e%2e%2fadmin%2fsetup-checklist')->assertNotFound();
    expect(app(DocsRepository::class)->find('client', '../admin/setup-checklist'))->toBeNull();
    expect(fn () => app(DocsRepository::class)->articles('..'))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('[P8-DOCS] the staff handbook is for signed-in staff with two-factor only', function () {
    $this->get('/admin/docs')->assertRedirect();
    $this->actingAs(p8Client())->get('/admin/docs')->assertForbidden();
    $this->actingAs(p8Staff('admin', false))->get('/admin/docs')->assertRedirect(route('profile'));

    $this->actingAs(p8Staff('admin'))->get('/admin/docs')->assertOk()->assertSee('First-time setup checklist');
    $this->actingAs(p8Staff('admin'))->get('/admin/docs/security-operations')->assertOk()->assertSee('Two-factor');
});

test('[P8-DOCS] public search needs every word, ranks titles first and finds nothing for nonsense', function () {
    $docs = app(DocsRepository::class);

    $domain = $docs->search('client', 'domain');
    expect($domain->first()['article']['slug'])->toBe('domains-and-https');

    $both = $docs->search('client', 'domain certificate');
    expect($both->pluck('article.slug')->all())->toContain('domains-and-https')->not->toContain('getting-started');

    expect($docs->search('client', 'zzqqxxnotaword'))->toBeEmpty();
    expect($docs->search('client', '  '))->toBeEmpty();

    $this->get('/docs?q=backup')->assertOk()->assertSee('Database backups');
    $this->get('/docs?q=zzqqxxnotaword')->assertOk()->assertSee('Nothing matched');
});

test('[P8-DOCS] a guide cannot inject scripts or script links, and files without a header are ignored', function () {
    $dir = sys_get_temp_dir().'/kb-'.uniqid();
    mkdir($dir.'/client', 0777, true);
    file_put_contents($dir.'/client/evil.md', "---\ntitle: Evil\nsummary: s\norder: 1\n---\n<script>alert(1)</script>\n\n[click](javascript:alert(1))\n\n<img src=x onerror=alert(1)>");
    file_put_contents($dir.'/client/no-header.md', "just text, no header");
    DocsRepository::$basePath = $dir;
    $docs = app(DocsRepository::class);

    expect($docs->articles('client')->pluck('slug')->all())->toBe(['evil']);

    $html = $docs->html($docs->find('client', 'evil'));
    expect(strtolower($html))->not->toContain('<script')->not->toContain('href="javascript')->not->toContain('onerror');
});

test('[P8-DOCS] every shipped article has a title, a summary and renders', function (string $audience) {
    $docs = app(DocsRepository::class);
    $all = $docs->articles($audience);

    expect($all->count())->toBeGreaterThanOrEqual(8);
    foreach ($all as $a) {
        expect($a['title'])->not->toBe('');
        expect($a['summary'])->not->toBe('');
        expect(strlen($docs->html($a)))->toBeGreaterThan(100);
    }
})->with(['client', 'admin']);

test('[P8-DOCS] the guides agree with the product on the delivery promise and say nothing false about payment', function () {
    $docs = app(DocsRepository::class);
    $text = fn (string $audience) => $docs->articles($audience)->map(fn ($a) => $a['body'])->implode("\n");

    expect($text('client'))->toContain('24 to 72 hours');
    expect($text('admin'))->toContain('72 hours');
    expect(\App\Services\Commerce\VpsFulfillmentService::SLA_HOURS)->toBe(72);

    foreach (['Visa', 'Mastercard', '99.9', '15 seconds', '8012 3456'] as $claim) {
        expect($text('client').$text('admin'))->not->toContain($claim);
    }
});

test('[P8-DOCS] the help center is linked from the storefront and the portal', function () {
    $this->get('/')->assertSee(route('docs.index'), false);
    $this->actingAs(p8Client())->get('/portal')->assertSee('Help Center');
});

/* ---------- API contract ---------- */

test('[P8-API] the OpenAPI file and the real routes agree', function () {
    $spec = Yaml::parseFile(base_path('docs/openapi-commerce.yaml'));
    expect($spec['openapi'])->toStartWith('3.');

    $normalize = fn (string $uri) => preg_replace('/\{[^}]+\}/', '{}', $uri);

    $real = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/'))
        ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS']))
            ->map(fn ($m) => $m.' '.$normalize('/'.substr($r->uri(), strlen('api/v1')))));

    $documented = collect($spec['paths'])->flatMap(fn ($ops, $path) => collect(array_keys($ops))
        ->map(fn ($m) => strtoupper($m).' '.$normalize($path)));

    // Everything documented exists.
    foreach ($documented as $line) {
        expect($real->contains($line))->toBeTrue("documented but not routed: {$line}");
    }

    // Everything customer-facing in the commerce API is documented.
    $commerce = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/') && str_contains($r->getActionName(), 'Api\\Commerce\\'))
        ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS']))
            ->map(fn ($m) => $m.' '.$normalize('/'.substr($r->uri(), strlen('api/v1')))));

    foreach ($commerce as $line) {
        expect($documented->contains($line))->toBeTrue("routed but not documented: {$line}");
    }
});

test('[P8-API] the status subscription endpoint no longer pretends to work', function () {
    $this->postJson('/api/v1/status/subscribe', ['type' => 'email', 'target' => 'a@example.com'])
        ->assertStatus(501)
        ->assertJson(['success' => false]);
});
