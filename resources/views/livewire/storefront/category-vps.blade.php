<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-purple-600/15 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar current="vps" :currency="$currency" />

    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24 relative z-10 w-full space-y-16">
        {{-- Hero Header --}}
        <div class="text-center space-y-4 max-w-3xl mx-auto pt-6">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-300 text-xs font-mono font-bold tracking-widest uppercase">
                <span class="size-1.5 rounded-full bg-purple-400 animate-pulse"></span>
                DEDICATED CLOUD INFRASTRUCTURE
            </div>

            <h1 class="text-4xl md:text-6xl font-black tracking-tight text-white leading-tight">
                Cloud VPS <span class="text-gradient">Infrastructure</span>
            </h1>

            <p class="text-base md:text-lg text-neutral-300 leading-relaxed font-sans">
                High-performance unmanaged and managed server allocations engineered for scalable networking deployments, microservices, and databases.
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

        {{-- VPS Plans Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($products as $prod)
                @php
                    $primaryVariant = $prod->variants->first();
                    $tier = $primaryVariant?->pricingTiers->first();
                    $priceMonthlyUsd = max(5, (float) ($tier?->price_monthly_usd ?: 15));
                    $priceYearlyUsd = max(50, (float) ($tier?->price_yearly_usd ?: $priceMonthlyUsd * 10));
                    $priceMonthlyLkr = ($tier && (float)$tier->price_monthly_lkr > 0) ? (float)$tier->price_monthly_lkr : round($priceMonthlyUsd * 305, 0);
                    $priceYearlyLkr = ($tier && (float)$tier->price_yearly_lkr > 0) ? (float)$tier->price_yearly_lkr : round($priceYearlyUsd * 305, 0);

                    $displayPrice = ($currency === 'USD')
                        ? ($billingPeriod === 'monthly' ? '$' . number_format($priceMonthlyUsd, 2) : '$' . number_format($priceYearlyUsd / 12, 2))
                        : ($billingPeriod === 'monthly' ? 'Rs ' . number_format($priceMonthlyLkr, 0) : 'Rs ' . number_format($priceYearlyLkr / 12, 0));
                @endphp

                <div @class([
                    'glass-card rounded-2xl p-6 flex flex-col justify-between transition-all relative',
                    'border-purple-500/50 shadow-[0_0_40px_-10px_rgba(168,85,247,0.3)]' => $prod->is_popular,
                ])>
                    @if ($prod->is_popular)
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-gradient-to-r from-purple-600 to-cyan-500 text-white shadow-lg">
                            Recommended
                        </div>
                    @endif

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs uppercase font-mono tracking-wider text-purple-400">Cloud VPS</span>
                            @if ($prod->is_popular)
                                <span class="size-2 rounded-full bg-cyan-400 animate-pulse"></span>
                            @endif
                        </div>

                        <h2 class="text-xl font-bold text-white mb-2">{{ $prod->name }}</h2>
                        <p class="text-xs text-neutral-300 leading-relaxed mb-6">{{ $prod->description }}</p>

                        {{-- Price Block --}}
                        <div class="mb-6 pb-6 border-b border-white/10">
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-extrabold text-white font-mono">{{ $displayPrice }}</span>
                                <span class="text-xs text-neutral-400">/ month</span>
                            </div>
                            @if ($billingPeriod === 'yearly')
                                <span class="text-[11px] text-emerald-400 block mt-1">Billed annually (Includes 20% discount)</span>
                            @endif
                        </div>

                        {{-- Hardware Specs --}}
                        @if ($primaryVariant)
                            <div class="grid grid-cols-2 gap-2 text-xs font-mono mb-6 bg-white/[0.02] p-3 rounded-xl border border-white/5">
                                <div>
                                    <span class="text-neutral-500 block text-[10px]">CPU</span>
                                    <span class="text-white font-bold">{{ $primaryVariant->vcpu }} Dedicated vCPU</span>
                                </div>
                                <div>
                                    <span class="text-neutral-500 block text-[10px]">RAM</span>
                                    <span class="text-white font-bold">{{ $primaryVariant->ram_mb >= 1024 ? ($primaryVariant->ram_mb / 1024) . ' GB' : $primaryVariant->ram_mb . ' MB' }} ECC</span>
                                </div>
                                <div>
                                    <span class="text-neutral-500 block text-[10px]">STORAGE</span>
                                    <span class="text-white font-bold">{{ $primaryVariant->disk_gb }} GB NVMe Gen4</span>
                                </div>
                                <div>
                                    <span class="text-neutral-500 block text-[10px]">NETWORK</span>
                                    <span class="text-white font-bold">{{ $primaryVariant->bandwidth_tb }} TB (10 Gbps)</span>
                                </div>
                            </div>
                        @endif

                        {{-- Features --}}
                        <ul class="space-y-2 text-xs text-neutral-300 mb-6">
                            <li class="flex items-center gap-2"><span class="text-cyan-400 font-bold">&#10003;</span> Dedicated Static IPv4 & /64 IPv6</li>
                            <li class="flex items-center gap-2"><span class="text-cyan-400 font-bold">&#10003;</span> 2.5 Tbps DDoS Shielding Included</li>
                            <li class="flex items-center gap-2"><span class="text-cyan-400 font-bold">&#10003;</span> Instant Automated OS Reinstallation</li>
                            <li class="flex items-center gap-2"><span class="text-cyan-400 font-bold">&#10003;</span> VNC Web Console & Full Root SSH</li>
                        </ul>
                    </div>

                    <div>
                        <button type="button" wire:click="orderPlan('{{ $primaryVariant?->uuid }}', '{{ $tier?->uuid }}')"
                            class="w-full py-3 rounded-xl font-bold text-xs uppercase tracking-wider bg-white/10 hover:bg-white/20 text-white border border-white/20 transition-all hover:border-cyan-400 hover:text-cyan-300">
                            Deploy Cloud VPS &rarr;
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-3 text-center py-16 glass-card rounded-2xl">
                    <p class="text-sm text-neutral-400">Loading Cloud VPS inventory...</p>
                </div>
            @endforelse
        </div>

        {{-- Global Network Footprint --}}
        <div class="glass-card rounded-3xl p-8 space-y-6">
            <div class="text-center max-w-xl mx-auto space-y-2">
                <h3 class="text-xl font-bold text-white">Global Edge Routing & Low-Latency Backbones</h3>
                <p class="text-xs text-neutral-400">Multi-homed BGP routing peering across tier-1 carriers ensures sub-millisecond roundtrips.</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5">
                    <span class="text-xs text-neutral-400 block font-mono">Falkenstein, Germany</span>
                    <span class="text-lg font-bold text-cyan-400 font-mono">14 ms Edge</span>
                </div>
                <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5">
                    <span class="text-xs text-neutral-400 block font-mono">Helsinki, Finland</span>
                    <span class="text-lg font-bold text-purple-400 font-mono">18 ms Edge</span>
                </div>
                <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5">
                    <span class="text-xs text-neutral-400 block font-mono">Singapore POP</span>
                    <span class="text-lg font-bold text-emerald-400 font-mono">22 ms Edge</span>
                </div>
                <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5">
                    <span class="text-xs text-neutral-400 block font-mono">Colombo, Sri Lanka</span>
                    <span class="text-lg font-bold text-amber-400 font-mono">12 ms Direct</span>
                </div>
            </div>
        </div>
    </main>

    <x-storefront.footer />
</div>

