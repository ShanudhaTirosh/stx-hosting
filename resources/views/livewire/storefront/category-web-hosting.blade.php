<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-purple-600/15 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar current="web" :currency="$currency" />

    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24 relative z-10 w-full space-y-16">
        {{-- Hero Header --}}
        <div class="text-center space-y-4 max-w-3xl mx-auto pt-6">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-300 text-xs font-mono font-bold tracking-widest uppercase">
                <span class="size-1.5 rounded-full bg-purple-400 animate-pulse"></span>
                MANAGED CLOUD WEB PLATFORM
            </div>

            <h1 class="text-4xl md:text-6xl font-black tracking-tight text-white leading-tight">
                Fast, Reliable <span class="text-gradient">Web &amp; WordPress</span> Hosting
            </h1>

            <p class="text-base md:text-lg text-neutral-300 leading-relaxed font-sans">
                Optimized for speed and conversion with automated SSL certificates, built-in Redis object caching, and daily off-site cloud backups.
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

        {{-- Feature Highlights Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-5xl mx-auto">
            <div class="p-5 rounded-2xl glass-card border border-white/10 text-center space-y-2">
                <div class="size-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mx-auto">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <div class="text-sm font-bold text-white">Free Wildcard SSL</div>
                <div class="text-[11px] text-neutral-400">Automated Let's Encrypt certificates</div>
            </div>
            <div class="p-5 rounded-2xl glass-card border border-white/10 text-center space-y-2">
                <div class="size-10 rounded-xl bg-yellow-500/10 text-yellow-400 flex items-center justify-center mx-auto">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div class="text-sm font-bold text-white">Redis Object Cache</div>
                <div class="text-[11px] text-neutral-400">Sub-millisecond query responses</div>
            </div>
            <div class="p-5 rounded-2xl glass-card border border-white/10 text-center space-y-2">
                <div class="size-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center mx-auto">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </div>
                <div class="text-sm font-bold text-white">Daily Snapshots</div>
                <div class="text-[11px] text-neutral-400">Automated S3 disaster recovery</div>
            </div>
            <div class="p-5 rounded-2xl glass-card border border-white/10 text-center space-y-2">
                <div class="size-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center mx-auto">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                </div>
                <div class="text-sm font-bold text-white">1-Click Apps</div>
                <div class="text-[11px] text-neutral-400">WordPress, Ghost, Directus & PHP</div>
            </div>
        </div>

        {{-- Web Hosting Plans Grid --}}
        <div class="border-t border-white/[0.06] pt-12">
            <div class="text-center space-y-2 mb-10">
                <span class="text-xs font-mono uppercase font-bold text-purple-400 tracking-widest">Optimized Configurations</span>
                <h2 class="text-3xl font-extrabold text-white">Web &amp; WordPress Hosting Plans</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($products as $prod)
                    @php
                        $primaryVariant = $prod->variants->first();
                        $tier = $primaryVariant?->pricingTiers->first();
                        $priceMonthlyUsd = max(2.5, (float) ($tier?->price_monthly_usd ?: 4.99));
                        $priceYearlyUsd = max(25, (float) ($tier?->price_yearly_usd ?: $priceMonthlyUsd * 10));
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
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow-lg">
                                Best Value
                            </div>
                        @endif

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs uppercase font-mono tracking-wider text-purple-400">Web Hosting</span>
                                <span class="text-[11px] font-mono text-neutral-400">cPanel / Direct Admin Alternative</span>
                            </div>

                            <h3 class="text-xl font-bold text-white mb-2">{{ $prod->name }}</h3>
                            <p class="text-xs text-neutral-400 mb-6 leading-relaxed">
                                {{ $prod->description ?: 'High-speed NVMe storage with automated PHP 8.3 & MariaDB stack.' }}
                            </p>

                            <div class="flex items-baseline gap-1 mb-6">
                                <span class="text-3xl font-black text-white">{{ $displayPrice }}</span>
                                <span class="text-xs font-mono text-neutral-400">/month</span>
                            </div>

                            <ul class="space-y-3 pt-6 border-t border-white/[0.08] text-xs text-neutral-300">
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>NVMe Enterprise Storage</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Free SSL & Unlimited Bandwidth</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>PHP 8.1, 8.2 & 8.3 Selector</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Automated Daily Database Snapshots</span>
                                </li>
                            </ul>
                        </div>

                        <div class="pt-8">
                            <button type="button"
                                wire:click="orderPlan('{{ $primaryVariant?->uuid }}', '{{ $tier?->uuid }}')"
                                class="w-full py-3 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-xs shadow-glow-violet transition-all flex items-center justify-center gap-2">
                                Order {{ $prod->name }}
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full glass-card rounded-2xl p-12 text-center text-neutral-400">
                        No Web Hosting plans found in database. Contact support for customized shared hosting tiers.
                    </div>
                @endforelse
            </div>
        </div>
    </main>

    <x-storefront.footer />
</div>

