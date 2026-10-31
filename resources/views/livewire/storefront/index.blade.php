<div class="min-h-screen bg-[#0d0d0f] text-neutral-100 flex flex-col justify-between selection:bg-purple-600 selection:text-white">
    {{-- Top Storefront Navigation --}}
    <header class="border-b border-white/[0.08] bg-[#0d0d0f]/90 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="{{ route('store.index') }}" class="flex items-center gap-2.5 group">
                    <img src="/STKcdr.svg" class="size-7 transition-transform group-hover:scale-105" alt="STX Enterprises" />
                    <span class="text-base font-extrabold tracking-tight text-white flex items-center gap-1 font-mono">
                        <span class="text-cyan-400">STX</span> HOSTING
                    </span>
                </a>

                <nav class="hidden md:flex items-center gap-5 text-xs font-medium text-neutral-400">
                    <button wire:click="selectCategory('all')" class="hover:text-white transition-colors {{ $selectedCategory === 'all' ? 'text-white font-bold' : '' }}">Store Catalog</button>
                    <button wire:click="selectCategory('vps')" class="hover:text-white transition-colors {{ $selectedCategory === 'vps' ? 'text-white font-bold' : '' }}">Cloud VPS</button>
                    <button wire:click="selectCategory('bot_hosting')" class="hover:text-white transition-colors {{ $selectedCategory === 'bot_hosting' ? 'text-white font-bold' : '' }}">Bot Hosting</button>
                    <button wire:click="selectCategory('web_hosting')" class="hover:text-white transition-colors {{ $selectedCategory === 'web_hosting' ? 'text-white font-bold' : '' }}">Web Hosting</button>
                    <button wire:click="selectCategory('database')" class="hover:text-white transition-colors {{ $selectedCategory === 'database' ? 'text-white font-bold' : '' }}">Databases</button>
                    <a href="{{ route('status.public') }}" class="hover:text-emerald-400 transition-colors flex items-center gap-1.5">
                        <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Status
                    </a>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                {{-- Currency Switcher --}}
                <div class="flex items-center p-0.5 rounded-lg border border-white/10 bg-white/[0.03] text-xs font-mono font-bold">
                    <button wire:click="selectCurrency('USD')" class="px-2.5 py-1 rounded {{ $currency === 'USD' ? 'bg-cyan-500 text-black font-extrabold shadow' : 'text-neutral-400 hover:text-white' }}">USD ($)</button>
                    <button wire:click="selectCurrency('LKR')" class="px-2.5 py-1 rounded {{ $currency === 'LKR' ? 'bg-cyan-500 text-black font-extrabold shadow' : 'text-neutral-400 hover:text-white' }}">LKR (Rs)</button>
                </div>

                @auth
                    <a href="{{ route('portal.index') }}" class="button text-xs py-1.5 px-3 border border-white/10 hover:bg-white/5 font-semibold">
                        Customer Portal &rarr;
                    </a>
                @else
                    <a href="{{ route('login') }}" class="button text-xs py-1.5 px-3 border border-white/10 hover:bg-white/5 font-semibold">
                        Sign In
                    </a>
                    <a href="{{ route('register') }}" class="button button-highlighted text-xs py-1.5 px-3 font-semibold">
                        Register
                    </a>
                @endauth
            </div>
        </div>
    </header>

    {{-- Hero Section --}}
    <section class="py-16 md:py-20 relative overflow-hidden border-b border-white/[0.06]">
        <div class="absolute inset-0 bg-radial from-purple-900/15 via-transparent to-transparent pointer-events-none"></div>
        <div class="max-w-5xl mx-auto px-4 text-center space-y-6 relative z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                <span class="size-2 rounded-full bg-cyan-400 animate-pulse"></span>
                Dedicated Gen4 NVMe &bull; Zero Overselling &bull; Instant Provisioning
            </div>

            <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight text-white leading-tight">
                Enterprise Cloud Infrastructure &bull; <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-purple-400">Uncompromising Speed</span>
            </h1>

            <p class="text-sm md:text-base text-neutral-400 max-w-2xl mx-auto">
                Deploy high-performance Cloud VPS, WhatsApp/Discord bot daemons, WordPress web suites, and managed PostgreSQL databases on enterprise hardware.
            </p>

            {{-- Category Filter Bar --}}
            <div class="pt-4 flex flex-wrap items-center justify-center gap-2">
                @foreach ($categories as $catKey => $catLabel)
                    <button wire:click="selectCategory('{{ $catKey }}')" type="button"
                        class="px-4 py-2 rounded-xl text-xs font-semibold transition-all {{ $selectedCategory === $catKey ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-white/[0.03] border border-white/10 hover:bg-white/[0.07] text-neutral-300' }}">
                        {{ $catLabel }}
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Products & Plans Catalog --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 flex-1 space-y-16">
        @forelse ($products as $product)
            <div class="space-y-6" wire:key="store-product-{{ $product->id }}">
                <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-2 pb-3 border-b border-white/[0.06]">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-2xl font-bold text-white tracking-tight">{{ $product->name }}</h2>
                            @if ($product->badge)
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase bg-amber-500/10 text-amber-400 border border-amber-500/20 font-mono">
                                    {{ $product->badge }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-neutral-400 mt-1">{{ $product->description }}</p>
                    </div>

                    <span class="text-xs font-mono text-cyan-400 uppercase font-semibold">Category: {{ $product->category }}</span>
                </div>

                {{-- Variants Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach ($product->variants as $variant)
                        @php
                            // Find primary tier for selected billing
                            $tier = $variant->pricingTiers->firstWhere('billing_type', $billingPeriod) ?? $variant->pricingTiers->first();
                            $price = $currency === 'LKR' && $tier?->price_lkr_cents
                                ? 'Rs ' . number_format($tier->price_lkr_cents / 100, 0)
                                : '$' . number_format(($tier?->price_cents ?? 0) / 100, 2);
                        @endphp

                        <div class="rounded-2xl border border-white/10 bg-white/[0.025] hover:border-cyan-500/50 hover:bg-white/[0.04] p-6 flex flex-col justify-between transition-all group shadow-xl relative"
                            wire:key="store-variant-{{ $variant->id }}">
                            @if ($variant->is_popular)
                                <div class="absolute -top-3 right-4 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-cyan-400 text-black shadow">
                                    Most Popular
                                </div>
                            @endif

                            <div class="space-y-4">
                                <div>
                                    <h3 class="text-lg font-bold text-white group-hover:text-cyan-400 transition-colors">{{ $variant->name }}</h3>
                                    <span class="text-[11px] font-mono text-neutral-400">{{ $variant->slug }}</span>
                                </div>

                                {{-- Price Tag --}}
                                <div class="p-3.5 rounded-xl bg-black/40 border border-white/5 flex items-baseline justify-between font-mono">
                                    <div>
                                        <span class="text-2xl font-black text-white">{{ $price }}</span>
                                        <span class="text-xs text-neutral-400">/ {{ $tier?->billing_type ?? 'mo' }}</span>
                                    </div>
                                    @if ($tier && $tier->setup_fee_cents > 0)
                                        <span class="text-[10px] text-neutral-400">+ ${{ $tier->setup_fee_cents / 100 }} setup</span>
                                    @else
                                        <span class="text-[10px] text-emerald-400 font-semibold">Free Setup</span>
                                    @endif
                                </div>

                                {{-- Hardware Specs Matrix --}}
                                <div class="space-y-2 font-mono text-xs pt-1">
                                    <div class="flex items-center justify-between py-1 border-b border-white/[0.04]">
                                        <span class="text-neutral-400">Compute:</span>
                                        <span class="font-bold text-white">{{ $variant->vcpu }} vCPU Cores</span>
                                    </div>
                                    <div class="flex items-center justify-between py-1 border-b border-white/[0.04]">
                                        <span class="text-neutral-400">Memory:</span>
                                        <span class="font-bold text-white">{{ $variant->ram_mb >= 1024 ? ($variant->ram_mb / 1024).' GB ECC' : $variant->ram_mb.' MB' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-1 border-b border-white/[0.04]">
                                        <span class="text-neutral-400">Storage:</span>
                                        <span class="font-bold text-white">{{ $variant->disk_gb }} GB Gen4 NVMe</span>
                                    </div>
                                    <div class="flex items-center justify-between py-1 border-b border-white/[0.04]">
                                        <span class="text-neutral-400">Bandwidth:</span>
                                        <span class="font-bold text-white">{{ $variant->bandwidth_gb >= 1000 ? ($variant->bandwidth_gb / 1000).' TB' : $variant->bandwidth_gb.' GB' }}</span>
                                    </div>

                                    @if (!empty($variant->specs_json))
                                        @foreach ($variant->specs_json as $specKey => $specVal)
                                            <div class="flex items-center justify-between py-1 border-b border-white/[0.04] text-[11px]">
                                                <span class="text-neutral-500 capitalize">{{ $specKey }}:</span>
                                                <span class="text-neutral-300 font-medium truncate max-w-[130px]">{{ $specVal }}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>

                            {{-- Action Button --}}
                            <div class="pt-6">
                                <a href="{{ route('portal.checkout', array_filter(['plan' => $variant->uuid, 'currency' => $currency, 'billing' => $billingPeriod, 'tier' => $tier?->uuid])) }}"
                                    class="w-full py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider bg-white/10 hover:bg-cyan-500 hover:text-black text-white transition-all duration-200 shadow flex items-center justify-center gap-1.5">
                                    <span>Deploy Now</span>
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="text-center py-16 border border-dashed border-white/10 rounded-2xl">
                <p class="text-neutral-400 text-sm">No products currently published in this category.</p>
                <button wire:click="selectCategory('all')" class="mt-3 text-xs text-cyan-400 hover:underline">
                    View all platform products &rarr;
                </button>
            </div>
        @endforelse
    </main>

    {{-- Storefront Footer --}}
    <footer class="border-t border-white/[0.08] bg-[#0a0a0c] py-12 text-xs text-neutral-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-3">
                <img src="/STKcdr.svg" class="size-6" alt="STX Enterprises" />
                <span class="font-mono text-neutral-300">&copy; {{ date('Y') }} STX Hosting Platform &bull; All Rights Reserved.</span>
            </div>

            <div class="flex items-center gap-6">
                <a href="{{ route('store.pricing') }}" class="hover:text-white transition-colors">Pricing Catalog</a>
                <a href="{{ route('status.public') }}" class="hover:text-white transition-colors">Infrastructure Health</a>
                <a href="{{ route('portal.index') }}" class="hover:text-white transition-colors">Client Area</a>
                <a href="{{ route('login') }}" class="hover:text-white transition-colors">Control Panel</a>
            </div>
        </div>
    </footer>
</div>
