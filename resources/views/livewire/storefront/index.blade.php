<div class="relative w-full overflow-hidden">
    {{-- Cosmic Background Orbs --}}
    <div class="fixed inset-0 pointer-events-none -z-10">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[750px] h-[450px] bg-purple-600/15 rounded-full blur-[140px]"></div>
        <div class="absolute top-[35%] -left-32 w-[550px] h-[350px] bg-cyan-600/10 rounded-full blur-[140px]"></div>
        <div class="absolute top-[65%] -right-32 w-[600px] h-[400px] bg-blue-600/10 rounded-full blur-[150px]"></div>
    </div>

    {{-- 1. STICKY GLASS NAVBAR --}}
    <nav class="sticky top-0 z-50 glass-nav transition-all duration-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            {{-- Logo --}}
            <div class="flex items-center gap-6">
                <a href="/" class="flex items-center gap-2 hover:opacity-90 transition-opacity">
                    <img src="/STKcdr.svg" alt="STX Enterprises" class="size-7" />
                    <span class="text-lg font-extrabold tracking-tight">
                        <span class="text-cyan-400">STX</span> <span class="text-white">Hosting</span>
                    </span>
                </a>

                {{-- Products Menu Links --}}
                <div class="hidden md:flex items-center gap-1 text-xs font-medium text-neutral-300">
                    <button type="button" wire:click="selectCategory('vps')"
                        @class(['px-3 py-1.5 rounded-lg hover:text-white transition-colors', 'text-cyan-400 font-bold bg-white/5' => $selectedCategory === 'vps'])>
                        Cloud VPS
                    </button>
                    <button type="button" wire:click="selectCategory('game-server')"
                        @class(['px-3 py-1.5 rounded-lg hover:text-white transition-colors', 'text-cyan-400 font-bold bg-white/5' => $selectedCategory === 'game-server'])>
                        Game Servers
                    </button>
                    <button type="button" wire:click="selectCategory('web-app')"
                        @class(['px-3 py-1.5 rounded-lg hover:text-white transition-colors', 'text-cyan-400 font-bold bg-white/5' => $selectedCategory === 'web-app'])>
                        App Hosting
                    </button>
                    <button type="button" wire:click="selectCategory('bot')"
                        @class(['px-3 py-1.5 rounded-lg hover:text-white transition-colors', 'text-cyan-400 font-bold bg-white/5' => $selectedCategory === 'bot'])>
                        Bot Hosting
                    </button>
                    <button type="button" wire:click="selectCategory('database')"
                        @class(['px-3 py-1.5 rounded-lg hover:text-white transition-colors', 'text-cyan-400 font-bold bg-white/5' => $selectedCategory === 'database'])>
                        Databases
                    </button>
                    <a href="{{ route('status.public') }}"
                        class="px-3 py-1.5 rounded-lg text-emerald-400 hover:text-emerald-300 flex items-center gap-1.5">
                        <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Status
                    </a>
                </div>
            </div>

            {{-- Right Actions: Currency Toggle & Auth --}}
            <div class="flex items-center gap-3">
                {{-- Currency Switcher --}}
                <div class="flex items-center rounded-lg p-0.5 border border-white/10 bg-white/5 text-xs font-mono">
                    <button type="button" wire:click="selectCurrency('USD')"
                        @class([
                            'px-2 py-1 rounded transition-colors',
                            'bg-purple-600 text-white font-bold' => $currency === 'USD',
                            'text-neutral-400 hover:text-white' => $currency !== 'USD',
                        ])>
                        USD ($)
                    </button>
                    <button type="button" wire:click="selectCurrency('LKR')"
                        @class([
                            'px-2 py-1 rounded transition-colors',
                            'bg-purple-600 text-white font-bold' => $currency === 'LKR',
                            'text-neutral-400 hover:text-white' => $currency !== 'LKR',
                        ])>
                        LKR (Rs)
                    </button>
                </div>

                @auth
                    <a href="{{ route('dashboard') }}" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-gradient-to-r from-purple-600 to-cyan-500 hover:from-purple-500 hover:to-cyan-400 text-white shadow-lg transition-all">
                        Console &rarr;
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-xs text-neutral-300 hover:text-white px-2 py-1 transition-colors">
                        Sign In
                    </a>
                    <a href="{{ route('register') }}" class="text-xs font-semibold px-3.5 py-1.5 rounded-lg bg-gradient-to-r from-purple-600 to-cyan-500 hover:from-purple-500 hover:to-cyan-400 text-white shadow-lg transition-all">
                        Get Started
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- 2. ORBIT HERO SECTION --}}
    <section class="relative pt-16 pb-20 sm:pt-24 sm:pb-28 text-center max-w-5xl mx-auto px-4">
        {{-- Country Pill Badge --}}
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-purple-500/30 bg-purple-950/20 backdrop-blur text-purple-300 text-xs font-medium mb-6 shadow-[0_0_25px_-5px_rgba(168,85,247,0.3)]">
            <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span>Sri Lanka's #1 Hosting & Autonomous Cloud Platform</span>
            <span class="text-purple-400/60">|</span>
            <span class="text-cyan-400">Low-Latency Global Edge</span>
        </div>

        {{-- Main Headline --}}
        <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight leading-[1.1] mb-6">
            Everything Cloud.<br />
            <span class="text-gradient">One Autonomous Engine.</span>
        </h1>

        {{-- Subtitle --}}
        <p class="text-base sm:text-lg text-neutral-300 max-w-2xl mx-auto mb-8 font-normal leading-relaxed">
            Deploy high-performance Cloud VPS, game servers, applications, and managed databases with sub-millisecond edge routing, NVMe Gen4 storage, and automated orchestration.
        </p>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap items-center justify-center gap-4 mb-12">
            <a href="#pricing-catalog" class="px-6 py-3 rounded-xl font-bold text-sm bg-gradient-to-r from-purple-600 to-cyan-500 hover:from-purple-500 hover:to-cyan-400 text-white shadow-[0_0_35px_-5px_rgba(124,58,237,0.5)] transition-all transform hover:-translate-y-0.5">
                Explore Hosting Plans &rarr;
            </a>
            <a href="{{ route('status.public') }}" class="px-6 py-3 rounded-xl font-semibold text-sm glass-card text-neutral-200 hover:text-white transition-all">
                Live System Status ({{ $monitorsSummary['uptime'] ?? '99.99' }}%)
            </a>
        </div>

        {{-- Live Metrics Bar --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 max-w-4xl mx-auto">
            <div class="glass-card rounded-xl p-3.5 text-center">
                <span class="text-xl sm:text-2xl font-extrabold text-cyan-400 font-mono">99.99%</span>
                <span class="block text-xs text-neutral-400 mt-0.5">Guaranteed SLA Uptime</span>
            </div>
            <div class="glass-card rounded-xl p-3.5 text-center">
                <span class="text-xl sm:text-2xl font-extrabold text-purple-400 font-mono">&lt; 15 ms</span>
                <span class="block text-xs text-neutral-400 mt-0.5">Edge Gateway Latency</span>
            </div>
            <div class="glass-card rounded-xl p-3.5 text-center">
                <span class="text-xl sm:text-2xl font-extrabold text-emerald-400 font-mono">10 Gbps</span>
                <span class="block text-xs text-neutral-400 mt-0.5">Redundant Uplinks</span>
            </div>
            <div class="glass-card rounded-xl p-3.5 text-center">
                <span class="text-xl sm:text-2xl font-extrabold text-amber-400 font-mono">60s</span>
                <span class="block text-xs text-neutral-400 mt-0.5">Instant Provisioning</span>
            </div>
        </div>
    </section>

    {{-- 3. BENTO GRID ARCHITECTURE FEATURES --}}
    <section class="max-w-6xl mx-auto px-4 py-16">
        <div class="text-center mb-12">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-white">Engineered for Maximum Throughput</h2>
            <p class="text-xs sm:text-sm text-neutral-400 mt-2">Next-generation hardware stacks designed for high-density, mission-critical workloads.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            {{-- Feature 1 --}}
            <div class="glass-card rounded-2xl p-6 md:col-span-2 relative overflow-hidden">
                <div class="flex items-center gap-3 mb-4">
                    <span class="size-8 rounded-lg bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 flex items-center justify-center font-mono font-bold text-sm">
                        IO
                    </span>
                    <h3 class="text-lg font-bold text-white">Enterprise NVMe Gen4 Storage Arrays</h3>
                </div>
                <p class="text-xs text-neutral-300 leading-relaxed max-w-xl">
                    Every VPS node is backed by RAID-10 enterprise PCIe Gen4 NVMe arrays delivering over 850,000 IOPS and 7,000 MB/s sequential read throughput. Zero I/O bottlenecks for heavy database queries and game servers.
                </p>
                <div class="mt-6 flex flex-wrap gap-2 text-[11px] font-mono text-cyan-400">
                    <span class="px-2 py-0.5 rounded bg-cyan-500/10 border border-cyan-500/20">PCIe 4.0</span>
                    <span class="px-2 py-0.5 rounded bg-cyan-500/10 border border-cyan-500/20">850k IOPS</span>
                    <span class="px-2 py-0.5 rounded bg-cyan-500/10 border border-cyan-500/20">RAID-10 Data Safety</span>
                </div>
            </div>

            {{-- Feature 2 --}}
            <div class="glass-card rounded-2xl p-6 relative overflow-hidden">
                <div class="flex items-center gap-3 mb-4">
                    <span class="size-8 rounded-lg bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center font-mono font-bold text-sm">
                        SEC
                    </span>
                    <h3 class="text-lg font-bold text-white">2.5 Tbps DDoS Shield</h3>
                </div>
                <p class="text-xs text-neutral-300 leading-relaxed">
                    Automated multi-layer volumetric and protocol scrubbing protects all games, bots, and APIs with zero latency penalty.
                </p>
            </div>

            {{-- Feature 3 --}}
            <div class="glass-card rounded-2xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="size-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center font-mono font-bold text-sm">
                        NET
                    </span>
                    <h3 class="text-lg font-bold text-white">Direct Peering Fabric</h3>
                </div>
                <p class="text-xs text-neutral-300 leading-relaxed">
                    Direct interconnects across Hetzner, Vultr, AWS, and local South Asian internet exchanges ensure lowest ping for gamers and users.
                </p>
            </div>

            {{-- Feature 4 --}}
            <div class="glass-card rounded-2xl p-6 md:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <span class="size-8 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center font-mono font-bold text-sm">
                        AUTO
                    </span>
                    <h3 class="text-lg font-bold text-white">Autonomous Git & Container Deployment</h3>
                </div>
                <p class="text-xs text-neutral-300 leading-relaxed max-w-xl">
                    Connect GitHub repositories or push Docker Compose files directly. The platform automatically pulls commits, provisions SSL certificates via Traefik, sets up health checks, and streams logs in real-time.
                </p>
            </div>
        </div>
    </section>

    {{-- 4. DYNAMIC PRICING MATRIX & PRODUCT CATALOG --}}
    <section id="pricing-catalog" class="max-w-6xl mx-auto px-4 py-16 scroll-mt-20">
        <div class="text-center mb-10">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                Transparent Pricing, <span class="text-gradient">Zero Hidden Fees</span>
            </h2>
            <p class="text-xs sm:text-sm text-neutral-400 mt-2">Scale instantly from developer testing to enterprise fleet clusters.</p>

            {{-- Category Filter Pills --}}
            <div class="flex flex-wrap items-center justify-center gap-2 mt-6">
                <button type="button" wire:click="selectCategory('all')"
                    @class([
                        'px-4 py-2 rounded-xl text-xs font-semibold transition-all',
                        'bg-purple-600 text-white shadow-lg' => $selectedCategory === 'all',
                        'glass-card text-neutral-300 hover:text-white' => $selectedCategory !== 'all',
                    ])>
                    All Hosting Plans
                </button>
                <button type="button" wire:click="selectCategory('vps')"
                    @class([
                        'px-4 py-2 rounded-xl text-xs font-semibold transition-all',
                        'bg-purple-600 text-white shadow-lg' => $selectedCategory === 'vps',
                        'glass-card text-neutral-300 hover:text-white' => $selectedCategory !== 'vps',
                    ])>
                    Cloud VPS
                </button>
                <button type="button" wire:click="selectCategory('game-server')"
                    @class([
                        'px-4 py-2 rounded-xl text-xs font-semibold transition-all',
                        'bg-purple-600 text-white shadow-lg' => $selectedCategory === 'game-server',
                        'glass-card text-neutral-300 hover:text-white' => $selectedCategory !== 'game-server',
                    ])>
                    Game Servers
                </button>
                <button type="button" wire:click="selectCategory('web-app')"
                    @class([
                        'px-4 py-2 rounded-xl text-xs font-semibold transition-all',
                        'bg-purple-600 text-white shadow-lg' => $selectedCategory === 'web-app',
                        'glass-card text-neutral-300 hover:text-white' => $selectedCategory !== 'web-app',
                    ])>
                    Application Hosting
                </button>
                <button type="button" wire:click="selectCategory('bot')"
                    @class([
                        'px-4 py-2 rounded-xl text-xs font-semibold transition-all',
                        'bg-purple-600 text-white shadow-lg' => $selectedCategory === 'bot',
                        'glass-card text-neutral-300 hover:text-white' => $selectedCategory !== 'bot',
                    ])>
                    Bot Hosting
                </button>
                <button type="button" wire:click="selectCategory('database')"
                    @class([
                        'px-4 py-2 rounded-xl text-xs font-semibold transition-all',
                        'bg-purple-600 text-white shadow-lg' => $selectedCategory === 'database',
                        'glass-card text-neutral-300 hover:text-white' => $selectedCategory !== 'database',
                    ])>
                    Databases
                </button>
            </div>

            {{-- Billing Cycle Switcher --}}
            <div class="flex items-center justify-center gap-3 mt-6">
                <span @class(['text-xs font-medium', 'text-white' => $billingPeriod === 'monthly', 'text-neutral-400' => $billingPeriod !== 'monthly'])>Monthly Billing</span>
                <button type="button" wire:click="selectBillingPeriod('{{ $billingPeriod === 'monthly' ? 'yearly' : 'monthly' }}')"
                    class="relative w-12 h-6 rounded-full bg-neutral-800 border border-white/20 transition-colors p-0.5">
                    <span @class([
                        'block size-4.5 rounded-full bg-purple-500 transition-transform duration-200 shadow-md',
                        'translate-x-6' => $billingPeriod === 'yearly',
                        'translate-x-0.5' => $billingPeriod === 'monthly',
                    ])></span>
                </button>
                <span @class(['text-xs font-medium flex items-center gap-1.5', 'text-white' => $billingPeriod === 'yearly', 'text-neutral-400' => $billingPeriod !== 'yearly'])>
                    Yearly Billing
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">Save 20%</span>
                </span>
            </div>
        </div>

        {{-- Products Grid --}}
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
                            Most Popular Choice
                        </div>
                    @endif

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs uppercase font-mono tracking-wider text-purple-400">{{ $prod->category }}</span>
                            @if ($prod->is_popular)
                                <span class="size-2 rounded-full bg-cyan-400 animate-pulse"></span>
                            @endif
                        </div>

                        <h3 class="text-xl font-bold text-white mb-2">{{ $prod->name }}</h3>
                        <p class="text-xs text-neutral-300 leading-relaxed mb-6">{{ $prod->description }}</p>

                        {{-- Price Display --}}
                        <div class="mb-6 pb-6 border-b border-white/10">
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl sm:text-4xl font-extrabold text-white font-mono">{{ $displayPrice }}</span>
                                <span class="text-xs text-neutral-400">/ month</span>
                            </div>
                            @if ($billingPeriod === 'yearly')
                                <span class="text-[11px] text-emerald-400 block mt-1">Billed annually (Includes 20% discount)</span>
                            @endif
                        </div>

                        {{-- Hardware Specifications --}}
                        @if ($primaryVariant)
                            <div class="grid grid-cols-2 gap-2 text-xs font-mono mb-6 bg-white/[0.02] p-3 rounded-xl border border-white/5">
                                <div>
                                    <span class="text-neutral-500 block text-[10px]">CPU</span>
                                    <span class="text-white font-bold">{{ $primaryVariant->vcpu }} vCPU</span>
                                </div>
                                <div>
                                    <span class="text-neutral-500 block text-[10px]">MEMORY</span>
                                    <span class="text-white font-bold">{{ $primaryVariant->ram_mb >= 1024 ? ($primaryVariant->ram_mb / 1024) . ' GB' : $primaryVariant->ram_mb . ' MB' }}</span>
                                </div>
                                <div>
                                    <span class="text-neutral-500 block text-[10px]">STORAGE</span>
                                    <span class="text-white font-bold">{{ $primaryVariant->disk_gb }} GB NVMe</span>
                                </div>
                                <div>
                                    <span class="text-neutral-500 block text-[10px]">BANDWIDTH</span>
                                    <span class="text-white font-bold">{{ $primaryVariant->bandwidth_tb }} TB</span>
                                </div>
                            </div>
                        @endif

                        {{-- Feature Highlights --}}
                        <ul class="space-y-2.5 text-xs text-neutral-300 mb-6">
                            <li class="flex items-center gap-2">
                                <span class="text-cyan-400 font-bold">&#10003;</span>
                                Dedicated IPv4 & Routed /64 IPv6
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-cyan-400 font-bold">&#10003;</span>
                                Automated Daily Snapshots & Backups
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-cyan-400 font-bold">&#10003;</span>
                                Autonomous Traefik Reverse Proxy & SSL
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-cyan-400 font-bold">&#10003;</span>
                                Full Root SSH & Docker API Access
                            </li>
                        </ul>
                    </div>

                    {{-- Deploy Action --}}
                    <div>
                        <button type="button" wire:click="orderPlan('{{ $primaryVariant?->uuid }}', '{{ $tier?->uuid }}')"
                            class="w-full py-3 rounded-xl font-bold text-xs uppercase tracking-wider bg-white/10 hover:bg-white/20 text-white border border-white/20 transition-all hover:border-cyan-400 hover:text-cyan-300">
                            Deploy Now &rarr;
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-3 text-center py-16 glass-card rounded-2xl">
                    <p class="text-sm text-neutral-400">No active products found in this category.</p>
                </div>
            @endforelse
        </div>
    </section>

    {{-- 5. REVIEWS & TESTIMONIALS --}}
    <section class="max-w-5xl mx-auto px-4 py-16">
        <div class="text-center mb-10">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-white">Trusted by Developers & Gaming Communities</h2>
            <p class="text-xs sm:text-sm text-neutral-400 mt-2">See what engineers and server owners say about STX Hosting infrastructure.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="glass-card rounded-2xl p-5 text-xs space-y-3">
                <div class="text-amber-400 font-mono text-sm">&starf;&starf;&starf;&starf;&starf;</div>
                <p class="text-neutral-300 leading-relaxed">
                    "Sub-15ms ping from Colombo to their edge gateway. We moved our game servers and production APIs to STX and CPU spikes dropped by 40%."
                </p>
                <div class="pt-2 border-t border-white/10 flex items-center gap-2">
                    <span class="font-bold text-white">Kavinda R.</span>
                    <span class="text-neutral-500">&bull; Game Server Admin</span>
                </div>
            </div>

            <div class="glass-card rounded-2xl p-5 text-xs space-y-3">
                <div class="text-amber-400 font-mono text-sm">&starf;&starf;&starf;&starf;&starf;</div>
                <p class="text-neutral-300 leading-relaxed">
                    "Having LKR payment options via bank transfer and card with automated provisioning made hosting our microservices seamless."
                </p>
                <div class="pt-2 border-t border-white/10 flex items-center gap-2">
                    <span class="font-bold text-white">Dilshan P.</span>
                    <span class="text-neutral-500">&bull; Lead Architect, FinTech</span>
                </div>
            </div>

            <div class="glass-card rounded-2xl p-5 text-xs space-y-3">
                <div class="text-amber-400 font-mono text-sm">&starf;&starf;&starf;&starf;&starf;</div>
                <p class="text-neutral-300 leading-relaxed">
                    "The autonomous deployment feature is literally Coolify on steroids with proper customer isolation and 24/7 reliability."
                </p>
                <div class="pt-2 border-t border-white/10 flex items-center gap-2">
                    <span class="font-bold text-white">Nuwan S.</span>
                    <span class="text-neutral-500">&bull; DevOps Engineer</span>
                </div>
            </div>
        </div>
    </section>

    {{-- 6. CTA BANNER --}}
    <section class="max-w-5xl mx-auto px-4 py-16">
        <div class="rounded-3xl p-8 sm:p-12 border border-purple-500/40 bg-gradient-to-r from-purple-900/40 via-cyan-900/30 to-blue-900/40 backdrop-blur text-center relative overflow-hidden shadow-2xl">
            <h2 class="text-2xl sm:text-4xl font-extrabold text-white mb-4">
                Launch Your Autonomous Cloud Instance in Seconds
            </h2>
            <p class="text-xs sm:text-sm text-neutral-300 max-w-xl mx-auto mb-8 leading-relaxed">
                Start with high-speed NVMe storage, dedicated IPv4, automated Traefik routing, and 24/7 technical assistance.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-4">
                <a href="#pricing-catalog" class="px-6 py-3 rounded-xl font-bold text-sm bg-white text-black hover:bg-neutral-100 shadow-xl transition-all">
                    View Catalog & Deploy
                </a>
                <a href="{{ route('status.public') }}" class="px-6 py-3 rounded-xl font-semibold text-sm border border-white/20 bg-white/5 hover:bg-white/10 text-white transition-all">
                    Check Infrastructure Health
                </a>
            </div>
        </div>
    </section>

    {{-- 7. STRUCTURED FOOTER --}}
    <footer class="border-t border-white/10 bg-[#030014] py-12 text-xs text-neutral-400">
        <div class="max-w-6xl mx-auto px-4 grid grid-cols-2 md:grid-cols-4 gap-8 mb-8">
            <div class="space-y-3 col-span-2 md:col-span-1">
                <div class="flex items-center gap-2">
                    <img src="/STKcdr.svg" alt="STX Enterprises" class="size-6" />
                    <span class="font-extrabold text-white text-sm">STX Enterprises</span>
                </div>
                <p class="text-neutral-400 leading-relaxed text-[11px]">
                    Enterprise-grade Cloud VPS, autonomous applications, game servers, and managed database clusters with edge routing.
                </p>
            </div>

            <div class="space-y-2">
                <span class="font-bold text-white text-xs block mb-3 uppercase tracking-wider">Cloud Products</span>
                <a href="#pricing-catalog" wire:click="selectCategory('vps')" class="block hover:text-white transition-colors">Dedicated Cloud VPS</a>
                <a href="#pricing-catalog" wire:click="selectCategory('game-server')" class="block hover:text-white transition-colors">Game Server Hosting</a>
                <a href="#pricing-catalog" wire:click="selectCategory('web-app')" class="block hover:text-white transition-colors">Autonomous App Hosting</a>
                <a href="#pricing-catalog" wire:click="selectCategory('bot')" class="block hover:text-white transition-colors">Bot & Worker Hosting</a>
                <a href="#pricing-catalog" wire:click="selectCategory('database')" class="block hover:text-white transition-colors">Managed Databases</a>
            </div>

            <div class="space-y-2">
                <span class="font-bold text-white text-xs block mb-3 uppercase tracking-wider">Telemetry & Support</span>
                <a href="{{ route('status.public') }}" class="block hover:text-white transition-colors">Public Status Dashboard</a>
                <a href="/portal/billing" class="block hover:text-white transition-colors">Customer Billing Portal</a>
                <a href="https://stxhosting.com/docs" target="_blank" class="block hover:text-white transition-colors">Documentation & Knowledgebase</a>
                <a href="mailto:support@shanutechx.com" class="block hover:text-white transition-colors">Technical Support Desk</a>
            </div>

            <div class="space-y-2">
                <span class="font-bold text-white text-xs block mb-3 uppercase tracking-wider">Legal & Compliance</span>
                <a href="#" class="block hover:text-white transition-colors">Terms of Service</a>
                <a href="#" class="block hover:text-white transition-colors">Privacy Policy</a>
                <a href="#" class="block hover:text-white transition-colors">Service Level Agreement (SLA)</a>
                <a href="#" class="block hover:text-white transition-colors">Fair Usage Policy</a>
            </div>
        </div>

        <div class="max-w-6xl mx-auto px-4 pt-6 border-t border-white/5 flex flex-wrap items-center justify-between gap-4 text-[11px]">
            <p>&copy; {{ date('Y') }} STX Enterprises Pvt Ltd. All rights reserved.</p>
            <p class="font-mono text-neutral-500">Autonomous Edge Fabric &bull; Running on Native Engine</p>
        </div>
    </footer>
</div>
