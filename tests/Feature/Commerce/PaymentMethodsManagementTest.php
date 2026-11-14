<?php

namespace Tests\Feature\Commerce;

use App\Livewire\Admin\Billing\PaymentMethods;
use App\Models\Commerce\PaymentMethod;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
});

test('staff user can create and manage payment methods with bank account details and product category scoping', function () {
    $staff = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($staff->id, ['role' => 'admin']);

    $this->actingAs($staff);

    Livewire::test(PaymentMethods::class)
        ->set('name', 'Commercial Bank of Ceylon (Direct Deposit)')
        ->set('type', 'bank_transfer')
        ->set('bank_name', 'Commercial Bank of Ceylon PLC')
        ->set('branch', 'Colombo 03 Corporate Branch')
        ->set('account_holder', 'STX Cloud Technologies (Pvt) Ltd')
        ->set('account_number', '1000248491')
        ->set('currency', 'LKR')
        ->set('instructions', 'Please transfer via CEFT or slip deposit and include your order reference in remark.')
        ->set('allowed_categories', ['vps', 'app_hosting'])
        ->set('min_amount_cents', 10000)
        ->set('max_amount_cents', 50000000)
        ->call('save')
        ->assertDispatched('success');

    $method = PaymentMethod::where('account_number', '1000248491')->firstOrFail();
    expect($method->bank_name)->toBe('Commercial Bank of Ceylon PLC');
    expect($method->account_holder)->toBe('STX Cloud Technologies (Pvt) Ltd');
    expect($method->currency)->toBe('LKR');
    expect($method->allowed_categories)->toContain('vps');
    expect($method->allowed_categories)->toContain('app_hosting');
    expect($method->matchesProductCategory('vps'))->toBeTrue();
    expect($method->matchesProductCategory('database'))->toBeFalse();
    expect($method->is_active)->toBeTrue();
});

test('staff user can toggle payment method active status and edit fields', function () {
    $staff = User::factory()->create();
    $rootTeam = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    $rootTeam->members()->attach($staff->id, ['role' => 'admin']);

    $method = PaymentMethod::create([
        'name' => 'Sampath Bank Vishwa',
        'type' => 'bank_transfer',
        'bank_name' => 'Sampath Bank PLC',
        'branch' => 'Kandy City Centre',
        'account_holder' => 'STX Hosting Private Limited',
        'account_number' => '009811223344',
        'currency' => 'LKR',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($staff);

    // Toggle active state
    Livewire::test(PaymentMethods::class)
        ->call('toggleActive', $method->id);

    $method->refresh();
    expect($method->is_active)->toBeFalse();

    // Edit method
    Livewire::test(PaymentMethods::class)
        ->call('editMethod', $method->id)
        ->set('branch', 'Colombo Fort Super Branch')
        ->set('is_active', true)
        ->call('save')
        ->assertDispatched('success');

    $method->refresh();
    expect($method->branch)->toBe('Colombo Fort Super Branch');
    expect($method->is_active)->toBeTrue();
});

test('scopeForCheckout filters payment methods by active status, currency, amount and category', function () {
    $vpsMethod = PaymentMethod::create([
        'name' => 'HNB VPS Only',
        'type' => 'bank_transfer',
        'account_holder' => 'STX',
        'account_number' => '11112222',
        'currency' => 'USD',
        'is_active' => true,
        'allowed_categories' => ['vps'],
        'min_amount_cents' => 1000,
        'max_amount_cents' => 50000,
    ]);

    $mailMethod = PaymentMethod::create([
        'name' => 'Bank of Ceylon Mail Only',
        'type' => 'bank_transfer',
        'account_holder' => 'STX',
        'account_number' => '33334444',
        'currency' => 'USD',
        'is_active' => true,
        'allowed_categories' => ['mail_hosting'],
    ]);

    $disabledMethod = PaymentMethod::create([
        'name' => 'Disabled Gateway',
        'type' => 'bank_transfer',
        'account_holder' => 'STX',
        'account_number' => '55556666',
        'currency' => 'USD',
        'is_active' => false,
    ]);

    // Query for VPS category with $25.00 (2500 cents)
    $vpsResults = PaymentMethod::forCheckout('USD', 2500, 'vps')->get();
    expect($vpsResults->pluck('id'))->toContain($vpsMethod->id);
    expect($vpsResults->pluck('id'))->not->toContain($mailMethod->id);
    expect($vpsResults->pluck('id'))->not->toContain($disabledMethod->id);

    // Query for amount exceeding max ($600.00 / 60000 cents)
    $largeAmountResults = PaymentMethod::forCheckout('USD', 60000, 'vps')->get();
    expect($largeAmountResults->pluck('id'))->not->toContain($vpsMethod->id);
});

test('non-staff customer cannot access or mutate payment methods admin component', function () {
    $customer = User::factory()->create();
    $customerTeam = Team::factory()->create();
    $customerTeam->members()->attach($customer->id, ['role' => 'admin']); // Admin of customer team, NOT root team

    $this->actingAs($customer);

    Livewire::test(PaymentMethods::class)
        ->assertForbidden();
});

