<div class="min-h-screen bg-[#030014] text-white flex flex-col justify-between relative overflow-hidden">
    {{-- Background Grid & Glow --}}
    <div class="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0"></div>
    <div class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-purple-600/15 rounded-full blur-[140px] pointer-events-none -z-10"></div>

    <x-storefront.navbar />

    <main class="flex-1 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-24 relative z-10 w-full space-y-10">
        {{-- Hero Header --}}
        <div class="text-center space-y-4 max-w-2xl mx-auto pt-6">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-300 text-xs font-mono font-bold tracking-widest uppercase">
                <span class="size-1.5 rounded-full bg-purple-400 animate-pulse"></span>
                COMPLIANCE &amp; POLICIES
            </div>

            <h1 class="text-3xl sm:text-5xl font-black tracking-tight text-white leading-tight">
                @if ($type === 'terms')
                    Terms of <span class="text-gradient">Service</span>
                @elseif ($type === 'privacy')
                    Privacy <span class="text-gradient">Policy</span>
                @elseif ($type === 'refund')
                    Refund &amp; <span class="text-gradient">Cancellation</span>
                @else
                    Cookie <span class="text-gradient">Policy</span>
                @endif
            </h1>

            <p class="text-xs font-mono text-neutral-400">Last Updated: September 2026</p>
        </div>

        {{-- Legal Navigation Tabs --}}
        <div class="flex items-center justify-center gap-2 text-xs font-mono border-b border-white/10 pb-4">
            <a href="{{ route('store.terms') }}" wire:navigate
                @class([
                    'px-4 py-2 rounded-xl transition-all border font-bold',
                    'bg-purple-600/20 border-purple-500/50 text-purple-300' => $type === 'terms',
                    'bg-white/5 border-white/10 text-neutral-400 hover:text-white' => $type !== 'terms',
                ])>
                Terms
            </a>
            <a href="{{ route('store.privacy') }}" wire:navigate
                @class([
                    'px-4 py-2 rounded-xl transition-all border font-bold',
                    'bg-purple-600/20 border-purple-500/50 text-purple-300' => $type === 'privacy',
                    'bg-white/5 border-white/10 text-neutral-400 hover:text-white' => $type !== 'privacy',
                ])>
                Privacy
            </a>
            <a href="{{ route('store.refund') }}" wire:navigate
                @class([
                    'px-4 py-2 rounded-xl transition-all border font-bold',
                    'bg-purple-600/20 border-purple-500/50 text-purple-300' => $type === 'refund',
                    'bg-white/5 border-white/10 text-neutral-400 hover:text-white' => $type !== 'refund',
                ])>
                Refunds
            </a>
            <a href="{{ route('store.cookies') }}" wire:navigate
                @class([
                    'px-4 py-2 rounded-xl transition-all border font-bold',
                    'bg-purple-600/20 border-purple-500/50 text-purple-300' => $type === 'cookies',
                    'bg-white/5 border-white/10 text-neutral-400 hover:text-white' => $type !== 'cookies',
                ])>
                Cookies
            </a>
        </div>

        {{-- Content Card --}}
        <div class="glass-card rounded-3xl p-8 sm:p-12 border border-white/10 space-y-8 text-xs leading-relaxed text-neutral-300 font-sans">
            @if ($type === 'terms')
                <section class="space-y-2">
                    <h2 class="text-base font-bold text-white">1. Acceptance of Terms</h2>
                    <p>
                        By creating an account, ordering infrastructure resources, or accessing the STX Cloud platform, you agree to comply with and be bound by these Terms of Service. If you do not agree to these terms, you may not provision or operate services on our network.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-bold text-white">2. Scope of Services</h2>
                    <p>
                        STX Enterprises provides Cloud VPS compute nodes, automated web application deployment engines, managed container runtimes, managed databases, and related orchestration tools (invoicing, support ticketing, deployment logs, metrics).
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-bold text-white">3. Acceptable Use Policy</h2>
                    <p>
                        You agree not to use the platform for any illegal activities, including but not limited to: malware distribution, phishing, unauthorized port scanning, denial-of-service (DDoS) attacks, crypto mining on shared resources, or unsolicited bulk email spam. Violations will result in immediate suspension without prior notice.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-bold text-white">4. Billing &amp; Renewals</h2>
                    <p>
                        Services are billed in advance on recurring monthly or annual cycles according to the plan selected. Automated invoices and renewal notices are delivered to your registered email address and dashboard prior to expiration.
                    </p>
                </section>
            @elseif ($type === 'privacy')
                <section class="space-y-2">
                    <h2 class="text-base font-bold text-white">1. Information We Collect</h2>
                    <p>
                        We collect essential contact information (email address, account username), payment transaction receipts uploaded for verification, and technical logs (IP addresses for SSH session auditing and system telemetry) required to securely provide our services.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-bold text-white">2. Data Security &amp; Encryption</h2>
                    <p>
                        All user secrets, API credentials, and SSH private keys are stored using industry-standard AES-256-GCM encryption. We do not sell or monetize personal customer information to third-party data aggregators.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-bold text-white">3. Third-Party Integrations</h2>
                    <p>
                        When enabled, deployment webhooks interact with GitHub and GitLab. Network ingress and reverse proxying are managed via internal Traefik and edge Anycast nodes.
                    </p>
                </section>
            @elseif ($type === 'refund')
                <section class="space-y-2">
                    <h2 class="text-base font-bold text-white">1. 7-Day Money-Back Guarantee</h2>
                    <p>
                        We offer a 7-day money-back guarantee for new subscriptions on Web App Hosting, Bot Hosting, and Managed Database tiers if our infrastructure fails to meet our published technical specifications or SLA commitments.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-bold text-white">2. Cloud VPS Allocations</h2>
                    <p>
                        Dedicated Cloud VPS allocations and custom IPv4 address transit reservations are non-refundable once root credentials have been delivered, unless an extended outage occurs that violates our 99.9% uptime SLA.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-bold text-white">3. How to Request SLA Credit</h2>
                    <p>
                        To request an SLA credit or refund, submit a support ticket in your Client Dashboard or contact on-duty engineers via Discord within 7 days of the incident.
                    </p>
                </section>
            @else
                <section class="space-y-2">
                    <h2 class="text-base font-bold text-white">1. Essential Cookies Only</h2>
                    <p>
                        STX uses strictly necessary session cookies and local storage tokens required to maintain authenticated dashboard sessions, anti-CSRF protections, and live WebSocket connections.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-bold text-white">2. No Cross-Site Advertisers</h2>
                    <p>
                        We do not utilize third-party advertising cookies or behavioural tracking scripts. Your browsing activity on our store remains completely private.
                    </p>
                </section>
            @endif
        </div>
    </main>

    <x-storefront.footer />
</div>

