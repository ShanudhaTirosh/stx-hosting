<?php

namespace Tests\Feature\Commerce;

use App\Livewire\Admin\Commerce\MailPlanStudio;
use App\Livewire\Portal\Mail\MailDashboard;
use App\Models\Commerce\MailPlan;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

test('storefront email hosting page is accessible and displays storage plans', function () {
    $response = $this->get('/email-hosting');

    $response->assertOk()
        ->assertSee('Email hosting for your own domain')
        ->assertSee('Starter')
        ->assertSee('500 MB')
        ->assertSee('Basic')
        ->assertSee('1 GB')
        ->assertSee('Pro')
        ->assertSee('3 GB')
        ->assertSee('Business')
        ->assertSee('5 GB')
        ->assertSee('How onboarding works')
        ->assertSee('Client Connection Details');
});

test('storefront pricing page features email hosting product', function () {
    $response = $this->get('/pricing');

    $response->assertOk()
        ->assertSee('Email Hosting with Custom Domains')
        ->assertSee('View Email Hosting Plans');
});

test('guest cannot access customer mail portal without authentication', function () {
    $response = $this->get('/portal/mail');

    $response->assertRedirect('/login');
});

test('guest cannot access admin mail plan studio', function () {
    $response = $this->get('/admin/commerce/mail-plans');

    $response->assertRedirect('/login');
});

test('customer without staff role cannot access admin mail plan studio', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['show_boarding' => false]);
    $user->teams()->attach($team, ['role' => 'member']);
    refreshSession($team);

    $this->actingAs($user)
        ->get('/admin/commerce/mail-plans')
        ->assertForbidden();
});

test('platform staff can access admin mail plan studio Livewire component', function () {
    $user = User::factory()->create();
    $rootTeam = Team::find(0);
    if (! $rootTeam) {
        $rootTeam = Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'show_boarding' => false]);
    }
    $user->teams()->attach($rootTeam, ['role' => 'owner']);
    refreshSession($rootTeam);

    $this->actingAs($user)
        ->get('/admin/commerce/mail-plans')
        ->assertOk()
        ->assertSee('Email Hosting Plans & Fleet Studio')
        ->assertSee('WildDuck Core Status')
        ->assertSee('Provisioned Mailboxes');

    Livewire::actingAs($user)
        ->test(MailPlanStudio::class)
        ->assertOk()
        ->assertSet('activeTab', 'plans');
});

