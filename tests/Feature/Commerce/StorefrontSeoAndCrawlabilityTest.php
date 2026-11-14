<?php

namespace Tests\Feature\Commerce;

use App\Models\InstanceSettings;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    Server::forceCreate(['id' => 0, 'name' => 'localhost', 'ip' => '127.0.0.1', 'team_id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

test('public robots.txt allows search engines and reputable AI crawlers while disallowing private routes', function () {
    $robots = @file_get_contents(public_path('robots.txt'));
    expect($robots)->not->toBeEmpty()
        ->toContain('User-agent: *')
        ->toContain('Allow: /')
        ->toContain('Disallow: /portal/')
        ->toContain('Disallow: /admin/')
        ->toContain('Disallow: /auth/')
        ->toContain('User-agent: GPTBot')
        ->toContain('User-agent: ClaudeBot')
        ->toContain('User-agent: PerplexityBot')
        ->toContain('Sitemap: https://shanutechx.com/sitemap.xml');
});

test('public sitemap.xml returns valid XML containing all core public routes', function () {
    $response = $this->get('/sitemap.xml');
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

    $content = $response->getContent();
    expect($content)->toContain('<urlset')
        ->toContain('<loc>' . route('home') . '</loc>')
        ->toContain('<loc>' . route('store.pricing') . '</loc>')
        ->toContain('<loc>' . route('store.features') . '</loc>')
        ->toContain('<loc>' . route('store.how-it-works') . '</loc>')
        ->toContain('<loc>' . route('store.templates') . '</loc>')
        ->toContain('<loc>' . route('store.vps') . '</loc>')
        ->toContain('<loc>' . route('store.email-hosting') . '</loc>')
        ->toContain('<loc>' . route('blog.index') . '</loc>')
        ->toContain('<loc>' . route('docs.index') . '</loc>')
        ->toContain('<loc>' . route('status.public') . '</loc>');
});

test('llms.txt provides plain text context with 24-72h SLA and LKR bank transfer payment', function () {
    $response = $this->get('/llms.txt');
    $response->assertStatus(200);

    $content = $response->getContent();
    expect($content)->toContain('STX Enterprises Cloud Platform')
        ->toContain('24 to 72 hours')
        ->toContain('Sri Lankan Rupees (LKR)')
        ->toContain('PayVerify AI');
});

test('features page renders server-side with single H1 and breadcrumbs', function () {
    $response = $this->get(route('store.features'));
    $response->assertStatus(200);

    $content = $response->getContent();
    expect($content)->toContain('<h1>Platform Features &amp; Infrastructure Architecture</h1>')
        ->toContain('High-Throughput NVMe Storage')
        ->toContain('Linux cgroups Quota Isolation')
        ->toContain('PayVerify AI Payment Verification')
        ->toContain('application/ld+json');
});

test('how it works page details 4-step bank transfer and 24-72h VPS delivery SLA', function () {
    $response = $this->get(route('store.how-it-works'));
    $response->assertStatus(200);

    $content = $response->getContent();
    expect($content)->toContain('<h1>How Ordering &amp; Service Activation Works</h1>')
        ->toContain('Select Your Plan &amp; Specs')
        ->toContain('Pay by Bank Transfer &amp; Upload Receipt')
        ->toContain('24 to 72 hours')
        ->toContain('application/ld+json');
});

test('curated templates catalog renders active compose templates directory', function () {
    $response = $this->get(route('store.templates'));
    $response->assertStatus(200);

    $content = $response->getContent();
    expect($content)->toContain('<h1>Curated 1-Click Application Templates</h1>')
        ->toContain('Next.js 15')
        ->toContain('Supabase BaaS')
        ->toContain('WordPress')
        ->toContain('Ghost Publishing');
});

test('engineering blog renders index and individual article with Article schema', function () {
    $indexResponse = $this->get(route('blog.index'));
    $indexResponse->assertStatus(200);
    $indexResponse->assertSee('Engineering Blog &amp; Cloud Guides', false);

    $articleResponse = $this->get(route('blog.show', ['slug' => 'how-to-buy-vps-in-sri-lanka-bank-transfer']));
    $articleResponse->assertStatus(200);

    $content = $articleResponse->getContent();
    expect($content)->toContain('How to Buy a High-Performance Cloud VPS in Sri Lanka with Bank Transfer')
        ->toContain('application/ld+json')
        ->toContain('"@type": "Article"')
        ->toContain('24 to 72 Hours');
});

test('catalog page enforces 24-72h delivery badge on VPS plans and dual currency', function () {
    $responseLkr = $this->get(route('store.vps', ['currency' => 'LKR']));
    $responseLkr->assertStatus(200);
    expect($responseLkr->getContent())->toContain('24–72h manual delivery');

    $responseUsd = $this->get(route('store.vps', ['currency' => 'USD']));
    $responseUsd->assertStatus(200);
    expect($responseUsd->getContent())->toContain('24–72h manual delivery');
});

test('web manifest is valid JSON and references platform theme color', function () {
    $manifestPath = public_path('manifest.json');
    expect(file_exists($manifestPath))->toBeTrue();

    $json = json_decode(file_get_contents($manifestPath), true);
    expect($json)->toBeArray()
        ->toHaveKey('name')
        ->toHaveKey('theme_color', '#0b2239')
        ->toHaveKey('start_url', '/');
});
