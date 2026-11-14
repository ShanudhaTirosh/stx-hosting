<?php

namespace Tests\Feature\Commerce;

use App\Livewire\Admin\Platform\PlatformDomainStudio;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Services\Commerce\CloudflarePlatformDnsService;
use App\Services\Commerce\PlatformDomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    config()->set('hosting.apps_domain', 'app.stxhostings.com');
    config()->set('hosting.ingress_ip', '198.51.100.10');
});

test('cloudflare platform dns service validates tokens and detects matching zones', function () {
    CloudflarePlatformDnsService::$mockHandler = function (string $action, array $args) {
        if ($action === 'verify_token') {
            return $args['token'] === 'valid-token'
                ? ['valid' => true, 'status' => 'active', 'message' => 'Token is valid and active.']
                : ['valid' => false, 'status' => 'invalid', 'message' => 'Invalid token.'];
        }

        if ($action === 'list_zones') {
            return [
                ['id' => 'zone_123', 'name' => 'stxhostings.com', 'status' => 'active'],
                ['id' => 'zone_456', 'name' => 'otherdomain.org', 'status' => 'active'],
            ];
        }

        return null;
    };

    $service = new CloudflarePlatformDnsService();

    // Valid token
    $res = $service->verifyToken('valid-token');
    expect($res['valid'])->toBeTrue();

    // Invalid token
    $resBad = $service->verifyToken('bad-token');
    expect($resBad['valid'])->toBeFalse();

    // Zone detection
    $zone = $service->detectZone('valid-token', 'stxhostings.com');
    expect($zone)->not->toBeNull();
    expect($zone['id'])->toBe('zone_123');

    $zoneSub = $service->detectZone('valid-token', 'app.stxhostings.com');
    expect($zoneSub)->not->toBeNull();
    expect($zoneSub['id'])->toBe('zone_123');

    CloudflarePlatformDnsService::$mockHandler = null;
});

test('cloudflare platform dns service provisions wildcard, root, and platform service records', function () {
    $upserted = [];

    CloudflarePlatformDnsService::$mockHandler = function (string $action, array $args) use (&$upserted) {
        if ($action === 'upsert_record') {
            $upserted[$args['name']] = $args;
            return [
                'id' => 'rec_' . md5($args['name']),
                'name' => $args['name'],
                'type' => $args['type'],
                'content' => $args['content'],
                'proxied' => $args['proxied'],
                'action' => 'created',
            ];
        }

        return null;
    };

    $service = new CloudflarePlatformDnsService();
    $records = $service->provisionPlatformDns(
        'mock-token',
        'zone_123',
        '198.51.100.10',
        'stxhostings.com',
        'app.stxhostings.com'
    );

    expect($records)->toHaveKey('root');
    expect($records)->toHaveKey('wildcard_apps');
    expect($records)->toHaveKey('mail');
    expect($records)->toHaveKey('webmail');
    expect($records)->toHaveKey('status');
    expect($records)->toHaveKey('api');

    expect($upserted['*.app.stxhostings.com']['type'])->toBe('A');
    expect($upserted['*.app.stxhostings.com']['content'])->toBe('198.51.100.10');
    expect($upserted['*.app.stxhostings.com']['proxied'])->toBeFalse(); // DNS-only for direct wildcard SSL

    expect($upserted['mail.stxhostings.com']['proxied'])->toBeFalse(); // SMTP direct port

    // Verify settings updated in DB
    $settings = instanceSettings();
    expect($settings->base_platform_domain)->toBe('stxhostings.com');
    expect($settings->apps_domain)->toBe('app.stxhostings.com');
    expect($settings->cloudflare_zone_id)->toBe('zone_123');

    CloudflarePlatformDnsService::$mockHandler = null;
});

test('platform domain studio livewire component tests token and provisions dns', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);
    $this->actingAs($user);

    CloudflarePlatformDnsService::$mockHandler = function (string $action, array $args) {
        if ($action === 'verify_token') {
            return ['valid' => true, 'status' => 'active', 'message' => 'Token is valid and active.'];
        }
        if ($action === 'list_zones') {
            return [['id' => 'zone_test', 'name' => 'stxhostings.com', 'status' => 'active']];
        }
        if ($action === 'upsert_record') {
            return [
                'id' => 'rec_test',
                'name' => $args['name'],
                'type' => $args['type'],
                'content' => $args['content'],
                'proxied' => $args['proxied'],
                'action' => 'created',
            ];
        }
        return null;
    };

    Livewire::test(PlatformDomainStudio::class)
        ->set('baseDomain', 'stxhostings.com')
        ->set('appsDomain', 'app.stxhostings.com')
        ->set('ingressIp', '198.51.100.10')
        ->set('cloudflareToken', 'valid-cf-token')
        ->call('testToken')
        ->assertSet('tokenStatus', 'valid')
        ->assertSet('cloudflareZoneId', 'zone_test')
        ->call('provisionDns')
        ->assertDispatched('success');

    $settings = instanceSettings();
    expect($settings->base_platform_domain)->toBe('stxhostings.com');
    expect($settings->apps_domain)->toBe('app.stxhostings.com');

    CloudflarePlatformDnsService::$mockHandler = null;
});

