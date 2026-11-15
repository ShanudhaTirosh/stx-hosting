@php
    $faqs = [
        ['question' => 'How do I pay without a credit card in Sri Lanka?', 'answer' => 'You can pay using standard Commercial Bank, Sampath Bank, HNB, BOC, or any Sri Lankan bank transfer or mobile banking app. When you place an order, you receive an official invoice in LKR or USD. Transfer the amount, upload your slip, and PayVerify AI verifies your receipt.'],
        ['question' => 'How long does Cloud VPS delivery take?', 'answer' => 'Cloud VPS servers are manually allocated and hardened by operators, with delivery taking 24 to 72 hours. Once provisioned, root credentials appear encrypted in your client portal.'],
        ['question' => 'How fast do app hosting and databases launch?', 'answer' => 'Git app containers and managed databases launch immediately once your payment receipt is approved.'],
        ['question' => 'Can I use my own custom domain?', 'answer' => 'Yes. Every application and email plan supports custom domains with automated Let\'s Encrypt SSL certificates and verified DNS records.'],
    ];
@endphp

<x-storefront.layout 
    :title="'Enterprise Cloud Hosting & NVMe VPS in Sri Lanka'" 
    :description="'Enterprise Cloud VPS, 1-click Docker app templates, and business email hosting in Sri Lanka. Pay by local bank transfer in LKR or USD with zero credit card required.'"
    :faqs="$faqs"
