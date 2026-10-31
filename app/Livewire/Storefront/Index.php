<?php

namespace App\Livewire\Storefront;

use App\Models\Commerce\Monitor;
use App\Models\Commerce\Product;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class Index extends Component
{
    public string $selectedCategory = 'all';

    public string $currency = 'USD';

    public string $billingPeriod = 'monthly';

    public function selectCategory(string $cat): void
    {
        $this->selectedCategory = $cat;
    }

    public function selectCurrency(string $curr): void
    {
        $this->currency = in_array($curr, ['USD', 'LKR']) ? $curr : 'USD';
    }

    public function selectBillingPeriod(string $period): void
    {
        $this->billingPeriod = $period;
    }

    public function orderPlan(string $variantUuid, ?string $tierUuid = null): void
    {
        $params = [
            'plan' => $variantUuid,
            'currency' => $this->currency,
            'billing' => $this->billingPeriod,
        ];

        if ($tierUuid) {
            $params['tier'] = $tierUuid;
        }

        $this->redirect(route('portal.checkout', $params), navigate: true);
    }

    public function render()
    {
        $products = Cache::remember('storefront_catalog_payload', 60, function () {
            return Product::with(['variants.pricingTiers' => function ($q) {
                $q->where('is_active', true);
            }])
                ->where('is_active', true)
                ->orderBy('is_popular', 'desc')
                ->orderBy('id', 'asc')
                ->get();
        });

        if ($this->selectedCategory !== 'all') {
            $filteredProducts = $products->where('category', $this->selectedCategory);
        } else {
            $filteredProducts = $products;
        }

        $categories = [
            'all' => 'All Categories',
            'vps' => 'Cloud VPS',
            'bot_hosting' => 'Bot Hosting',
            'web_hosting' => 'Web Hosting',
            'database' => 'Managed Databases',
            'app_hosting' => 'App Containers',
            'game_server' => 'Game Servers',
        ];

        $monitors = Cache::remember('storefront_status_summary', 60, function () {
            return [
                'total' => Monitor::where('is_public', true)->count(),
                'active' => Monitor::where('is_public', true)->where('current_status', 'operational')->count(),
                'uptime' => round(Monitor::where('is_public', true)->avg('uptime_percentage') ?: 99.98, 2),
            ];
        });

        return view('livewire.storefront.index', [
            'products' => $filteredProducts,
            'categories' => $categories,
            'statusSummary' => $monitors,
        ])->layout('layouts.simple', ['title' => 'STX Cloud Hosting & Infrastructure Storefront']);
    }
}
