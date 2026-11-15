<?php

namespace Tests\Feature\Portal;

use App\Livewire\Portal\Apps\AppDetail;
use App\Livewire\Portal\Domains;
use App\Livewire\Portal\Mail\MailDashboard;
use App\Livewire\Portal\ServiceDetail;
use App\Livewire\SwitchTeam;
use App\Models\Commerce\MailDomain;
use App\Models\Commerce\MailSubscription;
use App\Models\Commerce\ServiceInstance;
use App\Models\CustomDomain;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Services\Commerce\CustomDomainService;
use App\Services\Commerce\WildDuckProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);

    $this->team = Team::factory()->create(['personal_team' => false]);

    $this->owner = User::factory()->create();
    $this->owner->teams()->attach($this->team->id, ['role' => 'owner']);

    $this->admin = User::factory()->create();
    $this->admin->teams()->attach($this->team->id, ['role' => 'admin']);

    $this->member = User::factory()->create();
    $this->member->teams()->attach($this->team->id, ['role' => 'member']);
});

test('non-admin member cannot remove custom domains in portal', function () {
    $domain = CustomDomain::create([
        'team_id' => $this->team->id,
        'domain' => 'client-site.com',
        'is_verified' => true,
    ]);

    $this->actingAs($this->member);
    session(['currentTeam' => $this->team]);

    expect(fn () => Livewire::test(Domains::class)->call('remove', $domain->uuid))
        ->toThrow(HttpException::class);

    expect(CustomDomain::where('id', $domain->id)->exists())->toBeTrue();
});

test('team owner or admin can remove custom domains in portal', function () {
    $domain = CustomDomain::create([
        'team_id' => $this->team->id,
        'domain' => 'owner-site.com',
        'is_verified' => true,
    ]);

    $this->actingAs($this->owner);
    session(['currentTeam' => $this->team]);

    Livewire::test(Domains::class)
        ->call('remove', $domain->uuid)
        ->assertDispatched('success');

    expect(CustomDomain::where('id', $domain->id)->exists())->toBeFalse();
});

test('non-admin member cannot mutate application in AppDetail component', function () {
    $service = ServiceInstance::create([
        'team_id' => $this->team->id,
        'name' => 'client-app',
        'hostname' => 'client-app.stxapp.io',
        'service_type' => 'app',
        'status' => 'active',
        'network_config' => ['custom_domains' => ['client-app.com']],
    ]);

    $this->actingAs($this->member);
    session(['currentTeam' => $this->team]);

    // Member can view app detail
    Livewire::test(AppDetail::class, ['uuid' => $service->uuid])
        ->assertOk();

    // Member cannot redeploy or restart
    expect(fn () => Livewire::test(AppDetail::class, ['uuid' => $service->uuid])->call('redeploy'))
        ->toThrow(HttpException::class);

    expect(fn () => Livewire::test(AppDetail::class, ['uuid' => $service->uuid])->call('restart'))
        ->toThrow(HttpException::class);

    expect(fn () => Livewire::test(AppDetail::class, ['uuid' => $service->uuid])->call('removeDomain', 'client-app.com'))
        ->toThrow(HttpException::class);
});

test('team admin can redeploy and restart application in AppDetail component', function () {
    $service = ServiceInstance::create([
        'team_id' => $this->team->id,
        'name' => 'admin-app',
        'hostname' => 'admin-app.stxapp.io',
        'service_type' => 'app',
        'status' => 'active',
        'network_config' => ['custom_domains' => []],
    ]);

    $this->actingAs($this->admin);
    session(['currentTeam' => $this->team]);

    Livewire::test(AppDetail::class, ['uuid' => $service->uuid])
        ->call('redeploy')
        ->assertDispatched('success');

    Livewire::test(AppDetail::class, ['uuid' => $service->uuid])
        ->call('restart')
        ->assertDispatched('success');
});

test('non-admin member cannot delete mail domains or mailboxes in MailDashboard', function () {
    $sub = MailSubscription::create([
        'team_id' => $this->team->id,
        'user_id' => $this->owner->id,
        'status' => 'active',
        'total_storage_mb' => 5000,
        'max_mailboxes' => 5,
        'max_domains' => 2,
    ]);

    $domain = MailDomain::create([
        'subscription_id' => $sub->id,
        'team_id' => $this->team->id,
        'domain' => 'securemail.org',
        'is_verified' => true,
    ]);

    $wildduckMock = Mockery::mock(WildDuckProvisioningService::class);
    $this->app->instance(WildDuckProvisioningService::class, $wildduckMock);

    $this->actingAs($this->member);
    session(['currentTeam' => $this->team]);

    expect(fn () => Livewire::test(MailDashboard::class)->call('deleteDomain', (string) $domain->id, $wildduckMock))
        ->toThrow(HttpException::class);

    expect(MailDomain::where('id', $domain->id)->exists())->toBeTrue();
});

test('team switcher redirects portal users back to portal without dashboard disruption', function () {
    $secondTeam = Team::factory()->create(['personal_team' => false]);
    $this->member->teams()->attach($secondTeam->id, ['role' => 'member']);

    $this->actingAs($this->member);
    session(['currentTeam' => $this->team]);

    Livewire::test(SwitchTeam::class)
        ->call('switch_to', (string) $secondTeam->id)
        ->assertRedirect(route('portal.index'));

    expect(auth()->user()->currentTeam()->id)->toBe($secondTeam->id);
});
