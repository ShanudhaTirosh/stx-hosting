<?php

namespace App\Livewire\Storefront;

use App\Models\Commerce\Product;
use Livewire\Component;

class CategoryGameServers extends Component
{
    public string $currency = 'USD';
    public string $billingPeriod = 'monthly';
    public string $selectedGame = 'all'; // all, minecraft, rust, palworld, cs2

    public function selectCurrency(string $curr): void
    {
        $this->currency = in_array($curr, ['USD', 'LKR']) ? $curr : 'USD';
    }

    public function selectBillingPeriod(string $period): void
    {
        $this->billingPeriod = $period;
    }

    public function selectGame(string $game): void
    {
        $this->selectedGame = $game;
    }

    public function orderPlan(?string $variantUuid = null, ?string $tierUuid = null): void
    {
        $params = [
            'currency' => $this->currency,
            'billing' => $this->billingPeriod,
            'game' => $this->selectedGame,
        ];
        if ($variantUuid) $params['plan'] = $variantUuid;
        if ($tierUuid) $params['tier'] = $tierUuid;

        $this->redirect(route('portal.checkout', $params), navigate: true);
    }

    public function render()
    {
        $products = Product::with(['variants.pricingTiers' => function ($q) {
            $q->where('is_active', true);
        }])
            ->where('is_active', true)
            ->where('category', 'game_server')
            ->orderBy('is_popular', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        return view('livewire.storefront.category-game-servers', [
            'products' => $products,
        ])->layout('layouts.storefront', [
            'title' => 'Game Server Hosting | Low-Latency High-Tickrate Nodes — STX Enterprises',
        ]);
    }
}

