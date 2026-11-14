<?php

namespace Tests\Feature\Portal;

use App\Livewire\Portal\Mail\MailDashboard;
use App\Models\Commerce\MailDomain;
use App\Models\Commerce\MailMailbox;
use App\Models\Commerce\MailPlan;
use App\Models\Commerce\MailSubscription;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Support\MailDnsGuide;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

test('mail dns guide returns provider configurations and custom records', function () {
    $providers = MailDnsGuide::getProviders();
    expect($providers)->toHaveKey(MailDnsGuide::PROVIDER_CLOUDFLARE);
    expect($providers)->toHaveKey(MailDnsGuide::PROVIDER_NAMECHEAP);
    expect($providers)->toHaveKey(MailDnsGuide::PROVIDER_GODADDY);
    expect($providers)->toHaveKey(MailDnsGuide::PROVIDER_ROUTE53);
    expect($providers)->toHaveKey(MailDnsGuide::PROVIDER_GENERIC);

    // Test Cloudflare records
    $cfRecords = MailDnsGuide::getRecordsForProvider(
        MailDnsGuide::PROVIDER_CLOUDFLARE,
        'acme.com',
        'stx',
        'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC...'
    );

    expect(count($cfRecords))->toBeGreaterThanOrEqual(4);
    $mx = collect($cfRecords)->firstWhere('type', 'MX');
    expect($mx)->not->toBeNull();
    expect($mx['proxy'])->toBe('DNS Only (Grey)');
    expect($mx['host'])->toBe('@');
    expect($mx['priority'])->toBe(10);

    // Test Route 53 records
    $r53Records = MailDnsGuide::getRecordsForProvider(
        MailDnsGuide::PROVIDER_ROUTE53,
        'acme.com',
        'stx',
        'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC...'
    );
    $spf = collect($r53Records)->firstWhere('purpose', 'SPF sender authentication (prevents spam spoofing)');
    expect($spf)->not->toBeNull();
    expect(str_starts_with($spf['value'], '"'))->toBeTrue();

    // Test BIND zone output
    $bindZone = MailDnsGuide::generateBindZone(
        'acme.com',
        'stx',
        'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC...'
    );
    expect($bindZone)->toContain('Business Email Records for acme.com');
    expect($bindZone)->toContain('IN  MX    10');
    expect($bindZone)->toContain('stx._domainkey');

    // Test Live Diagnostics in test environment
    $diag = MailDnsGuide::runLiveDiagnostics('acme.com', 'stx', 'test_key');
    expect($diag)->toHaveKey('mx');
    expect($diag)->toHaveKey('spf');
    expect($diag)->toHaveKey('dkim');
    expect($diag)->toHaveKey('dmarc');
    expect($diag['mx']['status'])->toBe('matched');
});

test('authenticated client can download apple mail mobileconfig profile', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $box = MailMailbox::create([
        'domain_id' => 'dom_apple_test',
        'team_id' => $team->id,
        'wildduck_user_id' => 'wd_usr_apple',
        'address' => 'ceo@applecorp.com',
        'name' => 'Steve Jobs',
        'is_active' => true,
    ]);

    // 1. Authenticated team owner can download
    $response = $this->actingAs($user)->get("/portal/mail/apple-profile/{$box->id}");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/x-apple-aspen-config; charset=utf-8');
    $content = $response->getContent();
    expect($content)->toContain('com.apple.mail.managed');
    expect($content)->toContain('ceo@applecorp.com');
    expect($content)->toContain('Steve Jobs');
    expect($content)->toContain('EmailAccountTypeIMAP');
    expect($content)->toContain('<integer>993</integer>');
    expect($content)->toContain('<integer>587</integer>');

    // 2. Unauthenticated user is redirected or denied
    auth()->logout();
    $unauthResponse = $this->get("/portal/mail/apple-profile/{$box->id}");
    $unauthResponse->assertRedirect('/login');

    // 3. User from another team cannot access profile
    $otherUser = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $otherUser->teams()->attach($otherTeam->id, ['role' => 'owner']);

    $forbiddenResponse = $this->actingAs($otherUser)->get("/portal/mail/apple-profile/{$box->id}");
    $forbiddenResponse->assertForbidden();
});

