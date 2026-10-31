<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-emerald-600/15 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar current="databases" :currency="$currency" />

    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24 relative z-10 w-full space-y-16">
        <div class="text-center space-y-4 max-w-3xl mx-auto pt-6">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-mono font-bold tracking-widest uppercase">
                <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                AUTONOMOUS MANAGED DATABASES
            </div>

            <h1 class="text-4xl md:text-6xl font-black tracking-tight text-white leading-tight">
                Managed <span class="text-gradient">Database</span> Clusters
            </h1>

            <p class="text-base md:text-lg text-neutral-300 leading-relaxed font-sans">
                Deploy fully managed, high-performance database engines with automated daily backups, connection pooling, private networking, and point-in-time recovery.
            </p>

            <div class="flex items-center justify-center gap-4 pt-4">
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
            </div>
        </div>

        {{-- Supported Engines Banner --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="glass-card rounded-xl p-4 text-center space-y-1">
                <span class="text-xs uppercase font-mono tracking-wider text-emerald-400 font-bold">PostgreSQL 16</span>
                <p class="text-[11px] text-neutral-400">JSONB, pgvector, full ACID</p>
            </div>
            <div class="glass-card rounded-xl p-4 text-center space-y-1">
                <span class="text-xs uppercase font-mono tracking-wider text-cyan-400 font-bold">MySQL 8.4</span>
                <p class="text-[11px] text-neutral-400">Web standard, high throughput</p>
            </div>
            <div class="glass-card rounded-xl p-4 text-center space-y-1">
                <span class="text-xs uppercase font-mono tracking-wider text-rose-400 font-bold">Redis 7.2</span>
                <p class="text-[11px] text-neutral-400">In-memory caching & pub/sub</p>
            </div>
            <div class="glass-card rounded-xl p-4 text-center space-y-1">
                <span class="text-xs uppercase font-mono tracking-wider text-amber-400 font-bold">ClickHouse</span>
                <p class="text-[11px] text-neutral-400">Columnar real-time analytics</p>
            </div>
        </div>

        {{-- Database Plans --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($products as $prod)
                @php
                    $primaryVariant = $prod->variants->first();
                    $tier = $primaryVariant?->pricingTiers->first();
                    $priceMonthlyUsd = max(5, (float) ($tier?->price_monthly_usd ?: 15));
                    $priceMonthlyLkr = ($tier && (float)$tier->price_monthly_lkr > 0) ? (float)$tier->price_monthly_lkr : round($priceMonthlyUsd * 305, 0);

                    $displayPrice = ($currency === 'USD')
                        ? '$' . number_format($priceMonthlyUsd, 2)
                        : 'Rs ' . number_format($priceMonthlyLkr, 0);
                @endphp

                <div class="glass-card rounded-2xl p-6 flex flex-col justify-between transition-all relative">
                    <div>
                        <span class="text-xs uppercase font-mono tracking-wider text-emerald-400 block mb-2">Dedicated Database Instance</span>
                        <h2 class="text-xl font-bold text-white mb-2">{{ $prod->name }}</h2>
                        <p class="text-xs text-neutral-300 leading-relaxed mb-6">{{ $prod->description }}</p>

                        <div class="mb-6 pb-6 border-b border-white/10">
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-extrabold text-white font-mono">{{ $displayPrice }}</span>
                                <span class="text-xs text-neutral-400">/ month</span>
                            </div>
                        </div>

                        @if ($primaryVariant)
                            <div class="grid grid-cols-2 gap-2 text-xs font-mono mb-6 bg-white/[0.02] p-3 rounded-xl border border-white/5">
                                <div>
                                    <span class="text-neutral-500 block text-[10px]">CPU</span>
                                    <span class="text-white font-bold">{{ $primaryVariant->vcpu }} vCPU</span>
                                </div>
                                <div>
                                    <span class="text-neutral-500 block text-[10px]">RAM</span>
                                    <span class="text-white font-bold">{{ $primaryVariant->ram_mb >= 1024 ? ($primaryVariant->ram_mb / 1024) . ' GB' : $primaryVariant->ram_mb . ' MB' }}</span>
                                </div>
                                <div>
                                    <span class="text-neutral-500 block text-[10px]">NVME DISK</span>
                                    <span class="text-white font-bold">{{ $primaryVariant->disk_gb }} GB</span>
                                </div>
                                <div>
                                    <span class="text-neutral-500 block text-[10px]">CONNECTIONS</span>
                                    <span class="text-white font-bold">1,000+ Pooled</span>
                                </div>
                            </div>
                        @endif

                        <ul class="space-y-2 text-xs text-neutral-300 mb-6">
                            <li class="flex items-center gap-2"><span class="text-cyan-400 font-bold">&#10003;</span> Automated S3 Snapshots & Backups</li>
                            <li class="flex items-center gap-2"><span class="text-cyan-400 font-bold">&#10003;</span> Integrated Connection Pooler (PgBouncer)</li>
                            <li class="flex items-center gap-2"><span class="text-cyan-400 font-bold">&#10003;</span> Isolated Private WireGuard Virtual Network</li>
                            <li class="flex items-center gap-2"><span class="text-cyan-400 font-bold">&#10003;</span> SSL Encrypted Ingress with Dynamic TLS</li>
                        </ul>
                    </div>

                    <div>
                        <button type="button" wire:click="orderPlan('{{ $primaryVariant?->uuid }}', '{{ $tier?->uuid }}')"
                            class="w-full py-3 rounded-xl font-bold text-xs uppercase tracking-wider bg-white/10 hover:bg-white/20 text-white border border-white/20 transition-all hover:border-cyan-400 hover:text-cyan-300">
                            Provision Database &rarr;
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-3 text-center py-16 glass-card rounded-2xl">
                    <p class="text-sm text-neutral-400">Loading database cluster inventory...</p>
                </div>
            @endforelse
        </div>
    </main>

    <x-storefront.footer />
</div>

