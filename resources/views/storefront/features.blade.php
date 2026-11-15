@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => route('store.features')],
    ];
@endphp

<x-storefront.layout 
    :title="'Platform Features & Technical Specifications'" 
    :description="'Explore STX Hosting platform capabilities: enterprise PCIe Gen4 NVMe storage, isolated Docker containers, automated SSL certificates, R2 volume backups, and PayVerify AI slip verification.'"
    :breadcrumbs="$breadcrumbs"
>
    <div class="wrap band">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span style="color:var(--ink);font-weight:600">Features</span>
        </nav>

        <div class="pagehead" data-reveal>
            <div>
                <span class="pill signal" style="margin-bottom:1rem">
                    <span class="pill-dot"></span> Sovereign Engineering &bull; Modern Stack
                </span>
                <h1>Platform Features &amp; Infrastructure Architecture</h1>
                <p class="lead">Engineered for high throughput, kernel-level tenant isolation, automated SSL, and complete operational sovereignty.</p>
            </div>
            <div>
                <a href="{{ route('store.pricing') }}" class="btn">View All Plans &rarr;</a>
            </div>
        </div>

        <div class="card-grid-3" style="margin-top: 3.5rem;">
            <!-- Feature 1 -->
            <div class="card card-lift" data-reveal>
                <div class="pill signal" style="margin-bottom:1rem">Storage Tier</div>
                <h3 style="font-size:1.35rem;margin-bottom:0.75rem">High-Throughput NVMe Storage</h3>
                <p style="color:var(--muted);line-height:1.6;font-size:0.94rem">All Cloud VPS and managed database workloads run on enterprise PCIe Gen4 NVMe arrays delivering over 500,000 IOPS and sub-millisecond access latency. Eliminates database bottlenecks during traffic spikes.</p>
                <ul style="margin:1.25rem 0 0;padding-left:1.25rem;font-size:0.88rem;color:var(--ink-light);line-height:1.8">
                    <li>Zero noisy-neighbor disk throttling</li>
                    <li>Hardware-level TRIM and SMART health monitoring</li>
                    <li>ECC memory protection against bit rot</li>
                </ul>
            </div>

            <!-- Feature 2 -->
            <div class="card card-lift" data-reveal data-delay="80">
                <div class="pill emerald" style="margin-bottom:1rem">Kernel Guard</div>
                <h3 style="font-size:1.35rem;margin-bottom:0.75rem">Linux cgroups v2 Quota Isolation</h3>
                <p style="color:var(--muted);line-height:1.6;font-size:0.94rem">Every container application and microservice is bounded by strict kernel-level cgroups (v2) memory and CPU limits. Run intensive production applications without risking host node stability.</p>
                <ul style="margin:1.25rem 0 0;padding-left:1.25rem;font-size:0.88rem;color:var(--ink-light);line-height:1.8">
                    <li>Strict memory limits with automated OOM alerts</li>
                    <li>Fair-share and dedicated vCPU thread pinning</li>
                    <li>Per-container network traffic accounting</li>
                </ul>
            </div>

            <!-- Feature 3 -->
            <div class="card card-lift" data-reveal data-delay="160">
                <div class="pill ink" style="margin-bottom:1rem">Edge Proxy</div>
                <h3 style="font-size:1.35rem;margin-bottom:0.75rem">Automated Let's Encrypt SSL</h3>
                <p style="color:var(--muted);line-height:1.6;font-size:0.94rem">High-performance edge reverse proxies automatically request, verify, and renew TLS certificates for your root apex domains, subdomains, and wildcard hostnames with modern TLS 1.3 encryption.</p>
                <ul style="margin:1.25rem 0 0;padding-left:1.25rem;font-size:0.88rem;color:var(--ink-light);line-height:1.8">
                    <li>Automatic ACME HTTP-01 and DNS-01 verification</li>
                    <li>Zero-downtime certificate renewal cycles</li>
                    <li>HSTS and security header enforcement</li>
                </ul>
            </div>

            <!-- Feature 4 -->
            <div class="card card-lift" data-reveal>
                <div class="pill signal" style="margin-bottom:1rem">Disaster Recovery</div>
                <h3 style="font-size:1.35rem;margin-bottom:0.75rem">Cloudflare R2 Encrypted Backups</h3>
                <p style="color:var(--muted);line-height:1.6;font-size:0.94rem">Automated database and container volume snapshots are compressed, encrypted, and stored redundantly offsite in Cloudflare R2 / S3 storage. Restore from any point in time with a single click in your portal.</p>
                <ul style="margin:1.25rem 0 0;padding-left:1.25rem;font-size:0.88rem;color:var(--ink-light);line-height:1.8">
                    <li>Automated daily and weekly snapshot cron jobs</li>
                    <li>Zero egress fees on Cloudflare R2 backup downloads</li>
                    <li>One-click database rollback to fresh staging targets</li>
                </ul>
            </div>

            <!-- Feature 5 -->
            <div class="card card-lift" data-reveal data-delay="80">
                <div class="pill amber" style="margin-bottom:1rem">AI Verification</div>
                <h3 style="font-size:1.35rem;margin-bottom:0.75rem">PayVerify AI Verification</h3>
                <p style="color:var(--muted);line-height:1.6;font-size:0.94rem">Our payment verification engine uses Gemini 2.5 Flash multimodal vision and local perceptual dHash models to parse bank transfer receipts, check perceptual duplicate hashes, and match bank ledger transactions in minutes.</p>
                <ul style="margin:1.25rem 0 0;padding-left:1.25rem;font-size:0.88rem;color:var(--ink-light);line-height:1.8">
                    <li>Pure-pixel re-encoding permanently purges webshells</li>
                    <li>In-memory zero-leak OTP buffering with 60s TTL</li>
                    <li>100% immune to credit card chargeback fraud</li>
                </ul>
            </div>

            <!-- Feature 6 -->
            <div class="card card-lift" data-reveal data-delay="160">
                <div class="pill emerald" style="margin-bottom:1rem">Accountability</div>
                <h3 style="font-size:1.35rem;margin-bottom:0.75rem">Public Telemetry &amp; 90-Day SLA</h3>
                <p style="color:var(--muted);line-height:1.6;font-size:0.94rem">We believe in total operational transparency. Our public status page tracks live TCP and HTTP probes across all platform clusters with an unedited 90-day uptime heatmap and automated email incident alerts.</p>
                <ul style="margin:1.25rem 0 0;padding-left:1.25rem;font-size:0.88rem;color:var(--ink-light);line-height:1.8">
                    <li>60-second probe intervals with 2-failure confirmations</li>
                    <li>Subscriber incident updates via email</li>
                    <li>Full historical downtime accountability</li>
                </ul>
            </div>
        </div>

        <div style="margin-top:4.5rem;text-align:center" data-reveal>
            <a href="{{ route('store.pricing') }}" class="btn">View Plans &amp; Start Deploying &rarr;</a>
        </div>
    </div>
</x-storefront.layout>
