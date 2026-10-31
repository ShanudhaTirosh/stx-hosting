<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-purple-600/15 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar current="pricing" :currency="$currency" />

    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24 relative z-10 w-full space-y-12">
        {{-- Hero Header --}}
        <div class="text-center space-y-4 max-w-3xl mx-auto pt-6">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-300 text-xs font-mono font-bold tracking-widest uppercase">
                <span class="size-1.5 rounded-full bg-purple-400 animate-pulse"></span>
                TRANSPARENT CLOUD PRICING
            </div>

            <h1 class="text-4xl md:text-6xl font-black tracking-tight text-white leading-tight">
                Products &amp; <span class="text-gradient">Specifications</span>
            </h1>

            <p class="text-base md:text-lg text-neutral-300 leading-relaxed font-sans">
                Explore hardware specifications, container runtimes, and pricing plans. Filter by product category or search for specific configurations.
            </p>

            {{-- Currency & Duration Controls --}}
            <div class="flex flex-wrap items-center justify-center gap-4 pt-4">
                {{-- Currency Switcher --}}
                <div class="flex items-center rounded-xl p-1 border border-white/10 bg-white/5 text-xs font-mono">
                    <button type="button" wire:click="selectCurrency('USD')"
                        @class(['px-3 py-1.5 rounded-lg font-bold transition-colors', 'bg-purple-600 text-white' => $currency === 'USD', 'text-neutral-400 hover:text-white' => $currency !== 'USD'])>
                        USD ($)
                    </button>
                    <button type="button" wire:click="selectCurrency('LKR')"
                        @class(['px-3 py-1.5 rounded-lg font-bold transition-colors', 'bg-purple-600 text-white' => $currency === 'LKR', 'text-neutral-400 hover:text-white' => $currency !== 'LKR'])>
                        LKR (Rs)
                    </button>
                </div>

                {{-- Billing Switcher --}}
                <div class="flex items-center rounded-xl p-1 border border-white/10 bg-white/5 text-xs font-mono">
                    <button type="button" wire:click="selectBillingPeriod('monthly')"
                        @class(['px-3 py-1.5 rounded-lg font-bold transition-colors', 'bg-purple-600 text-white' => $billingPeriod === 'monthly', 'text-neutral-400 hover:text-white' => $billingPeriod !== 'monthly'])>
                        Monthly
                    </button>
                    <button type="button" wire:click="selectBillingPeriod('yearly')"
                        @class(['px-3 py-1.5 rounded-lg font-bold transition-colors flex items-center gap-1', 'bg-purple-600 text-white' => $billingPeriod === 'yearly', 'text-neutral-400 hover:text-white' => $billingPeriod !== 'yearly'])>
                        Yearly <span class="text-[10px] text-emerald-400 font-extrabold">-20%</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Filter & Search Toolbar --}}
        <div class="space-y-4">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                {{-- Category Pill Tabs --}}
                <div class="flex items-center gap-2 overflow-x-auto w-full md:w-auto pb-2 md:pb-0 scrollbar-none text-xs font-mono">
                    @php
                        $cats = [
                            'all' => 'All Services',
                            'vps' => 'Cloud VPS',
                            'web_hosting' => 'Web & App',
                            'database' => 'Databases',
                            'bot_hosting' => 'Bots',
                            'games' => 'Game Servers',
                        ];
                    @endphp
                    @foreach ($cats as $key => $label)
                        <button type="button" wire:click="setCategory('{{ $key }}')"
                            @class([
                                'px-4 py-2 rounded-xl whitespace-nowrap transition-all border font-bold',
                                'bg-purple-600/20 border-purple-500/50 text-purple-300 shadow-[0_0_15px_rgba(168,85,247,0.2)]' => $selectedCategory === $key,
                                'bg-white/5 border-white/10 text-neutral-400 hover:text-white hover:border-white/20' => $selectedCategory !== $key,
                            ])>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- Search & Sort Box --}}
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <div class="relative flex-1 md:w-64">
                        <input type="text" wire:model.live.debounce.300ms="searchQuery" placeholder="Search plans..."
                            class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-3 py-2 text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-purple-500 transition-colors">
                        <svg class="size-4 text-neutral-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>

                    <select wire:model.live="sortOption"
                        class="bg-[#090b22] border border-white/10 rounded-xl px-3 py-2 text-xs text-neutral-300 focus:outline-none focus:border-purple-500">
                        <option value="default">Recommended</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="name">Name</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Products Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($products as $prod)
                @php
                    $primaryVariant = $prod->variants->first();
                    $tier = $primaryVariant?->pricingTiers->first();
                    $priceMonthlyUsd = max(2, (float) ($tier?->price_monthly_usd ?: 10));
                    $priceYearlyUsd = max(20, (float) ($tier?->price_yearly_usd ?: $priceMonthlyUsd * 10));
                    $priceMonthlyLkr = ($tier && (float)$tier->price_monthly_lkr > 0) ? (float)$tier->price_monthly_lkr : round($priceMonthlyUsd * 305, 0);
                    $priceYearlyLkr = ($tier && (float)$tier->price_yearly_lkr > 0) ? (float)$tier->price_yearly_lkr : round($priceYearlyUsd * 305, 0);

                    $displayPrice = ($currency === 'USD')
                        ? ($billingPeriod === 'monthly' ? '$' . number_format($priceMonthlyUsd, 2) : '$' . number_format($priceYearlyUsd / 12, 2))
                        : ($billingPeriod === 'monthly' ? 'Rs ' . number_format($priceMonthlyLkr, 0) : 'Rs ' . number_format($priceYearlyLkr / 12, 0));
                @endphp

                <div @class([
                    'glass-card rounded-2xl p-6 flex flex-col justify-between transition-all relative border border-white/10 hover:border-purple-500/40',
                    'border-purple-500/50 shadow-[0_0_40px_-10px_rgba(168,85,247,0.3)]' => $prod->is_popular,
                ])>
                    @if ($prod->is_popular)
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-gradient-to-r from-purple-600 to-cyan-500 text-white shadow-lg">
                            Popular Choice
                        </div>
                    @endif

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs uppercase font-mono tracking-wider text-purple-400">
                                {{ strtoupper(str_replace('_', ' ', $prod->category)) }}
                            </span>
                            <span class="text-[11px] font-mono text-neutral-400">Instant Deploy</span>
                        </div>

                        <h3 class="text-xl font-bold text-white mb-2">{{ $prod->name }}</h3>
                        <p class="text-xs text-neutral-400 mb-6 leading-relaxed line-clamp-2">
                            {{ $prod->description ?: 'High-performance cloud resource allocation with dedicated network connectivity and auto-failover.' }}
                        </p>

                        <div class="flex items-baseline gap-1 mb-6">
                            <span class="text-3xl font-black text-white">{{ $displayPrice }}</span>
                            <span class="text-xs font-mono text-neutral-400">/month</span>
                        </div>

                        {{-- Specs or feature list --}}
                        <ul class="space-y-2.5 pt-6 border-t border-white/[0.08] text-xs text-neutral-300">
                            @if ($prod->cpu_cores || $prod->ram_gb || $prod->disk_gb)
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                                    <span>{{ $prod->cpu_cores ?: 2 }} vCPU Cores &bull; {{ $prod->ram_gb ?: 4 }} GB RAM</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                                    <span>{{ $prod->disk_gb ?: 50 }} GB NVMe PCIe Gen4 Storage</span>
                                </li>
                            @else
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Automated Zero-Downtime Pipeline</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Dedicated Port & SSL Ingress Routing</span>
                                </li>
                            @endif
                            <li class="flex items-center gap-2">
                                <svg class="size-4 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>24/7 Monitoring &amp; 99.9% SLA Guarantee</span>
                            </li>
                        </ul>
                    </div>

                    <div class="pt-8">
                        <button type="button"
                            wire:click="orderPlan('{{ $primaryVariant?->uuid }}', '{{ $tier?->uuid }}')"
                            class="w-full py-3 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-xs shadow-glow-violet transition-all flex items-center justify-center gap-2">
                            Deploy {{ $prod->name }}
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full glass-card rounded-2xl p-12 text-center text-neutral-400">
                    No products matching your search criteria. Try adjusting filters.
                </div>
            @endforelse
        </div>
    </main>

    <x-storefront.footer />
</div>

