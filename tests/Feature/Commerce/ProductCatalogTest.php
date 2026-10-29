<?php

namespace Tests\Feature\Commerce;

use App\Models\InstanceSettings;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
});

test('public catalog api returns all active products and pricing plans', function () {
    $response = $this->getJson('/api/products');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'products' => [
                '*' => [
                    'id',
                    'category',
                    'name',
                    'slug',
                    'description',
                    'iconUrl',
                    'isPopular',
                    'badge',
                    'isActive',
                    'plans' => [
                        '*' => [
                            'id',
                            'productId',
                            'name',
                            'price',
                            'currency',
                            'billingType',
                            'isPopular',
                            'badge',
                            'specsJson',
                            'isActive',
                        ],
                    ],
                ],
            ],
        ]);

    $products = $response->json('products');
    expect(count($products))->toBeGreaterThanOrEqual(3);

    // Verify Hetzner Cloud VPS product exists
    $vps = collect($products)->firstWhere('category', 'vps');
    expect($vps)->not->toBeNull();
    expect($vps['name'])->toContain('Hetzner');
    expect(count($vps['plans']))->toBeGreaterThanOrEqual(4);
});

test('public catalog api filters by category correctly', function () {
    $vpsResponse = $this->getJson('/api/products?category=vps');
    $vpsResponse->assertOk();
    $vpsProducts = $vpsResponse->json('products');

    foreach ($vpsProducts as $p) {
        expect($p['category'])->toBe('vps');
    }

    $dbResponse = $this->getJson('/api/products?category=database');
    $dbResponse->assertOk();
    $dbProducts = $dbResponse->json('products');

    foreach ($dbProducts as $p) {
        expect($p['category'])->toBe('database');
    }
});

test('public catalog api converts currency to LKR when requested', function () {
    $usdResponse = $this->getJson('/api/products?category=vps&currency=USD');
    $usdPlans = $usdResponse->json('products.0.plans');
    expect($usdPlans[0]['currency'])->toBe('USD');
    expect($usdPlans[0]['price'])->toBe(7.50);

    $lkrResponse = $this->getJson('/api/products?category=vps&currency=LKR');
    $lkrPlans = $lkrResponse->json('products.0.plans');
    expect($lkrPlans[0]['currency'])->toBe('LKR');
    expect($lkrPlans[0]['price'])->toBe(2400.00);
});
