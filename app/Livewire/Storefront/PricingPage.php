<?php

namespace App\Livewire\Storefront;

use App\Models\Commerce\Product;
use Livewire\Component;

class PricingPage extends Component
{
    public string $currency = 'USD';
    public string $billingPeriod = 'monthly';
    public string $selectedCategory = 'all';
    public string $searchQuery = '';
    public string $sortOption = 'default'; // default, price_asc, price_desc, name

    public function selectCurrency(string $curr): void
    {
        $this->currency = in_array($curr, ['USD', 'LKR']) ? $curr : 'USD';
    }

    public function selectBillingPeriod(string $period): void
    {
        $this->billingPeriod = $period;
    }

    public function setCategory(string $category): void
    {
        $this->selectedCategory = $category;
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
        $query = Product::with(['variants.pricingTiers' => function ($q) {
            $q->where('is_active', true);
        }])
            ->where('is_active', true);

        if ($this->selectedCategory !== 'all') {
            if ($this->selectedCategory === 'vps') {
                $query->where('category', 'vps');
            } elseif ($this->selectedCategory === 'web_hosting') {
                $query->whereIn('category', ['web_hosting', 'app_catalog']);
            } elseif ($this->selectedCategory === 'database') {
                $query->where('category', 'database');
            } elseif ($this->selectedCategory === 'bot_hosting') {
                $query->where('category', 'bot_hosting');
            } elseif ($this->selectedCategory === 'games') {
                $query->where('category', 'games');
            } else {
                $query->where('category', $this->selectedCategory);
            }
        }

        if (!empty($this->searchQuery)) {
            $term = '%' . strtolower(trim($this->searchQuery)) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
            });
        }

        $products = $query->get();

        // Client-side sorting for computed pricing
        if ($this->sortOption === 'price_asc') {
            $products = $products->sortBy(function ($p) {
                $tier = $p->variants->first()?->pricingTiers->first();
                return (float) ($tier?->price_monthly_usd ?: 9999);
            });
        } elseif ($this->sortOption === 'price_desc') {
            $products = $products->sortByDesc(function ($p) {
                $tier = $p->variants->first()?->pricingTiers->first();
                return (float) ($tier?->price_monthly_usd ?: 0);
            });
        } elseif ($this->sortOption === 'name') {
            $products = $products->sortBy('name');
        } else {
            $products = $products->sortByDesc('is_popular');
        }

        return view('livewire.storefront.pricing-page', [
            'products' => $products,
        ])->layout('layouts.storefront', [
            'title' => 'Transparent Cloud Pricing & Specifications — STX Enterprises',
        ]);
    }
}

