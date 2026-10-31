<?php

namespace App\Livewire\Storefront;

use App\Models\Commerce\Product;
use Livewire\Component;

class CategoryVps extends Component
{
    public string $currency = 'USD';
    public string $billingPeriod = 'monthly';
    public string $selectedLocation = 'fsn1'; // fsn1 (Germany), nbg1 (Germany), hel1 (Finland), sl (Sri Lanka)

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
            'location' => $this->selectedLocation,
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
            ->where('category', 'vps')
            ->orderBy('is_popular', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        return view('livewire.storefront.category-vps', [
            'products' => $products,
        ])->layout('layouts.storefront', [
            'title' => 'Cloud VPS Infrastructure | High-Performance NVMe Servers — STX Enterprises',
        ]);
    }
}

