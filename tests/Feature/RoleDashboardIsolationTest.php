<?php

use App\Enums\Role;
use App\Livewire\Admin\Billing\VerificationSettings;
use App\Livewire\Admin\Cluster\ClusterNodeStudio;
use App\Livewire\Admin\Platform\PlatformDomainStudio;
use App\Livewire\Admin\Status\StatusStudio;
use App\Livewire\SettingsEmail;
use App\Models\Commerce\CommercialSetting;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::unguarded(fn () => InstanceSettings::updateOrCreate(['id' => 0], [
        'id' => 0,
        'is_registration_enabled' => true,
        'cloudflare_api_token' => 'cf-secret-token-12345',
        'smtp_password' => 'super-secret-smtp-password',
        'resend_api_key' => 're_test_key_12345678',
    ]));

    $this->rootTeam = Team::find(0) ?? Team::factory()->create(['id' => 0]);

    // Platform Owner user
    $this->owner = User::factory()->create([
        'two_factor_confirmed_at' => now(),
    ]);
    $this->rootTeam->members()->attach($this->owner->id, ['role' => Role::OWNER->value]);

    // Platform Admin user (Staff, but admin-only)
    $this->admin = User::factory()->create([
        'two_factor_confirmed_at' => now(),
    ]);
    $this->rootTeam->members()->attach($this->admin->id, ['role' => Role::ADMIN->value]);

    // Regular Client user
    $this->clientTeam = Team::factory()->create();
    $this->client = User::factory()->create();
    $this->clientTeam->members()->attach($this->client->id, ['role' => Role::OWNER->value]);

    Team::query()->update(['show_boarding' => false]);
    Cache::flush();
});

test('rbac service correctly classifies platform roles', function () {
    expect(RbacService::isPlatformOwner($this->owner))->toBeTrue();
    expect(RbacService::isPlatformAdminOnly($this->owner))->toBeFalse();
    expect(RbacService::canAccessAdminDashboard($this->owner))->toBeTrue();
    expect(RbacService::canAccessOwnerDashboard($this->owner))->toBeTrue();
    expect(RbacService::canAccessClientPortal($this->owner))->toBeTrue();

    expect(RbacService::isPlatformOwner($this->admin))->toBeFalse();
    expect(RbacService::isPlatformAdminOnly($this->admin))->toBeTrue();
    expect(RbacService::canAccessAdminDashboard($this->admin))->toBeTrue();
    expect(RbacService::canAccessOwnerDashboard($this->admin))->toBeFalse();
    expect(RbacService::canAccessClientPortal($this->admin))->toBeFalse();

    expect(RbacService::isPlatformOwner($this->client))->toBeFalse();
    expect(RbacService::isPlatformAdminOnly($this->client))->toBeFalse();
    expect(RbacService::canAccessAdminDashboard($this->client))->toBeFalse();
    expect(RbacService::canAccessOwnerDashboard($this->client))->toBeFalse();
    expect(RbacService::canAccessClientPortal($this->client))->toBeTrue();
});

test('admin can access admin dashboard routes', function () {
    $this->actingAs($this->admin);
    session(['currentTeam' => $this->rootTeam]);

    $this->get(route('admin.index'))->assertOk();
    $this->get(route('admin.billing.proofs'))->assertOk();
    $this->get(route('admin.operations.provisioning'))->assertOk();
    $this->get(route('admin.inventory.vps'))->assertOk();
    $this->get(route('admin.teams'))->assertOk();
    $this->get(route('admin.status.index'))->assertOk();
    $this->get(route('admin.audit'))->assertOk();
});

test('admin cannot access owner dashboard routes and is aborted with 403', function () {
    $this->actingAs($this->admin);
    session(['currentTeam' => $this->rootTeam]);

    $this->get(route('owner.index'))->assertForbidden();
    $this->get(route('owner.admins'))->assertForbidden();
    $this->get(route('owner.settings.index'))->assertForbidden();
    $this->get(route('owner.billing.verification-settings'))->assertForbidden();
    $this->get(route('owner.billing.methods'))->assertForbidden();
    $this->get(route('owner.cluster.nodes'))->assertForbidden();
    $this->get(route('owner.platform.domains'))->assertForbidden();
});

