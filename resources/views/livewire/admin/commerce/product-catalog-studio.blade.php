<div class="max-w-7xl mx-auto p-6 space-y-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-6 border-b border-black/[0.08] dark:border-white/[0.06]">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                    <span class="size-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                    Commerce Engine
                </span>
                <span class="text-xs text-neutral-500 font-mono">Catalog Studio</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-black dark:text-white mt-1">Products, Plans & Pricing Studio</h1>
            <p class="text-xs text-neutral-500 dark:text-neutral-400">Configure hosting products, hardware specifications, and multi-currency pricing plans for the platform.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('store.index') }}" target="_blank" class="button border border-neutral-300 dark:border-white/10 hover:bg-neutral-100 dark:hover:bg-white/5 text-xs py-2 px-3">
                <x-reicon name="external-link" class="size-3.5 mr-1" />
                Preview Storefront
            </a>
            <button wire:click="openCreateProductModal" type="button" class="button button-highlighted text-xs py-2 px-3.5">
                <x-reicon name="plus" class="size-3.5 mr-1" />
                Add New Product
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 rounded-xl border border-neutral-200 dark:border-white/[0.06] bg-white dark:bg-white/[0.02]">
            <span class="text-xs font-medium text-neutral-500 block">Total Active Products</span>
            <span class="text-2xl font-bold text-black dark:text-white mt-1 block font-mono">{{ $totalProducts }}</span>
        </div>
        <div class="p-4 rounded-xl border border-neutral-200 dark:border-white/[0.06] bg-white dark:bg-white/[0.02]">
            <span class="text-xs font-medium text-neutral-500 block">Configured Plan Variants</span>
            <span class="text-2xl font-bold text-black dark:text-white mt-1 block font-mono">{{ $totalVariants }}</span>
        </div>
        <div class="p-4 rounded-xl border border-neutral-200 dark:border-white/[0.06] bg-white dark:bg-white/[0.02]">
            <span class="text-xs font-medium text-neutral-500 block">Active Pricing Tiers (USD / LKR)</span>
            <span class="text-2xl font-bold text-emerald-500 mt-1 block font-mono">{{ $totalTiers }}</span>
        </div>
    </div>

    {{-- Filters & Search --}}
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-2 overflow-x-auto w-full sm:w-auto pb-2 sm:pb-0">
            @foreach ($categories as $catKey => $catLabel)
                <button wire:click="$set('selectedCategory', '{{ $catKey }}')" type="button"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition-colors {{ $selectedCategory === $catKey ? 'bg-black text-white dark:bg-white dark:text-black font-semibold shadow-sm' : 'bg-neutral-100 hover:bg-neutral-200 dark:bg-white/[0.05] dark:hover:bg-white/[0.08] text-neutral-600 dark:text-neutral-300' }}">
                    {{ $catLabel }}
                </button>
            @endforeach
        </div>

        <div class="w-full sm:w-72">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search products & specs…"
                class="w-full px-3 py-1.5 text-xs rounded-lg border border-neutral-300 dark:border-white/10 bg-white dark:bg-white/[0.03] text-black dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-amber-500" />
        </div>
    </div>

    {{-- Products & Plans List --}}
    <div class="space-y-6">
        @forelse ($products as $product)
            <div class="rounded-xl border border-neutral-200 dark:border-white/[0.07] bg-white dark:bg-white/[0.025] shadow-sm overflow-hidden" wire:key="product-{{ $product->id }}">
                {{-- Product Card Header --}}
                <div class="p-5 border-b border-neutral-100 dark:border-white/[0.05] flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-neutral-50/50 dark:bg-white/[0.015]">
                    <div class="flex items-start gap-3.5">
                        <div class="size-10 rounded-xl border border-neutral-200 dark:border-white/[0.08] bg-white dark:bg-white/[0.05] flex items-center justify-center shrink-0">
                            <img src="{{ $product->icon_url ?: '/images/logos/server.svg' }}" class="size-6 object-contain" alt="{{ $product->name }}"
                                onerror="this.src='/STKcdr.svg'" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-base font-bold text-black dark:text-white">{{ $product->name }}</h2>
                                <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase rounded bg-neutral-200 dark:bg-white/10 text-neutral-600 dark:text-neutral-300">
                                    {{ $product->category }}
                                </span>
                                @if ($product->badge)
                                    <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                        {{ $product->badge }}
                                    </span>
                                @endif
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $product->is_active ? 'bg-emerald-500/10 text-emerald-500' : 'bg-neutral-500/10 text-neutral-400' }}">
                                    {{ $product->is_active ? 'Active' : 'Draft' }}
                                </span>
                            </div>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 max-w-2xl">{{ $product->description }}</p>
                            <span class="text-[11px] font-mono text-neutral-400 mt-1 block">Slug: /store?cat={{ $product->category }} (UUID: {{ $product->uuid }})</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button wire:click="openCreateVariantModal({{ $product->id }})" type="button" class="button button-highlighted text-xs py-1.5 px-3">
                            <x-reicon name="plus" class="size-3 mr-1" />
                            Add Plan
                        </button>
                        <button wire:click="editProduct({{ $product->id }})" type="button" class="button border border-neutral-300 dark:border-white/10 hover:bg-neutral-100 dark:hover:bg-white/5 text-xs py-1.5 px-3">
                            <x-reicon name="edit" class="size-3 mr-1" />
                            Edit
                        </button>
                        <button wire:click="toggleProductActive({{ $product->id }})" type="button" class="button border border-neutral-300 dark:border-white/10 hover:bg-neutral-100 dark:hover:bg-white/5 text-xs py-1.5 px-2.5">
                            {{ $product->is_active ? 'Disable' : 'Enable' }}
                        </button>
                        <button wire:click="deleteProduct({{ $product->id }})" wire:confirm="Are you sure you want to delete this product and all its plan variants?" type="button" class="button button-danger text-xs py-1.5 px-2.5">
                            <x-reicon name="trash" class="size-3" />
                        </button>
                    </div>
                </div>

                {{-- Variants & Plans Grid --}}
                <div class="p-5">
                    @if ($product->variants->isEmpty())
                        <div class="text-center py-6 border border-dashed border-neutral-200 dark:border-white/10 rounded-xl">
                            <p class="text-xs text-neutral-500">No plan variants configured for this product yet.</p>
                            <button wire:click="openCreateVariantModal({{ $product->id }})" type="button" class="mt-2 text-xs text-amber-500 hover:underline font-semibold">
                                + Add First Plan Variant
                            </button>
                        </div>
                    @else
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            @foreach ($product->variants as $variant)
                                <div class="rounded-xl border border-neutral-200 dark:border-white/[0.06] bg-neutral-50/50 dark:bg-white/[0.015] p-4 flex flex-col justify-between space-y-3" wire:key="variant-{{ $variant->id }}">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <h3 class="text-sm font-bold text-black dark:text-white">{{ $variant->name }}</h3>
                                                <span class="text-[10px] font-mono text-neutral-400">({{ $variant->slug }})</span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <button wire:click="editVariant({{ $variant->id }})" class="text-neutral-400 hover:text-black dark:hover:text-white p-1">
                                                    <x-reicon name="edit" class="size-3.5" />
                                                </button>
                                                <button wire:click="deleteVariant({{ $variant->id }})" wire:confirm="Delete this plan variant?" class="text-neutral-400 hover:text-rose-500 p-1">
                                                    <x-reicon name="trash" class="size-3.5" />
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Hardware Specs Chips --}}
                                        <div class="grid grid-cols-4 gap-1.5 mt-2.5 text-center font-mono">
                                            <div class="bg-white dark:bg-white/[0.03] p-1.5 rounded border border-neutral-200/80 dark:border-white/5">
                                                <span class="text-[9px] text-neutral-400 block uppercase">vCPU</span>
                                                <span class="text-[11px] font-bold text-black dark:text-white">{{ $variant->vcpu }} Cores</span>
                                            </div>
                                            <div class="bg-white dark:bg-white/[0.03] p-1.5 rounded border border-neutral-200/80 dark:border-white/5">
                                                <span class="text-[9px] text-neutral-400 block uppercase">RAM</span>
                                                <span class="text-[11px] font-bold text-black dark:text-white">{{ $variant->ram_mb >= 1024 ? ($variant->ram_mb / 1024).' GB' : $variant->ram_mb.' MB' }}</span>
                                            </div>
                                            <div class="bg-white dark:bg-white/[0.03] p-1.5 rounded border border-neutral-200/80 dark:border-white/5">
                                                <span class="text-[9px] text-neutral-400 block uppercase">Storage</span>
                                                <span class="text-[11px] font-bold text-black dark:text-white">{{ $variant->disk_gb }} GB</span>
                                            </div>
                                            <div class="bg-white dark:bg-white/[0.03] p-1.5 rounded border border-neutral-200/80 dark:border-white/5">
                                                <span class="text-[9px] text-neutral-400 block uppercase">Bandwidth</span>
                                                <span class="text-[11px] font-bold text-black dark:text-white">{{ $variant->bandwidth_gb >= 1000 ? ($variant->bandwidth_gb / 1000).' TB' : $variant->bandwidth_gb.' GB' }}</span>
                                            </div>
                                        </div>

                                        {{-- Custom Specs JSON --}}
                                        @if (!empty($variant->specs_json))
                                            <div class="mt-2 text-[10.5px] text-neutral-500 dark:text-neutral-400 space-y-0.5">
                                                @foreach ($variant->specs_json as $k => $v)
                                                    <div class="flex items-center justify-between">
                                                        <span class="capitalize text-neutral-400">{{ $k }}:</span>
                                                        <span class="font-medium text-black dark:text-neutral-200">{{ $v }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Pricing Tiers --}}
                                    <div class="pt-3 border-t border-neutral-200/60 dark:border-white/5">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-[11px] font-medium text-neutral-500">Pricing Tiers:</span>
                                            <button wire:click="openCreatePricingModal({{ $variant->id }})" class="text-[11px] text-amber-500 hover:underline font-semibold">
                                                + Add Tier
                                            </button>
                                        </div>

                                        <div class="space-y-1.5">
                                            @forelse ($variant->pricingTiers as $tier)
                                                <div class="flex items-center justify-between px-2.5 py-1.5 rounded bg-white dark:bg-white/[0.03] border border-neutral-200/60 dark:border-white/5 text-xs font-mono" wire:key="tier-{{ $tier->id }}">
                                                    <div class="flex items-center gap-2">
                                                        <span class="uppercase text-[10px] font-bold text-neutral-400 px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-white/5">
                                                            {{ $tier->billing_type }}
                                                        </span>
                                                        <span class="font-bold text-emerald-500">${{ number_format($tier->price_cents / 100, 2) }}</span>
                                                        @if ($tier->price_lkr_cents)
                                                            <span class="text-neutral-400 text-[10.5px]">/ Rs {{ number_format($tier->price_lkr_cents / 100, 0) }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <button wire:click="editPricingTier({{ $tier->id }})" class="text-neutral-400 hover:text-black dark:hover:text-white">
                                                            <x-reicon name="edit" class="size-3" />
                                                        </button>
                                                        <button wire:click="deletePricingTier({{ $tier->id }})" wire:confirm="Delete this pricing tier?" class="text-neutral-400 hover:text-rose-500">
                                                            <x-reicon name="trash" class="size-3" />
                                                        </button>
                                                    </div>
                                                </div>
                                            @empty
                                                <span class="text-[11px] text-neutral-400 italic">No pricing tiers defined yet.</span>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-12 text-center border border-dashed border-neutral-200 dark:border-white/10 rounded-xl bg-white dark:bg-white/[0.02]">
                <p class="text-sm text-neutral-500">No products found matching your filter.</p>
                <button wire:click="openCreateProductModal" class="mt-3 button button-highlighted text-xs py-2 px-3.5">
                    Create New Product
                </button>
            </div>
        @endforelse
    </div>

    {{-- Product Modal --}}
    @if ($showProductModal)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-panel border border-neutral-200 dark:border-white/10 rounded-2xl w-full max-w-xl p-6 shadow-2xl space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-200 dark:border-white/10">
                    <h2 class="text-lg font-bold text-black dark:text-white">
                        {{ $editingProductId ? 'Edit Product' : 'Add New Product' }}
                    </h2>
                    <button wire:click="$set('showProductModal', false)" class="text-neutral-400 hover:text-black dark:hover:text-white">
                        <x-reicon name="x" class="size-5" />
                    </button>
                </div>

                <form wire:submit.prevent="saveProduct" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-neutral-600 dark:text-neutral-300 mb-1">Product Name *</label>
                            <input wire:model="name" type="text" placeholder="e.g. Dedicated Game Server" required
                                class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white" />
                        </div>
                        <div>
                            <label class="block font-medium text-neutral-600 dark:text-neutral-300 mb-1">Category *</label>
                            <select wire:model="category" required class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white">
                                <option value="vps">Cloud VPS</option>
                                <option value="bot_hosting">Bot Hosting</option>
                                <option value="web_hosting">Web Hosting</option>
                                <option value="database">Managed Database</option>
                                <option value="app_hosting">App Containers</option>
                                <option value="game_server">Game Server</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-neutral-600 dark:text-neutral-300 mb-1">URL Slug (leave blank to auto-generate)</label>
                        <input wire:model="slug" type="text" placeholder="e.g. game-servers"
                            class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white" />
                    </div>

                    <div>
                        <label class="block font-medium text-neutral-600 dark:text-neutral-300 mb-1">Description</label>
                        <textarea wire:model="description" rows="3" placeholder="Brief overview of features, target workloads, and guarantees…"
                            class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-neutral-600 dark:text-neutral-300 mb-1">Badge Text (Optional)</label>
                            <input wire:model="badge" type="text" placeholder="e.g. High Performance, 24/7"
                                class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white" />
                        </div>
                        <div>
                            <label class="block font-medium text-neutral-600 dark:text-neutral-300 mb-1">Icon URL</label>
                            <input wire:model="icon_url" type="text" placeholder="/images/logos/server.svg"
                                class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white" />
                        </div>
                    </div>

                    <div class="flex items-center gap-6 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input wire:model="is_popular" type="checkbox" class="rounded border-neutral-300 text-amber-500" />
                            <span class="text-neutral-600 dark:text-neutral-300 font-medium">Mark as Popular / Featured</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input wire:model="is_active" type="checkbox" class="rounded border-neutral-300 text-emerald-500" />
                            <span class="text-neutral-600 dark:text-neutral-300 font-medium">Active (Visible in Store)</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-neutral-200 dark:border-white/10">
                        <button wire:click="$set('showProductModal', false)" type="button" class="button border border-neutral-300 dark:border-white/10 text-xs py-2 px-4">
                            Cancel
                        </button>
                        <button type="submit" class="button button-highlighted text-xs py-2 px-5">
                            Save Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Variant Modal --}}
    @if ($showVariantModal)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-panel border border-neutral-200 dark:border-white/10 rounded-2xl w-full max-w-xl p-6 shadow-2xl space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-200 dark:border-white/10">
                    <h2 class="text-lg font-bold text-black dark:text-white">
                        {{ $editingVariantId ? 'Edit Plan Variant' : 'Add Plan Variant' }}
                    </h2>
                    <button wire:click="$set('showVariantModal', false)" class="text-neutral-400 hover:text-black dark:hover:text-white">
                        <x-reicon name="x" class="size-5" />
                    </button>
                </div>

                <form wire:submit.prevent="saveVariant" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-neutral-600 dark:text-neutral-300 mb-1">Plan Name *</label>
                            <input wire:model="variant_name" type="text" placeholder="e.g. Starter Cloud VPS" required
                                class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white" />
                        </div>
                        <div>
                            <label class="block font-medium text-neutral-600 dark:text-neutral-300 mb-1">Slug / Identifier</label>
                            <input wire:model="variant_slug" type="text" placeholder="e.g. cx22"
                                class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white" />
                        </div>
                    </div>

                    {{-- Hardware Specs --}}
                    <div class="p-3 bg-neutral-50 dark:bg-white/[0.02] border border-neutral-200 dark:border-white/5 rounded-xl space-y-3">
                        <span class="font-bold text-neutral-700 dark:text-neutral-200 block">Hardware Specifications</span>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                            <div>
                                <label class="block text-[11px] text-neutral-500 mb-0.5">vCPU Cores</label>
                                <input wire:model="vcpu" type="number" min="1" required class="w-full px-2.5 py-1.5 rounded border border-neutral-300 dark:border-white/10 bg-white dark:bg-white/[0.03] text-black dark:text-white font-mono" />
                            </div>
                            <div>
                                <label class="block text-[11px] text-neutral-500 mb-0.5">RAM (MB)</label>
                                <input wire:model="ram_mb" type="number" min="128" step="128" required class="w-full px-2.5 py-1.5 rounded border border-neutral-300 dark:border-white/10 bg-white dark:bg-white/[0.03] text-black dark:text-white font-mono" />
                            </div>
                            <div>
                                <label class="block text-[11px] text-neutral-500 mb-0.5">NVMe (GB)</label>
                                <input wire:model="disk_gb" type="number" min="1" required class="w-full px-2.5 py-1.5 rounded border border-neutral-300 dark:border-white/10 bg-white dark:bg-white/[0.03] text-black dark:text-white font-mono" />
                            </div>
                            <div>
                                <label class="block text-[11px] text-neutral-500 mb-0.5">Traffic (GB)</label>
                                <input wire:model="bandwidth_gb" type="number" min="10" step="100" required class="w-full px-2.5 py-1.5 rounded border border-neutral-300 dark:border-white/10 bg-white dark:bg-white/[0.03] text-black dark:text-white font-mono" />
                            </div>
                        </div>
                    </div>

                    {{-- Custom Specs Builder --}}
                    <div>
                        <label class="block font-medium text-neutral-600 dark:text-neutral-300 mb-1">Display Features (JSON Specs)</label>
                        <div class="flex items-center gap-2 mb-2">
                            <input wire:model="specKey" type="text" placeholder="Key (e.g. port)" class="w-1/3 px-2.5 py-1.5 rounded border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white" />
                            <input wire:model="specValue" type="text" placeholder="Value (e.g. 1 Gbps Dedicated)" class="flex-1 px-2.5 py-1.5 rounded border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white" />
                            <button wire:click.prevent="addSpecItem" type="button" class="button border border-neutral-300 dark:border-white/10 text-xs py-1.5 px-3">
                                Add
                            </button>
                        </div>

                        <div class="space-y-1">
                            @foreach ($specs as $k => $v)
                                <div class="flex items-center justify-between px-2.5 py-1 rounded bg-neutral-100 dark:bg-white/[0.03] text-[11px] font-mono">
                                    <span><strong class="text-neutral-600 dark:text-neutral-300">{{ $k }}:</strong> {{ $v }}</span>
                                    <button wire:click.prevent="removeSpecItem('{{ $k }}')" class="text-neutral-400 hover:text-rose-500">
                                        &times;
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input wire:model="variant_active" type="checkbox" class="rounded border-neutral-300 text-emerald-500" />
                            <span class="text-neutral-600 dark:text-neutral-300 font-medium">Variant Active</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-neutral-200 dark:border-white/10">
                        <button wire:click="$set('showVariantModal', false)" type="button" class="button border border-neutral-300 dark:border-white/10 text-xs py-2 px-4">
                            Cancel
                        </button>
                        <button type="submit" class="button button-highlighted text-xs py-2 px-5">
                            Save Plan Variant
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Pricing Tier Modal --}}
    @if ($showPricingModal)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-panel border border-neutral-200 dark:border-white/10 rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-200 dark:border-white/10">
                    <h2 class="text-lg font-bold text-black dark:text-white">
                        {{ $editingTierId ? 'Edit Pricing Tier' : 'Add Pricing Tier' }}
                    </h2>
                    <button wire:click="$set('showPricingModal', false)" class="text-neutral-400 hover:text-black dark:hover:text-white">
                        <x-reicon name="x" class="size-5" />
                    </button>
                </div>

                <form wire:submit.prevent="savePricingTier" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-medium text-neutral-600 dark:text-neutral-300 mb-1">Billing Period *</label>
                        <select wire:model="billing_type" required class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white font-mono">
                            <option value="monthly">Monthly</option>
                            <option value="30-day">30-Day Pass</option>
                            <option value="50-day">50-Day Pass</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="semi_annual">Semi-Annual</option>
                            <option value="annual">Annual</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-neutral-600 dark:text-neutral-300 mb-1">Price in USD ($) *</label>
                            <input wire:model="price_usd" type="number" step="0.01" min="0" required
                                class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white font-mono font-bold" />
                        </div>
                        <div>
                            <label class="block font-medium text-neutral-600 dark:text-neutral-300 mb-1">Price in LKR (Rs)</label>
                            <input wire:model="price_lkr" type="number" step="1" min="0"
                                class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white font-mono" />
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-neutral-600 dark:text-neutral-300 mb-1">Setup Fee ($)</label>
                        <input wire:model="setup_fee_usd" type="number" step="0.01" min="0"
                            class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-white/10 bg-neutral-50 dark:bg-white/[0.03] text-black dark:text-white font-mono" />
                    </div>

                    <div class="pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input wire:model="tier_active" type="checkbox" class="rounded border-neutral-300 text-emerald-500" />
                            <span class="text-neutral-600 dark:text-neutral-300 font-medium">Tier Active & Purchasable</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-neutral-200 dark:border-white/10">
                        <button wire:click="$set('showPricingModal', false)" type="button" class="button border border-neutral-300 dark:border-white/10 text-xs py-2 px-4">
                            Cancel
                        </button>
                        <button type="submit" class="button button-highlighted text-xs py-2 px-5">
                            Save Tier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

