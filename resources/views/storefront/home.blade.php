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
            <div style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.35rem 0.75rem;background:var(--ok-bg);color:var(--ok-ink);border-radius:100px;font-size:0.875rem;font-weight:600;margin-bottom:1.5rem">
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981"></span>
                Direct Bank Transfer &bull; LKR &amp; USD Dual Pricing &bull; No Credit Card Required
            </div>

            <h1>Enterprise Cloud Hosting You Can Pay for with a Bank Transfer.</h1>
            <p class="lede">High-performance NVMe Cloud VPS, Git-driven container app hosting, managed databases, and custom domain email. Priced honestly in Sri Lankan Rupees or US Dollars. You order, pay by bank transfer, PayVerify AI verifies your slip, and your service goes live.</p>

            <div class="actions">
                <a class="btn" href="{{ route('store.pricing') }}">View All Plans &amp; Pricing</a>
                <a class="btn ghost" href="{{ route('store.how-it-works') }}">How Ordering Works</a>
            </div>

            @if ($status['total'] > 0)
                <div style="margin-top:2rem;display:inline-flex;align-items:center;gap:0.75rem;padding:0.5rem 1rem;background:var(--card);border:1px solid var(--line);border-radius:var(--radius);font-size:0.875rem">
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $status['down'] === 0 ? '#10b981' : '#f59e0b' }}"></span>
                    <span>
                        @if ($status['down'] === 0)
                            All <strong>{{ $status['total'] }}</strong> platform monitors are 100% operational.
                        @else
                            {{ $status['down'] }} of {{ $status['total'] }} monitors have an open incident.
                        @endif
                        <a href="{{ route('status.public') }}" target="_blank" rel="noopener" style="margin-left:0.5rem;font-weight:600">View Live 90-Day Telemetry &rarr;</a>
                    </span>
                </div>
            @endif

            @if ($index->isNotEmpty())
                <ul class="index" aria-label="Plans by Category" style="margin-top:2.5rem">
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

    <!-- Core Pillars / Answer-First Capabilities -->
    <section class="band" style="background:var(--card);border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:4rem 0">
        <div class="wrap">
            <h2 style="font-size:2rem;margin-bottom:0.75rem">Why Developers &amp; Businesses Choose STX Hosting</h2>
            <p style="color:var(--muted);font-size:1.1rem;margin-bottom:2.5rem">Built from the ground up for technical reliability, financial transparency, and ease of payment.</p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1.5rem">
                <div style="background:var(--paper);padding:1.75rem;border-radius:var(--radius);border:1px solid var(--line)">
                    <div style="font-size:1.5rem;margin-bottom:0.75rem">🚀</div>
                    <h3 style="font-size:1.25rem;margin-bottom:0.5rem">High-Performance NVMe VPS</h3>
                    <p style="margin:0;font-size:0.95rem;color:var(--muted)">Dedicated vCPUs with ultra-fast NVMe storage. Hand-allocated with root SSH credentials delivered within <strong>24 to 72 hours</strong> of payment approval.</p>
                    <a href="{{ route('store.vps') }}" style="display:inline-block;margin-top:1rem;font-weight:600">Explore VPS Plans &rarr;</a>
                </div>

                <div style="background:var(--paper);padding:1.75rem;border-radius:var(--radius);border:1px solid var(--line)">
                    <div style="font-size:1.5rem;margin-bottom:0.75rem">📦</div>
                    <h3 style="font-size:1.25rem;margin-bottom:0.5rem">31+ 1-Click App Templates</h3>
                    <p style="margin:0;font-size:0.95rem;color:var(--muted)">Deploy Next.js, WordPress, Ghost, Directus, n8n, Supabase, and Laravel in seconds. Automatic Let's Encrypt SSL certificates and zero-downtime Git redeployments.</p>
                    <a href="{{ route('store.templates') }}" style="display:inline-block;margin-top:1rem;font-weight:600">Browse Curated Templates &rarr;</a>
                </div>

                <div style="background:var(--paper);padding:1.75rem;border-radius:var(--radius);border:1px solid var(--line)">
                    <div style="font-size:1.5rem;margin-bottom:0.75rem">✉️</div>
                    <h3 style="font-size:1.25rem;margin-bottom:0.5rem">Private Business Email Hosting</h3>
                    <p style="margin:0;font-size:0.95rem;color:var(--muted)">Run corporate email on your domain with modern STX Webmail, pooled team storage, and automatic SPF, DKIM (2048-bit), and DMARC deliverability protection.</p>
                    <a href="{{ route('store.email-hosting') }}" style="display:inline-block;margin-top:1rem;font-weight:600">See Email Plans &rarr;</a>
                </div>

                <div style="background:var(--paper);padding:1.75rem;border-radius:var(--radius);border:1px solid var(--line)">
                    <div style="font-size:1.5rem;margin-bottom:0.75rem">🛡️</div>
                    <h3 style="font-size:1.25rem;margin-bottom:0.5rem">PayVerify AI Verification</h3>
                    <p style="margin:0;font-size:0.95rem;color:var(--muted)">No credit card required. Upload your bank transfer slip, and PayVerify AI extracts transaction amounts and corroborates with our bank ledger for rapid approval.</p>
                    <a href="{{ route('store.how-it-works') }}" style="display:inline-block;margin-top:1rem;font-weight:600">Learn How It Works &rarr;</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Comparison Table: STX vs Legacy cPanel vs Hyperscalers -->
    <section class="band" style="padding:4rem 0">
        <div class="wrap">
            <h2 style="font-size:2rem;margin-bottom:0.75rem">Platform Comparison</h2>
            <p style="color:var(--muted);font-size:1.1rem;margin-bottom:2rem">How STX Hosting stacks up against legacy shared hosting and complex hyperscalers.</p>

            <table class="ledger">
                <thead>
                    <tr>
                        <th scope="col">Feature</th>
                        <th scope="col">Legacy cPanel Shared Hosting</th>
                        <th scope="col" style="color:var(--signal);font-weight:700">STX Cloud Platform</th>
                        <th scope="col">Global Hyperscalers (AWS/Azure)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Payment Methods</strong></td>
                        <td>Credit card / USD only</td>
                        <td style="background:var(--ok-bg);font-weight:600">Bank Transfer in LKR &amp; USD (Zero Card Fees)</td>
                        <td>International Credit Card only (FX Cap risks)</td>
                    </tr>
                    <tr>
                        <td><strong>Runtime Architecture</strong></td>
                        <td>Shared Apache; noisy neighbors slow you down</td>
                        <td style="background:var(--ok-bg);font-weight:600">Isolated Docker containers &amp; dedicated NVMe VPS</td>
                        <td>Complex IAM, VPCs, and serverless limits</td>
                    </tr>
                    <tr>
                        <td><strong>Pricing Predictability</strong></td>
                        <td>Hidden renewal price increases (2x-3x)</td>
                        <td style="background:var(--ok-bg);font-weight:600">100% Flat Predictable Invoices; No Surprises</td>
                        <td>Uncapped bandwidth and execution second bills</td>
                    </tr>
                    <tr>
                        <td><strong>Deploy Workflow</strong></td>
                        <td>Manual FTP / Zip upload</td>
                        <td style="background:var(--ok-bg);font-weight:600">Automated Git push webhooks &amp; 1-click templates</td>
                        <td>Complex CI/CD pipelines &amp; CloudFormation</td>
                    </tr>
                    <tr>
                        <td><strong>Delivery SLA</strong></td>
                        <td>Instant but unmonitored</td>
                        <td style="background:var(--ok-bg);font-weight:600">Instant apps; 24–72h hand-hardened VPS</td>
                        <td>Instant VM spinning</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Visual How It Works Band -->
    <section class="band ink" id="how" style="padding:4rem 0">
        <div class="wrap">
            <h2 style="font-size:2rem;margin-bottom:0.75rem">How Ordering Works (4 Simple Steps)</h2>
            <p style="color:#cbd5e1;font-size:1.1rem;margin-bottom:2.5rem">Transparent, accountable, and chargeback-free.</p>

            <ol class="steps">
                <li>
                    <strong>1. Select Your Plan</strong>
                    <p>Choose your Cloud VPS specs, container plan, or email tier. Prices displayed in LKR or USD are exact and final.</p>
                </li>
                <li>
                    <strong>2. Bank Transfer</strong>
                    <p>Checkout creates your invoice with bank account details. Transfer funds via your local mobile banking app or branch slip.</p>
                </li>
                <li>
                    <strong>3. Upload Slip</strong>
                    <p>Upload a photo or PDF of your receipt. PayVerify AI and our human review team inspect and verify your payment.</p>
                </li>
                <li>
                    <strong>4. Service Activated</strong>
                    <p>App containers and email start immediately upon approval. Cloud VPS servers are hand-delivered within 24 to 72 hours.</p>
                </li>
            </ol>
        </div>
    </section>

    <!-- FAQs Section -->
    <section class="band" style="background:var(--card);border-top:1px solid var(--line);padding:4rem 0">
        <div class="wrap">
            <h2 style="font-size:2rem;margin-bottom:0.75rem">Frequently Asked Questions</h2>
            <p style="color:var(--muted);font-size:1.1rem;margin-bottom:2rem">Direct answers to common questions about our platform and payment flows.</p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:1.5rem">
                @foreach ($faqs as $faq)
                    <div style="background:var(--paper);padding:1.5rem;border-radius:var(--radius);border:1px solid var(--line)">
                        <h3 style="font-size:1.1rem;margin-bottom:0.5rem">{{ $faq['question'] }}</h3>
                        <p style="margin:0;font-size:0.95rem;color:var(--muted);line-height:1.6">{{ $faq['answer'] }}</p>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:2.5rem;text-align:center">
                <a href="{{ route('store.faqs') }}" class="btn ghost">Read Full Help Center FAQs &rarr;</a>
            </div>
        </div>
    </section>
</x-storefront.layout>
