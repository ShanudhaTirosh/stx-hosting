<?php

namespace App\Livewire\Storefront;

use App\Models\Commerce\Product;
use Livewire\Component;

class CategoryWebHosting extends Component
{
    public string $currency = 'USD';
    public string $billingPeriod = 'monthly';

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
            ->where('category', 'web_hosting')
            ->orderBy('is_popular', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        return view('livewire.storefront.category-web-hosting', [
            'products' => $products,
        ])->layout('layouts.storefront', [
            'title' => 'Managed Web & WordPress Hosting | NVMe SSD & Free SSL — STX Enterprises',
        ]);
    }
}

