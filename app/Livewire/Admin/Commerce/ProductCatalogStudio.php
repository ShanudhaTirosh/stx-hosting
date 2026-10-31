<?php

namespace App\Livewire\Admin\Commerce;

use App\Models\Commerce\PricingTier;
use App\Models\Commerce\Product;
use App\Models\Commerce\ProductVariant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProductCatalogStudio extends Component
{
    public string $selectedCategory = 'all';

    public string $search = '';

    public bool $showProductModal = false;

    public bool $showVariantModal = false;

    public bool $showPricingModal = false;

    // Active product being edited or adding variants to
    public ?int $editingProductId = null;

    public ?int $editingVariantId = null;

    public ?int $editingTierId = null;

    // Product Form Fields
    public string $name = '';

    public string $slug = '';

    public string $category = 'vps';

    public string $description = '';

    public string $icon_url = '/images/logos/server.svg';

    public string $badge = '';

    public bool $is_popular = false;

    public bool $is_active = true;

    // Variant Form Fields
    public string $variant_name = '';

    public string $variant_slug = '';

    public int $vcpu = 2;

    public int $ram_mb = 4096;

    public int $disk_gb = 40;

    public string $disk_type = 'nvme';

    public int $bandwidth_gb = 20000;

    public array $specs = [];

    public string $specKey = '';

    public string $specValue = '';

    public bool $variant_active = true;

    // Pricing Tier Form Fields
    public string $billing_type = 'monthly';

    public float $price_usd = 7.50;

    public float $price_lkr = 2400.00;

    public float $setup_fee_usd = 0.00;

    public bool $tier_active = true;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:64',
            'description' => 'nullable|string',
        ];
    }

    public function openCreateProductModal(): void
    {
        $this->resetProductForm();
        $this->showProductModal = true;
    }

    public function editProduct(int $id): void
    {
        $product = Product::findOrFail($id);
        $this->editingProductId = $product->id;
        $this->name = $product->name;
        $this->slug = $product->slug;
        $this->category = $product->category;
        $this->description = $product->description ?? '';
        $this->icon_url = $product->icon_url ?? '/images/logos/server.svg';
        $this->badge = $product->badge ?? '';
        $this->is_popular = (bool) $product->is_popular;
        $this->is_active = (bool) $product->is_active;

        $this->showProductModal = true;
    }

    public function saveProduct(): void
    {
        $this->validate();

        $slug = $this->slug ? Str::slug($this->slug) : Str::slug($this->name);

        $data = [
            'name' => $this->name,
            'slug' => $slug,
            'category' => $this->category,
            'description' => $this->description,
            'icon_url' => $this->icon_url ?: '/images/logos/server.svg',
            'badge' => $this->badge ?: null,
            'is_popular' => $this->is_popular,
            'is_active' => $this->is_active,
        ];

        if ($this->editingProductId) {
            Product::where('id', $this->editingProductId)->update($data);
        } else {
            $data['uuid'] = (string) Str::uuid();
            Product::create($data);
        }

        $this->clearCommerceCache();
        $this->showProductModal = false;
        $this->resetProductForm();
        $this->dispatch('success', 'Product saved successfully.');
    }

    public function deleteProduct(int $id): void
    {
        Product::findOrFail($id)->delete();
        $this->clearCommerceCache();
        $this->dispatch('success', 'Product deleted successfully.');
    }

    public function openCreateVariantModal(int $productId): void
    {
        $this->editingProductId = $productId;
        $this->resetVariantForm();
        $this->showVariantModal = true;
    }

    public function editVariant(int $id): void
    {
        $variant = ProductVariant::findOrFail($id);
        $this->editingVariantId = $variant->id;
        $this->editingProductId = $variant->product_id;
        $this->variant_name = $variant->name;
        $this->variant_slug = $variant->slug;
        $this->vcpu = (int) $variant->vcpu;
        $this->ram_mb = (int) $variant->ram_mb;
        $this->disk_gb = (int) $variant->disk_gb;
        $this->disk_type = $variant->disk_type ?? 'nvme';
        $this->bandwidth_gb = (int) $variant->bandwidth_gb;
        $this->specs = (array) ($variant->specs_json ?? []);
        $this->variant_active = (bool) $variant->is_active;

        $this->showVariantModal = true;
    }

    public function addSpecItem(): void
    {
        if (trim($this->specKey) !== '' && trim($this->specValue) !== '') {
            $this->specs[trim($this->specKey)] = trim($this->specValue);
            $this->specKey = '';
            $this->specValue = '';
        }
    }

    public function removeSpecItem(string $key): void
    {
        unset($this->specs[$key]);
    }

    public function saveVariant(): void
    {
        $this->validate([
            'variant_name' => 'required|string|max:255',
            'vcpu' => 'required|integer|min:1',
            'ram_mb' => 'required|integer|min:128',
            'disk_gb' => 'required|integer|min:1',
        ]);

        $slug = $this->variant_slug ? Str::slug($this->variant_slug) : Str::slug($this->variant_name);

        $data = [
            'product_id' => $this->editingProductId,
            'name' => $this->variant_name,
            'slug' => $slug,
            'vcpu' => $this->vcpu,
            'ram_mb' => $this->ram_mb,
            'disk_gb' => $this->disk_gb,
            'disk_type' => $this->disk_type,
            'bandwidth_gb' => $this->bandwidth_gb,
            'specs_json' => ! empty($this->specs) ? $this->specs : null,
            'is_active' => $this->variant_active,
        ];

        if ($this->editingVariantId) {
            ProductVariant::where('id', $this->editingVariantId)->update($data);
        } else {
            $data['uuid'] = (string) Str::uuid();
            ProductVariant::create($data);
        }

        $this->clearCommerceCache();
        $this->showVariantModal = false;
        $this->resetVariantForm();
        $this->dispatch('success', 'Plan variant saved successfully.');
    }

    public function deleteVariant(int $id): void
    {
        ProductVariant::findOrFail($id)->delete();
        $this->clearCommerceCache();
        $this->dispatch('success', 'Plan variant deleted successfully.');
    }

    public function openCreatePricingModal(int $variantId): void
    {
        $this->editingVariantId = $variantId;
        $this->resetPricingForm();
        $this->showPricingModal = true;
    }

    public function editPricingTier(int $id): void
    {
        $tier = PricingTier::findOrFail($id);
        $this->editingTierId = $tier->id;
        $this->editingVariantId = $tier->product_variant_id;
        $this->billing_type = $tier->billing_type;
        $this->price_usd = $tier->price_cents / 100;
        $this->price_lkr = ($tier->price_lkr_cents ?? 0) / 100;
        $this->setup_fee_usd = ($tier->setup_fee_cents ?? 0) / 100;
        $this->tier_active = (bool) $tier->is_active;

        $this->showPricingModal = true;
    }

    public function savePricingTier(): void
    {
        $this->validate([
            'billing_type' => 'required|string|max:32',
            'price_usd' => 'required|numeric|min:0',
        ]);

        $data = [
            'product_variant_id' => $this->editingVariantId,
            'billing_type' => $this->billing_type,
            'price_cents' => (int) round($this->price_usd * 100),
            'price_lkr_cents' => (int) round($this->price_lkr * 100),
            'setup_fee_cents' => (int) round($this->setup_fee_usd * 100),
            'is_active' => $this->tier_active,
        ];

        if ($this->editingTierId) {
            PricingTier::where('id', $this->editingTierId)->update($data);
        } else {
            $data['uuid'] = (string) Str::uuid();
            PricingTier::create($data);
        }

        $this->clearCommerceCache();
        $this->showPricingModal = false;
        $this->resetPricingForm();
        $this->dispatch('success', 'Pricing tier saved successfully.');
    }

    public function deletePricingTier(int $id): void
    {
        PricingTier::findOrFail($id)->delete();
        $this->clearCommerceCache();
        $this->dispatch('success', 'Pricing tier deleted successfully.');
    }

    public function toggleProductActive(int $id): void
    {
        $p = Product::findOrFail($id);
        $p->is_active = ! $p->is_active;
        $p->save();
        $this->clearCommerceCache();
    }

    private function resetProductForm(): void
    {
        $this->editingProductId = null;
        $this->name = '';
        $this->slug = '';
        $this->category = 'vps';
        $this->description = '';
        $this->icon_url = '/images/logos/server.svg';
        $this->badge = '';
        $this->is_popular = false;
        $this->is_active = true;
    }

    private function resetVariantForm(): void
    {
        $this->editingVariantId = null;
        $this->variant_name = '';
        $this->variant_slug = '';
        $this->vcpu = 2;
        $this->ram_mb = 4096;
        $this->disk_gb = 40;
        $this->disk_type = 'nvme';
        $this->bandwidth_gb = 20000;
        $this->specs = [
            'cpu' => '2 vCPU x86',
            'ram' => '4 GB ECC RAM',
            'storage' => '40 GB Gen4 NVMe',
            'port' => '1 Gbps Dedicated',
            'location' => 'Multi-Region (US / EU / Asia)',
            'ipv4' => '1 Dedicated IPv4',
        ];
        $this->specKey = '';
        $this->specValue = '';
        $this->variant_active = true;
    }

    private function resetPricingForm(): void
    {
        $this->editingTierId = null;
        $this->billing_type = 'monthly';
        $this->price_usd = 7.50;
        $this->price_lkr = 2400.00;
        $this->setup_fee_usd = 0.00;
        $this->tier_active = true;
    }

    private function clearCommerceCache(): void
    {
        Cache::forget('storefront_catalog_payload');
        Cache::forget('api_status_public_payload');
        Cache::forget('public_status_page_data');
        // Clear all cached API products keys
        $currencies = ['USD', 'LKR'];
        $categories = ['', 'vps', 'bot_hosting', 'web_hosting', 'database', 'app_hosting', 'game_server'];
        foreach ($currencies as $c) {
            foreach ($categories as $cat) {
                Cache::forget("api_products_{$cat}_{$c}");
            }
        }
    }

    public function render()
    {
        $query = Product::with(['variants.pricingTiers'])->orderBy('id', 'desc');

        if ($this->selectedCategory !== 'all') {
            $query->where('category', $this->selectedCategory);
        }

        if (trim($this->search) !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%")
                    ->orWhere('slug', 'like', "%{$this->search}%");
            });
        }

        $products = $query->get();

        $categories = [
            'all' => 'All Categories',
            'vps' => 'Cloud VPS',
            'bot_hosting' => 'Bot Hosting',
            'web_hosting' => 'Web Hosting',
            'database' => 'Managed Databases',
            'app_hosting' => 'App Containers',
            'game_server' => 'Game Servers',
        ];

        return view('livewire.admin.commerce.product-catalog-studio', [
            'products' => $products,
            'categories' => $categories,
            'totalProducts' => Product::count(),
            'totalVariants' => ProductVariant::count(),
            'totalTiers' => PricingTier::count(),
        ])->layout('layouts.app', ['title' => 'Product & Pricing Studio | STX Admin']);
    }
}

