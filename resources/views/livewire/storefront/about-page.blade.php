<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-purple-600/15 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar current="about" />

    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24 relative z-10 w-full space-y-20">
        {{-- Hero Header --}}
        <div class="text-center space-y-4 max-w-3xl mx-auto pt-6">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-300 text-xs font-mono font-bold tracking-widest uppercase">
                <span class="size-1.5 rounded-full bg-purple-400 animate-pulse"></span>
                OUR ARCHITECTURAL PHILOSOPHY
            </div>

            <h1 class="text-4xl md:text-6xl font-black tracking-tight text-white leading-tight">
                Built for <span class="text-gradient">Builders</span>
            </h1>

            <p class="text-base md:text-lg text-neutral-300 leading-relaxed font-sans">
                STX runs your compute hosting, container pipelines, and edge network so you can focus on writing software that scales effortlessly.
            </p>
        </div>

        {{-- Metrics Band --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 max-w-5xl mx-auto">
            <div class="glass-card rounded-2xl p-6 text-center border border-white/10">
                <div class="text-3xl sm:text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-cyan-400">99.9%</div>
                <div class="text-xs font-mono text-neutral-400 uppercase tracking-wider mt-1">Uptime SLA</div>
            </div>
            <div class="glass-card rounded-2xl p-6 text-center border border-white/10">
                <div class="text-3xl sm:text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-emerald-400">&lt; 25ms</div>
                <div class="text-xs font-mono text-neutral-400 uppercase tracking-wider mt-1">Edge Latency</div>
            </div>
            <div class="glass-card rounded-2xl p-6 text-center border border-white/10">
                <div class="text-3xl sm:text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-purple-400">100%</div>
                <div class="text-xs font-mono text-neutral-400 uppercase tracking-wider mt-1">NVMe PCIe Gen4</div>
            </div>
            <div class="glass-card rounded-2xl p-6 text-center border border-white/10">
                <div class="text-3xl sm:text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-400">24/7/365</div>
                <div class="text-xs font-mono text-neutral-400 uppercase tracking-wider mt-1">Direct Engineering</div>
            </div>
        </div>

        {{-- Core Pillars Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl mx-auto">
            <div class="glass-card rounded-3xl p-8 border border-white/10 hover:border-purple-500/40 transition-all space-y-4">
                <div class="size-12 rounded-2xl bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center text-white shadow-lg">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                </div>
                <h3 class="text-xl font-bold text-white">One platform, every runtime</h3>
                <p class="text-sm text-neutral-400 leading-relaxed">
                    Web apps, Node.js, Python, Go, Rust, and Discord bots all deploy the same way — push a git repository or upload a archive, and our platform detects the runtime and builds it in containers automatically.
                </p>
            </div>

            <div class="glass-card rounded-3xl p-8 border border-white/10 hover:border-cyan-500/40 transition-all space-y-4">
                <div class="size-12 rounded-2xl bg-gradient-to-br from-cyan-500 to-emerald-500 flex items-center justify-center text-white shadow-lg">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </div>
                <h3 class="text-xl font-bold text-white">Your own domain, instant SSL</h3>
                <p class="text-sm text-neutral-400 leading-relaxed">
                    Every deployment gets an automatic subdomain and a free Let's Encrypt SSL certificate the moment it goes live — no manual DNS wrangling or open port configurations required.
                </p>
            </div>

            <div class="glass-card rounded-3xl p-8 border border-white/10 hover:border-pink-500/40 transition-all space-y-4">
                <div class="size-12 rounded-2xl bg-gradient-to-br from-pink-500 to-purple-600 flex items-center justify-center text-white shadow-lg">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </div>
                <h3 class="text-xl font-bold text-white">Real support, not just tickets</h3>
                <p class="text-sm text-neutral-400 leading-relaxed">
                    Live chat directly with our engineering team on WhatsApp and Discord, instant automated alerts for your services, and transparent billing logs for every transaction.
                </p>
            </div>
        </div>

        {{-- Enterprise Infrastructure Overview --}}
        <div class="max-w-4xl mx-auto glass-card rounded-3xl p-8 sm:p-12 border border-white/10 space-y-6">
            <h2 class="text-2xl font-bold text-white">Decentralized Edge Network</h2>
            <p class="text-sm text-neutral-300 leading-relaxed">
                Our infrastructure operates on bare-metal enterprise hardware powered by AMD EPYC™ processors and enterprise PCIe Gen4 NVMe arrays housed in Tier-3 data centers across Falkenstein (Germany), Nuremberg (Germany), Helsinki (Finland), and Colombo (Sri Lanka).
            </p>
            <p class="text-sm text-neutral-400 leading-relaxed">
                By integrating self-healing Docker engines, automated Let's Encrypt TLS issuance, Traefik reverse proxy orchestration, and hardware-level DDoS filtering, we provide developers and businesses with an affordable, high-velocity alternative to monopolistic hyperscalers.
            </p>
            <div class="pt-4 flex flex-wrap gap-4">
                <a href="{{ route('store.pricing') }}" wire:navigate
                    class="px-6 py-3 rounded-xl bg-gradient-to-r from-purple-600 to-cyan-500 text-white font-bold text-xs shadow-glow-violet transition-all">
                    View Infrastructure Plans
                </a>
                <a href="{{ route('status.public') }}" wire:navigate
                    class="px-6 py-3 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-xs transition-all">
                    Check System Health
                </a>
            </div>
        </div>
    </main>

    <x-storefront.footer />
</div>