test('public rfc autoconfig endpoint returns valid xml for mozilla thunderbird', function () {
    $response = $this->get('/mail/config-v1.1.xml?emailaddress=support@clientbrand.com');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    $xml = $response->getContent();
    expect($xml)->toContain('<clientConfig version="1.1">');
    expect($xml)->toContain('<domain>clientbrand.com</domain>');
    expect($xml)->toContain('<incomingServer type="imap">');
    expect($xml)->toContain('<port>993</port>');
    expect($xml)->toContain('<outgoingServer type="smtp">');
    expect($xml)->toContain('<port>587</port>');
});

test('public rfc autodiscover endpoint returns valid xml for microsoft outlook', function () {
    $requestXml = <<<XML
<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/outlook/requestschema/2006">
    <Request>
        <EMailAddress>exec@mycompany.com</EMailAddress>
        <AcceptableResponseSchema>http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a</AcceptableResponseSchema>
    </Request>
</Autodiscover>
XML;

    $response = $this->call('POST', '/autodiscover/autodiscover.xml', [], [], [], [
        'CONTENT_TYPE' => 'text/xml',
    ], $requestXml);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/xml; charset=utf-8');
    $xml = $response->getContent();
    expect($xml)->toContain('<Autodiscover');
    expect($xml)->toContain('<Type>IMAP</Type>');
    expect($xml)->toContain('<Port>993</Port>');
    expect($xml)->toContain('<Type>SMTP</Type>');
    expect($xml)->toContain('<Port>587</Port>');
    expect($xml)->toContain('exec@mycompany.com');
});

test('mail dashboard component handles registrar switching, live diagnostics, and port testing', function () {
    $team = Team::factory()->create(['show_boarding' => false]);
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);
    refreshSession($team);

    $plan = MailPlan::where('slug', 'mail-basic')->firstOrFail();
    MailSubscription::create([
        'team_id' => $team->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'total_storage_mb' => 1024,
        'max_mailboxes' => 2,
        'max_domains' => 1,
        'renews_at' => now()->addMonth(),
    ]);

    $domain = MailDomain::create([
        'team_id' => $team->id,
        'domain' => 'techstartup.io',
        'dkim_selector' => 'stx',
        'dkim_public_key' => 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC...',
        'is_verified' => false,
    ]);

    $box = MailMailbox::create([
        'domain_id' => $domain->id,
        'team_id' => $team->id,
        'wildduck_user_id' => 'wd_usr_tech',
        'address' => 'founder@techstartup.io',
        'name' => 'Founder',
        'is_active' => true,
    ]);

    // Test DNS helper and registrar tab switching
    Livewire::actingAs($user)
        ->test(MailDashboard::class)
        ->call('openDnsHelper', $domain->id)
        ->assertSet('showDnsHelperModal', true)
        ->assertSet('selectedDnsProvider', 'cloudflare')
        ->call('setDnsProvider', 'namecheap')
        ->assertSet('selectedDnsProvider', 'namecheap')
        ->call('runLiveDnsDiagnostic', $domain->id)
        ->assertCount('liveDnsDiagnostics', 4);

    // Test client setup studio and connectivity probe
    Livewire::actingAs($user)
        ->test(MailDashboard::class)
        ->call('openCredentials', $box->address, $box->id)
        ->assertSet('showCredentialsModal', true)
        ->assertSet('selectedMailboxAddress', 'founder@techstartup.io')
        ->assertSet('selectedMailboxId', $box->id)
        ->call('setClientPlatform', 'outlook')
        ->assertSet('selectedClientPlatform', 'outlook')
        ->call('testMailServerConnectivity')
        ->assertDispatched('success')
        ->assertSee('Ports Verified Active');
});

