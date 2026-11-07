<?php

/*
|--------------------------------------------------------------------------
| Phase 3: storefront and cart
|--------------------------------------------------------------------------
*/

use App\Mail\StorefrontContactMail;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\PricingTier;
use App\Models\Commerce\ProductVariant;
use App\Models\InstanceSettings;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Commerce\OrderPlacedNotification;
use App\Services\Commerce\CartService;
use App\Services\Commerce\OrderService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    Server::forceCreate(['id' => 0, 'name' => 'localhost', 'ip' => '127.0.0.1', 'team_id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

function p3Variants(int $n = 2)
{
    return ProductVariant::with('pricingTiers')
        ->where('is_active', true)
        ->whereHas('pricingTiers', fn ($q) => $q->where('is_active', true)->whereNotNull('price_lkr_cents'))
        ->orderBy('id')->take($n)->get();
}

function p3Client(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    return [$user->fresh(), $team];
}

function p3Add($test, ProductVariant $v)
{
    $tier = $v->pricingTiers->where('is_active', true)->sortBy('price_cents')->first();

    return $test->post('/cart/add', ['variant' => $v->uuid, 'billing_type' => $tier->billing_type]);
}

/* ---------- pages ---------- */

test('[P3-PAGES] every public page loads for a visitor', function (string $path) {
    $this->get($path)->assertOk();
})->with([
    '/', '/store', '/pricing', '/vps', '/app-hosting', '/web-hosting', '/bot-hosting', '/databases', '/game-servers',
    '/v2ray', '/cart', '/about', '/faqs', '/contact', '/terms', '/privacy', '/refund', '/cookies',
]);

test('[P3-PAGES] the old reviews page is gone and redirects home', function () {
    $this->get('/reviews')->assertRedirect('/')->assertStatus(301);
});

test('[P3-PAGES] home lists the categories that have plans, with a price', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Cloud VPS')
        ->assertSee('App hosting')
        ->assertSee('from');
});

test('[P3-PAGES] the catalog shows specs and a price for every plan', function () {
    $variant = p3Variants(1)->first();
    $tier = $variant->pricingTiers->where('is_active', true)->sortBy('price_cents')->first();

    $this->get('/pricing')
        ->assertOk()
        ->assertSee($variant->name)
        ->assertSee('$'.number_format($tier->price_cents / 100, 2), false);
});

test('[P3-PAGES] unknown legal types and categories 404', function () {
    $this->get('/legal-nonsense')->assertNotFound();
});

/* ---------- honesty ---------- */

test('[P3-HONEST] the storefront no longer states things we cannot back up', function (string $path) {
    $html = $this->get($path)->getContent();

    foreach (['Kavindu Perera', '15 seconds', '8012 3456 7890', '99.9% Uptime', 'Falkenstein', 'AMD EPYC'] as $claim) {
        expect($html)->not->toContain($claim);
    }
})->with(['/', '/pricing', '/about', '/faqs', '/vps']);

test('[P3-HONEST] the FAQ says VPS delivery is manual and takes 24 to 72 hours', function () {
    $this->get('/faqs')->assertSee('24 to 72 hours');
});

test('[P3-HONEST] About only shows numbers the owner put in config', function () {
    $this->get('/about')->assertDontSee('In numbers');

    config(['storefront.claims' => ['Uptime target' => '99.5%']]);

    $this->get('/about')->assertSee('In numbers')->assertSee('99.5%');
});

/* ---------- accessibility and structure ---------- */

test('[P3-A11Y] pages have a language, a skip link, one main landmark and one h1', function (string $path) {
    $html = $this->get($path)->getContent();

    expect($html)->toContain('<html lang="en">');
    expect($html)->toContain('href="#main"');
    expect(substr_count($html, '<main id="main">'))->toBe(1);
    expect(substr_count($html, '<h1'))->toBe(1);
})->with(['/', '/pricing', '/vps', '/cart', '/faqs', '/contact', '/terms']);

test('[P3-A11Y] every add-to-cart control has an accessible name', function () {
    $html = $this->get('/vps')->getContent();

    expect($html)->toContain('to cart</span>');
    expect($html)->toContain('<caption class="sr-only">');
});

/* ---------- currency ---------- */

test('[P3-CURRENCY] the chosen currency sticks across pages', function () {
    $this->get('/pricing?currency=LKR')->assertSee('LKR');
    $this->get('/vps')->assertSee('LKR');
    $this->get('/pricing?currency=USD')->assertSee('$');
});

/* ---------- cart ---------- */

test('[P3-CART] adding a plan puts it in the cart at the catalog price, whatever the browser sends', function () {
    $variant = p3Variants(1)->first();
    $tier = $variant->pricingTiers->where('is_active', true)->sortBy('price_cents')->first();

    $this->post('/cart/add', [
        'variant' => $variant->uuid,
        'billing_type' => $tier->billing_type,
        'price' => 1,
        'unit_cents' => 1,
    ])->assertRedirect();

    $this->get('/cart')
        ->assertOk()
        ->assertSee($variant->name)
        ->assertSee('$'.number_format($tier->price_cents / 100, 2), false)
        ->assertDontSee('$0.01', false);
});

test('[P3-CART] the header shows how many items are in the cart', function () {
    $v = p3Variants(1)->first();
    p3Add($this, $v);
    p3Add($this, $v);

    $this->get('/')->assertSee('Cart, 2 items', false);
});

test('[P3-CART] quantity can be changed and a line removed', function () {
    $v = p3Variants(1)->first();
    p3Add($this, $v);
    $line = array_key_first(session(CartService::KEY));

    $this->patch("/cart/{$line}", ['quantity' => 3])->assertRedirect('/cart');
    expect(session(CartService::KEY)[$line]['quantity'])->toBe(3);

    $this->patch("/cart/{$line}", ['quantity' => 99])->assertSessionHasErrors('quantity');

    $this->delete("/cart/{$line}")->assertRedirect('/cart');
    expect(session(CartService::KEY))->toBe([]);
});

test('[P3-CART] unknown and inactive plans cannot be added', function () {
    $this->post('/cart/add', ['variant' => 'not-a-real-uuid'])->assertNotFound();

    $v = p3Variants(1)->first();
    $v->update(['is_active' => false]);
    $this->post('/cart/add', ['variant' => $v->uuid])->assertNotFound();
});

test('[P3-CART] a plan that stops being sold shows a problem and blocks checkout', function () {
    [$user, $team] = p3Client();
    $v = p3Variants(1)->first();
    p3Add($this, $v);
    $v->update(['is_active' => false]);

    $this->actingAs($user)->withSession(['currentTeam' => $team])
        ->get('/cart')->assertSee('no longer available');

    $this->actingAs($user)->withSession(['currentTeam' => $team])
        ->post('/cart/checkout')->assertRedirect('/cart');

    expect(Invoice::count())->toBe(0);
});

/* ---------- checkout ---------- */

test('[P3-CHECKOUT] a visitor is sent to sign in and the cart is kept', function () {
    $v = p3Variants(1)->first();
    p3Add($this, $v);

    $this->post('/cart/checkout')->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('cart.show'));
    expect(session(CartService::KEY))->not->toBeEmpty();
});

