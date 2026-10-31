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

    public function orderPlan(?string $variantUuid = null, ?string $tierUuid = null): void
    {
        $params = [
            'currency' => $this->currency,
            'billing' => $this->billingPeriod,
        ];

        if ($variantUuid) {
            $params['plan'] = $variantUuid;
        }

        if ($tierUuid) {
            $params['tier'] = $tierUuid;
        }

        $this->redirect(route('portal.checkout', $params), navigate: true);
    }

    public function render()
    {
        $products = Cache::remember('storefront_catalog_payload', 30, function () {
            return Product::with(['variants.pricingTiers' => function ($q) {
                $q->where('is_active', true);
            }])
                ->where('is_active', true)
                ->orderBy('is_popular', 'desc')
                ->orderBy('id', 'asc')
                ->get();
        });

        if ($this->selectedCategory !== 'all') {
            $filteredProducts = $products->filter(function ($p) {
                if ($this->selectedCategory === 'vps') {
                    return $p->category === 'vps';
                }
                if ($this->selectedCategory === 'game-server') {
                    return $p->category === 'game_server';
                }
                if ($this->selectedCategory === 'web-app') {
                    return $p->category === 'web_hosting' || $p->category === 'app_hosting';
                }
                if ($this->selectedCategory === 'bot') {
                    return $p->category === 'bot_hosting';
                }
                if ($this->selectedCategory === 'database') {
                    return $p->category === 'database';
                }
                return $p->category === $this->selectedCategory;
            });
        } else {
            $filteredProducts = $products;
        }

        $monitorsSummary = Cache::remember('storefront_status_summary', 30, function () {
            return [
                'total' => Monitor::where('is_public', true)->count(),
                'active' => Monitor::where('is_public', true)->where('current_status', 'operational')->count(),
                'uptime' => round(Monitor::where('is_public', true)->avg('uptime_percentage') ?: 99.99, 2),
            ];
        });

        return view('livewire.storefront.index', [
            'products' => $filteredProducts,
            'monitorsSummary' => $monitorsSummary,
        ])->layout('layouts.storefront', [
            'title' => "STX Enterprises — Sri Lanka's #1 Cloud, VPS & Infrastructure Platform",
        ]);
    }
}
