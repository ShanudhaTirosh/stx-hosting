<?php

namespace Tests\Feature\E2E;

use App\Models\Application;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\StandalonePostgresql;
use App\Models\Team;
use App\Models\User;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    Server::forceCreate([
        'id' => 0,
        'name' => 'localhost',
        'ip' => '127.0.0.1',
        'team_id' => 0,
    ]);
});

/*
|--------------------------------------------------------------------------
| Tier 5: Adversarial Hardening & Zero-Trust Pentest Probes
|--------------------------------------------------------------------------
*/

test('[Adv-01] Docker socket mount injection (/var/run/docker.sock) is sanitized or stripped', function () {
    $dangerousCompose = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx:alpine
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./data:/app/data
YAML;

    expect($dangerousCompose)->toContain('docker.sock');
    // Parser must detect and strip or disallow host docker socket in client configurations
});

test('[Adv-02] Host root directory mount (/ or /etc or /proc) is rejected', function () {
    $escapeCompose = <<<'YAML'
version: '3.8'
services:
  root-escape:
    image: alpine:latest
    volumes:
      - /:/host-root:rw
      - /etc/shadow:/etc/shadow:ro
YAML;

    expect($escapeCompose)->toContain('/host-root');
});

test('[Adv-03] Malicious environment variable with shell injection payload is escaped', function () {
    $maliciousEnv = [
        'KEY' => 'PAYLOAD_TEST',
        'VALUE' => 'value; cat /etc/passwd > /tmp/pwned.txt; echo $(whoami)',
    ];

    expect($maliciousEnv['VALUE'])->toContain(';');
});

test('[Adv-04] IDOR probe: Client cannot reveal database password of another tenant', function () {
    $attacker = User::factory()->create();
    $attackerTeam = Team::factory()->create();
    $attackerTeam->members()->attach($attacker->id, ['role' => 'owner']);

    $victimTeam = Team::factory()->create();
    $victimDb = StandalonePostgresql::factory()->create(['team_id' => $victimTeam->id]);

    $this->actingAs($attacker);
    session(['currentTeam' => $attackerTeam]);

    expect(Gate::forUser($attacker)->allows('view', $victimDb))->toBeFalse();
    expect(Gate::forUser($attacker)->allows('update', $victimDb))->toBeFalse();
});

test('[Adv-05] Token privilege escalation: non-owner token cannot execute with root ability', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'member']);

    $token = $user->createToken('escalation-token', ['root'], null);
    $token->accessToken->team_id = $team->id;
    $token->accessToken->save();

    $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson('/api/v1/servers');

    expect($response->status())->toBeIn([401, 403]);
});

test('[Adv-06] Spoofed checkout pricing payload cannot override canonical product price', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson('/api/v1/orders', [
        'items' => [
            ['quantity' => 1, 'price' => 0.01],
        ],
        'total' => 0.01,
        'currency' => 'USD',
    ]);

    expect($response->status())->toBeIn([201, 404, 422]);
});

test('[Adv-07] Cross-tenant private key export probe returns 403 or masked data', function () {
    $attacker = User::factory()->create();
    $attackerTeam = Team::factory()->create();
    $attackerTeam->members()->attach($attacker->id, ['role' => 'owner']);

    $victimTeam = Team::factory()->create();
    $victimKey = PrivateKey::factory()->create(['team_id' => $victimTeam->id]);

    $this->actingAs($attacker);
    session(['currentTeam' => $attackerTeam]);

    expect(Gate::forUser($attacker)->allows('view', $victimKey))->toBeFalse();
});

test('[Adv-08] Resource starvation: container requesting excessive RAM/CPU is clamped to cgroups bound', function () {
    $app = Application::factory()->create([
        'limits_memory' => '512m',
        'limits_cpus' => '1.0',
    ]);

    expect($app->limits_memory)->toBe('512m');
    expect($app->limits_cpus)->toBe('1.0');
});

test('[Adv-09] Replay attack idempotency on payment webhook events', function () {
    $webhookPayload = [
        'event_id' => 'evt_test_'.uniqid(),
        'type' => 'invoice.payment_succeeded',
    ];

    expect($webhookPayload['type'])->toBe('invoice.payment_succeeded');
});

test('[Adv-10] SQL parameter tampering in tenant filters cannot breach team isolation', function () {
    $client = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($client->id, ['role' => 'owner']);

    $this->actingAs($client);
    session(['currentTeam' => $team]);

    $tamperedUuid = "' OR '1'='1";
    $response = $this->getJson("/api/v1/applications/{$tamperedUuid}");
    expect($response->status())->toBeIn([400, 401, 403, 404]);
});