>
    <!-- Hero Section -->
    <section class="hero">
        <div class="wrap">
            <div class="hero-layout">
                <div data-reveal>
                    <div class="pill emerald" style="margin-bottom: 1.25rem;">
                        <span class="pill-dot"></span> Direct Bank Transfer &bull; LKR &amp; USD Dual Pricing &bull; No Card Required
                    </div>

                    <h1>Enterprise Cloud Hosting You Can Pay for with a Bank Transfer.</h1>
                    
                    <p class="lede">High-performance NVMe Cloud VPS, Git-driven container app hosting, managed databases, and custom domain email. Priced honestly in Sri Lankan Rupees or US Dollars. You order, pay by bank transfer, PayVerify AI verifies your slip, and your service goes live.</p>

                    <div class="actions">
                        <a class="btn" href="{{ route('store.pricing') }}">View All Plans &amp; Pricing</a>
                        <a class="btn ghost" href="{{ route('store.how-it-works') }}">How Ordering Works</a>
                    </div>

                    @if ($status['total'] > 0)
                        <div style="margin-top: 2rem; display: inline-flex; align-items: center; gap: 0.75rem; padding: 0.5rem 1rem; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius-sm); font-size: 0.88rem; box-shadow: var(--shadow-subtle);">
                            <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:{{ $status['down'] === 0 ? 'var(--emerald)' : 'var(--amber)' }}"></span>
                            <span>
                                @if ($status['down'] === 0)
                                    All <strong>{{ $status['total'] }}</strong> platform monitors are 100% operational.
                                @else
                                    {{ $status['down'] }} of {{ $status['total'] }} monitors have an open incident.
                                @endif
                                <a href="{{ route('status.public') }}" target="_blank" rel="noopener" style="margin-left:0.5rem;font-weight:600">View 90-Day Telemetry &rarr;</a>
                            </span>
                        </div>
                    @endif
                </div>

                <!-- Right Column: Interactive Platform Telemetry Shell -->
                <div data-reveal data-delay="120">
                    <div class="telemetry-card">
                        <div class="telemetry-header">
                            <div class="telemetry-dots">
                                <span class="telemetry-dot dot-red"></span>
                                <span class="telemetry-dot dot-amber"></span>
                                <span class="telemetry-dot dot-green"></span>
                            </div>
                            <span>stx-control-plane &bull; cluster-ready</span>
                        </div>
                        <div class="telemetry-body">
                            <div class="telemetry-line"><span class="telemetry-signal">$</span> stx cluster status --region=colombo-1</div>
                            <div class="telemetry-line"><span class="telemetry-accent">&gt;</span> Edge Gateway: <span class="telemetry-ok">ACTIVE (Caddy v2.8 / TLS 1.3)</span></div>
                            <div class="telemetry-line"><span class="telemetry-accent">&gt;</span> Storage Engine: <span class="telemetry-ok">NVMe PCIe 4.0 (ZFS Mirror)</span></div>
                            <div class="telemetry-line"><span class="telemetry-accent">&gt;</span> PayVerify AI: <span class="telemetry-ok">ONLINE (Gemini 2.5 Flash / dHash)</span></div>
                            <div class="telemetry-line"><span class="telemetry-accent">&gt;</span> Sentinel Telemetry: <span class="telemetry-ok">100% Operational</span></div>
                            
                            <div class="telemetry-stat-row">
                                <div>
                                    <div class="telemetry-stat-val" data-counter="99.98" data-decimals="2" data-suffix="%">99.98%</div>
                                    <div class="telemetry-stat-lbl">Uptime SLA</div>
                                </div>
                                <div>
                                    <div class="telemetry-stat-val" data-counter="31" data-suffix="+">31+</div>
                                    <div class="telemetry-stat-lbl">Docker Stacks</div>
                                </div>
                                <div>
                                    <div class="telemetry-stat-val">0%</div>
                                    <div class="telemetry-stat-lbl">Card FX Fees</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($index->isNotEmpty())
                <ul class="index" aria-label="Plans by Category" style="margin-top: 3.5rem;" data-reveal>
                    @foreach ($index as $row)
                        <li>
                            <a href="{{ route($row['route']) }}">
                                <span class="name">{{ $row['label'] }}</span>
                                <span class="from">from <strong>{{ $row['from'] }}</strong>@if ($row['term']) / {{ $row['term'] }}@endif</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <!-- Core Capabilities Band -->
    <section class="band soft" data-reveal>
        <div class="wrap">
            <div class="band-header">
                <h2>Built for Technical Teams, Agencies &amp; Growing Businesses</h2>
                <p>Pure isolation, modern developer tooling, and flexible local payment workflows built into one unified control plane.</p>
            </div>

            <div class="card-grid-4">
                <div class="card card-lift">
                    <div class="pill signal" style="margin-bottom:1rem">NVMe VPS</div>
                    <h3 style="font-size:1.25rem;margin-bottom:0.5rem">Cloud VPS Servers</h3>
                    <p style="margin:0;font-size:0.92rem;color:var(--muted)">Dedicated vCPU allocations with ultra-fast NVMe storage. Hand-allocated and hardened by engineers within <strong>24 to 72 hours</strong> with private root keys.</p>
                    <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--line-subtle)">
                        <a href="{{ route('store.vps') }}" style="font-weight:600;font-size:0.9rem">Explore VPS Plans &rarr;</a>
                    </div>
                </div>

                <div class="card card-lift">
                    <div class="pill emerald" style="margin-bottom:1rem">1-Click Apps</div>
                    <h3 style="font-size:1.25rem;margin-bottom:0.5rem">31+ Production Stacks</h3>
                    <p style="margin:0;font-size:0.92rem;color:var(--muted)">Deploy Next.js, WordPress, Ghost, Directus, n8n, Supabase, and Laravel in seconds. Automatic Let's Encrypt SSL certificates and zero-downtime Git redeployments.</p>
                    <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--line-subtle)">
                        <a href="{{ route('store.templates') }}" style="font-weight:600;font-size:0.9rem">Browse App Stacks &rarr;</a>
                    </div>
                </div>

                <div class="card card-lift">
                    <div class="pill ink" style="margin-bottom:1rem">Mail Hosting</div>
                    <h3 style="font-size:1.25rem;margin-bottom:0.5rem">Business Email</h3>
                    <p style="margin:0;font-size:0.92rem;color:var(--muted)">Run corporate email on your own domain with lightweight STX Webmail, pooled team storage, and automatic 2048-bit DKIM, SPF, and DMARC deliverability protection.</p>
                    <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--line-subtle)">
                        <a href="{{ route('store.email-hosting') }}" style="font-weight:600;font-size:0.9rem">See Email Plans &rarr;</a>
                    </div>
                </div>

                <div class="card card-lift">
                    <div class="pill amber" style="margin-bottom:1rem">PayVerify AI</div>
                    <h3 style="font-size:1.25rem;margin-bottom:0.5rem">Slip Verification</h3>
                    <p style="margin:0;font-size:0.92rem;color:var(--muted)">No international credit card required. Upload your bank slip; PayVerify AI extracts amounts, checks against perceptual hashes, and notifies staff for fast approval.</p>
                    <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--line-subtle)">
                        <a href="{{ route('store.how-it-works') }}" style="font-weight:600;font-size:0.9rem">How It Works &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Comparison Matrix: STX vs Legacy cPanel vs Hyperscalers -->
    <section class="band" data-reveal>
        <div class="wrap">
            <div class="band-header">
                <h2>Engineered Without Compromises</h2>
                <p>See why modern teams switch away from legacy shared cPanel and avoid the exorbitant complexity of hyperscalers.</p>
            </div>

            <div class="matrix-card">
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th scope="col" style="width:25%">Feature</th>
                            <th scope="col" style="width:25%">Legacy Shared cPanel</th>
                            <th scope="col" style="width:25%;background:var(--signal-subtle);color:var(--signal)">STX Cloud Platform</th>
                            <th scope="col" style="width:25%">Hyperscalers (AWS / GCP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Payment Method</strong></td>
                            <td>Credit Card / USD only</td>
                            <td style="background:var(--signal-subtle);font-weight:600"><span class="matrix-check">&#10003; Local Bank Transfer (LKR/USD)</span></td>
                            <td>Credit Card with FX limits</td>
                        </tr>
                        <tr>
                            <td><strong>Runtime Isolation</strong></td>
                            <td>Shared Apache / Noisy Neighbors</td>
                            <td style="background:var(--signal-subtle);font-weight:600"><span class="matrix-check">&#10003; Isolated Docker &amp; Dedicated VPS</span></td>
                            <td><span class="matrix-check">&#10003; Full VM / Container</span></td>
                        </tr>
                        <tr>
                            <td><strong>Pricing Predictability</strong></td>
                            <td>Hidden renewal hikes (2x–3x)</td>
                            <td style="background:var(--signal-subtle);font-weight:600"><span class="matrix-check">&#10003; 100% Flat Predictable Invoices</span></td>
                            <td>Surprise bandwidth &amp; IOPS charges</td>
                        </tr>
                        <tr>
                            <td><strong>Deployment Flow</strong></td>
                            <td>Manual FTP / Zip extract</td>
                            <td style="background:var(--signal-subtle);font-weight:600"><span class="matrix-check">&#10003; 1-Click Compose &amp; Git Webhooks</span></td>
                            <td>Complex IAM, VPC &amp; CI/CD rules</td>
                        </tr>
                        <tr>
                            <td><strong>Provisioning SLA</strong></td>
                            <td>Instant but unmonitored</td>
                            <td style="background:var(--signal-subtle);font-weight:600"><span class="matrix-check">&#10003; Instant Apps &bull; 24-72h Hand-Hardened VPS</span></td>
                            <td>Instant VM creation</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Visual 4-Step How It Works Band -->
    <section class="band dark" id="how" data-reveal>
        <div class="wrap">
            <div class="band-header">
                <h2>How Ordering Works in 4 Steps</h2>
                <p>Simple, transparent, and completely free of chargeback disputes.</p>
            </div>

            <ol class="flow-steps">
                <li class="flow-step">
                    <strong>1. Select Your Plan</strong>
                    <p>Choose your Cloud VPS specs, container app tier, or email package. Prices shown in LKR or USD are exact and transparent.</p>
                </li>
                <li class="flow-step">
                    <strong>2. Bank Transfer</strong>
                    <p>Checkout generates your invoice with bank account details (Commercial Bank, Sampath, HNB, BOC). Transfer funds via mobile banking or branch.</p>
                </li>
                <li class="flow-step">
                    <strong>3. Upload Slip</strong>
                    <p>Upload a photo or PDF receipt. PayVerify AI verifies the amount, runs perceptual tamper checks, and notifies operators.</p>
                </li>
                <li class="flow-step">
                    <strong>4. Service Activated</strong>
                    <p>App containers and business email launch immediately upon approval. Cloud VPS servers are hand-delivered in 24 to 72 hours.</p>
                </li>
            </ol>
        </div>
    </section>

    <!-- Frequently Asked Questions -->
    <section class="band" data-reveal>
        <div class="wrap-narrow">
            <div class="band-header">
                <h2>Frequently Asked Questions</h2>
                <p>Direct, clear answers to common questions about payment, provisioning, and platform security.</p>
            </div>

            <div class="accordion">
                @foreach ($faqs as $i => $faq)
                    <div class="accordion-item {{ $i === 0 ? 'open' : '' }}">
                        <button class="accordion-trigger" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}">
                            <span>{{ $faq['question'] }}</span>
                            <svg class="accordion-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="accordion-content">
                            <p>{{ $faq['answer'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:2.5rem;text-align:center">
                <a href="{{ route('store.faqs') }}" class="btn ghost">Browse All Help Center FAQs &rarr;</a>
            </div>
        </div>
    </section>
</x-storefront.layout>