test('[P3-CHECKOUT] placing an order turns the whole cart into one invoice at catalog prices', function () {
    Notification::fake();
    [$user, $team] = p3Client();
    [$a, $b] = p3Variants(2)->all();
    p3Add($this, $a);
    p3Add($this, $b);

    $expected = collect([$a, $b])->sum(fn ($v) => $v->pricingTiers->where('is_active', true)->sortBy('price_cents')->first()->price_cents);

    $response = $this->actingAs($user)->withSession(['currentTeam' => $team])->post('/cart/checkout');

    $invoice = Invoice::where('user_id', $user->id)->firstOrFail();
    expect($invoice->total_cents)->toBe((int) $expected);
    expect($invoice->items)->toHaveCount(2);
    expect($invoice->status)->toBe('unpaid');
    $response->assertRedirect(route('portal.checkout', ['invoice' => $invoice->uuid]));
    expect(session(CartService::KEY))->toBeNull();
    Notification::assertSentTo($user, OrderPlacedNotification::class);
});

test('[P3-CHECKOUT] in LKR a plan without an LKR price blocks the order', function () {
    [$user, $team] = p3Client();
    $v = p3Variants(1)->first();
    $v->pricingTiers()->update(['price_lkr_cents' => null]);
    $this->get('/cart?currency=LKR');
    $this->post('/cart/add', ['variant' => $v->uuid]);

    $this->actingAs($user)->withSession(['currentTeam' => $team])
        ->post('/cart/checkout')->assertRedirect('/cart')->assertSessionHas('error');

    expect(Invoice::count())->toBe(0);
});

test('[P3-CHECKOUT] a setup fee is added to the order total (USD) and refused in LKR', function () {
    [$user, $team] = p3Client();
    $v = p3Variants(1)->first();
    $tier = $v->pricingTiers->where('is_active', true)->sortBy('price_cents')->first();
    $tier->update(['setup_fee_cents' => 500]);

    [, $invoice] = app(OrderService::class)->place($user, $team, [['variant' => $v->fresh('pricingTiers'), 'billing_type' => $tier->billing_type]], 'USD');
    expect($invoice->total_cents)->toBe((int) $tier->price_cents + 500);

    expect(fn () => app(OrderService::class)->place($user, $team, [['variant' => $v->fresh('pricingTiers'), 'billing_type' => $tier->billing_type]], 'LKR'))
        ->toThrow(InvalidArgumentException::class);
});

/* ---------- contact ---------- */

test('[P3-CONTACT] a message reaches the support address with the sender as reply-to', function () {
    Mail::fake();
    config(['storefront.support.email' => 'help@example.test']);

    $this->post('/contact', [
        'name' => 'Nimal', 'email' => 'nimal@example.com', 'department' => 'sales',
        'subject' => 'Plans', 'message' => 'Which plan fits a Discord bot?',
    ])->assertRedirect(route('store.contact'))->assertSessionHas('success');

    Mail::assertSent(StorefrontContactMail::class, fn ($m) => $m->hasTo('help@example.test') && $m->hasReplyTo('nimal@example.com'));
});

test('[P3-CONTACT] the hidden spam field blocks bots without sending anything', function () {
    Mail::fake();
    config(['storefront.support.email' => 'help@example.test']);

    $this->post('/contact', [
        'name' => 'Bot', 'email' => 'bot@example.com', 'department' => 'sales',
        'message' => 'Buy cheap watches now please', 'website' => 'http://spam.test',
    ])->assertRedirect();

    Mail::assertNothingSent();
});

test('[P3-CONTACT] invalid input is rejected with errors', function () {
    Mail::fake();

    $this->post('/contact', ['name' => 'A', 'email' => 'nope', 'department' => 'x', 'message' => 'short'])
        ->assertSessionHasErrors(['name', 'email', 'department', 'message']);

    Mail::assertNothingSent();
});