test('admin cannot access client portal routes and is aborted with 403', function () {
    $this->actingAs($this->admin);
    session(['currentTeam' => $this->rootTeam]);

    $this->get(route('portal.index'))->assertForbidden();
    $this->get(route('portal.services'))->assertForbidden();
    $this->get(route('portal.deploy'))->assertForbidden();
    $this->get(route('portal.billing'))->assertForbidden();
    $this->get('/dashboard')->assertForbidden();
});

test('client cannot access admin or owner routes and is aborted with 403', function () {
    $this->actingAs($this->client);
    session(['currentTeam' => $this->clientTeam]);

    $this->get(route('admin.index'))->assertForbidden();
    $this->get(route('admin.billing.proofs'))->assertForbidden();
    $this->get(route('owner.index'))->assertForbidden();
    $this->get(route('owner.admins'))->assertForbidden();
});

test('owner can access owner console, admin tools and client portal', function () {
    $this->actingAs($this->owner);
    session(['currentTeam' => $this->rootTeam]);

    $this->get(route('owner.index'))->assertOk();
    $this->get(route('owner.admins'))->assertOk();
    $this->get(route('owner.billing.proofs'))->assertOk();
    $this->get(route('owner.operations.provisioning'))->assertOk();
    $this->get(route('portal.index'))->assertOk();
    $this->get(route('portal.services'))->assertOk();
});

test('root team enforces at least one owner invariant', function () {
    expect(fn () => $this->rootTeam->assertMinimumOwnersRemaining([$this->owner->id]))
        ->toThrow(ValidationException::class);

    // If another owner exists, removing one is permitted
    $secondOwner = User::factory()->create();
    $this->rootTeam->members()->attach($secondOwner->id, ['role' => Role::OWNER->value]);

    expect(fn () => $this->rootTeam->assertMinimumOwnersRemaining([$this->owner->id]))
        ->not->toThrow(ValidationException::class);
});

test('secret masking in livewire components protects raw keys', function () {
    $this->actingAs($this->owner);
    session(['currentTeam' => $this->rootTeam]);

    // 1. VerificationSettings masks payverify & gemini keys
    CommercialSetting::set('payverify_api_key', 'live_pv_secret_1234');
    CommercialSetting::set('payverify_gemini_api_keys', 'AIzaSySecretGeminiKey1,AIzaSySecretGeminiKey2');

    Livewire::test(VerificationSettings::class)
        ->assertSet('payverifyApiKey', '••••••••')
        ->assertSet('geminiApiKeys', '••••••••');

    // 2. PlatformDomainStudio masks Cloudflare API token
    Livewire::test(PlatformDomainStudio::class)
        ->assertSet('cloudflareToken', '••••••••');

    // 3. SettingsEmail masks SMTP password and Resend API key
    Livewire::test(SettingsEmail::class)
        ->assertSet('smtpPassword', '••••••••')
        ->assertSet('resendApiKey', '••••••••');

    // 4. StatusStudio masks Telegram bot token and Discord webhook
    CommercialSetting::set('status_telegram_token', '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
    CommercialSetting::set('status_discord_webhook', 'https://discord.com/api/webhooks/123/xyz-secret');

    Livewire::test(StatusStudio::class)
        ->assertSet('telegramBotToken', '••••••••')
        ->assertSet('discordWebhookUrl', '••••••••');
});

test('cluster node and platform domain studios block direct admin calls', function () {
    $this->actingAs($this->admin);
    session(['currentTeam' => $this->rootTeam]);

    Livewire::test(ClusterNodeStudio::class)
        ->assertForbidden();

    Livewire::test(PlatformDomainStudio::class)
        ->assertForbidden();
});
