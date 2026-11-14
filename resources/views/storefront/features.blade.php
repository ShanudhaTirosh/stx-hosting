@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => route('store.features')],
    ];
@endphp

<x-storefront.layout 
    :title="'Platform Features & Technical Specifications'" 
    :description="'Explore STX Hosting platform capabilities: high-speed NVMe storage, isolated Docker containers, automated SSL certificates, R2 volume backups, and PayVerify AI slip verification.'"
    :breadcrumbs="$breadcrumbs"
>
    <div class="wrap" style="padding-top:2.5rem;padding-bottom:4rem">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span>Features</span>
        </nav>

        <div class="pagehead" style="margin-bottom:3rem">
            <h1>Platform Features &amp; Infrastructure Architecture</h1>
            <p style="font-size:1.15rem;color:var(--muted);max-width:75ch">Engineered for high performance, tenant isolation, automated SSL, and complete operational sovereignty.</p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:2rem">
            <!-- Feature 1 -->
            <div style="background:var(--card);padding:2rem;border-radius:var(--radius);border:1px solid var(--line)">
                <div style="font-size:2rem;margin-bottom:1rem">⚡</div>
                <h2 style="font-size:1.4rem;margin-bottom:0.75rem">High-Throughput NVMe Storage</h2>
                <p style="color:var(--muted);line-height:1.6">All Cloud VPS and managed database workloads run on enterprise PCIe Gen4 NVMe arrays delivering over 500,000 IOPS and sub-millisecond access latency. Eliminates database bottlenecks during peak traffic.</p>
                <ul style="margin:1rem 0 0;padding-left:1.25rem;font-size:0.95rem;color:var(--muted);line-height:1.8">
                    <li>Zero noisy-neighbor disk throttling</li>
                    <li>Hardware-level TRIM and health monitoring</li>
                    <li>ECC memory protection against bit rot</li>
                </ul>
            </div>

            <!-- Feature 2 -->
            <div style="background:var(--card);padding:2rem;border-radius:var(--radius);border:1px solid var(--line)">
                <div style="font-size:2rem;margin-bottom:1rem">🔒</div>
                <h2 style="font-size:1.4rem;margin-bottom:0.75rem">Linux cgroups Quota Isolation</h2>
                <p style="color:var(--muted);line-height:1.6">Every container application and microservice is bounded by strict kernel-level cgroups (v2) memory and CPU limits. Run intensive production applications without risking host node stability.</p>
                <ul style="margin:1rem 0 0;padding-left:1.25rem;font-size:0.95rem;color:var(--muted);line-height:1.8">
                    <li>Strict memory limits with automated OOM alerts</li>
                    <li>Fair-share and dedicated vCPU thread pinning</li>
                    <li>Per-container network traffic accounting</li>
                </ul>
            </div>

            <!-- Feature 3 -->
            <div style="background:var(--card);padding:2rem;border-radius:var(--radius);border:1px solid var(--line)">
                <div style="font-size:2rem;margin-bottom:1rem">🌐</div>
                <h2 style="font-size:1.4rem;margin-bottom:0.75rem">Automated Let's Encrypt SSL</h2>
                <p style="color:var(--muted);line-height:1.6">Traefik edge proxies automatically request, verify, and renew TLS certificates for your root apex domains, subdomains, and wildcard hostnames. Full HTTP/2 and modern TLS 1.3 encryption enabled by default.</p>
                <ul style="margin:1rem 0 0;padding-left:1.25rem;font-size:0.95rem;color:var(--muted);line-height:1.8">
                    <li>Automatic ACME HTTP-01 and DNS-01 verification</li>
                    <li>Zero-downtime certificate renewal cycles</li>
                    <li>HSTS and security header enforcement</li>
                </ul>
            </div>

            <!-- Feature 4 -->
            <div style="background:var(--card);padding:2rem;border-radius:var(--radius);border:1px solid var(--line)">
                <div style="font-size:2rem;margin-bottom:1rem">🗄️</div>
                <h2 style="font-size:1.4rem;margin-bottom:0.75rem">Cloudflare R2 Encrypted Backups</h2>
                <p style="color:var(--muted);line-height:1.6">Automated database and container volume snapshots are compressed, encrypted, and stored redundantly offsite in Cloudflare R2 / AWS S3 buckets. Restore from any point in time with a single click in your client portal.</p>
                <ul style="margin:1rem 0 0;padding-left:1.25rem;font-size:0.95rem;color:var(--muted);line-height:1.8">
                    <li>Automated daily and weekly snapshot cron jobs</li>
                    <li>Zero egress fees on Cloudflare R2 backup downloads</li>
                    <li>One-click database rollback to fresh staging targets</li>
                </ul>
            </div>

            <!-- Feature 5 -->
            <div style="background:var(--card);padding:2rem;border-radius:var(--radius);border:1px solid var(--line)">
                <div style="font-size:2rem;margin-bottom:1rem">🤖</div>
                <h2 style="font-size:1.4rem;margin-bottom:0.75rem">PayVerify AI Payment Verification</h2>
                <p style="color:var(--muted);line-height:1.6">Our proprietary payment verification engine combines Google Gemini 1.5 Flash multimodal vision and local CPU models to parse bank transfer receipts, check perceptual duplicate hashes, and match bank ledger transactions in minutes.</p>
                <ul style="margin:1rem 0 0;padding-left:1.25rem;font-size:0.95rem;color:var(--muted);line-height:1.8">
                    <li>Pure-pixel re-encoding permanently purges webshells</li>
                    <li>In-memory zero-leak OTP buffering with 60s TTL</li>
                    <li>100% immune to credit card chargeback fraud</li>
                </ul>
            </div>

            <!-- Feature 6 -->
            <div style="background:var(--card);padding:2rem;border-radius:var(--radius);border:1px solid var(--line)">
                <div style="font-size:2rem;margin-bottom:1rem">📊</div>
                <h2 style="font-size:1.4rem;margin-bottom:0.75rem">Public Telemetry &amp; 90-Day Uptime</h2>
                <p style="color:var(--muted);line-height:1.6">We believe in total transparency. Our public status page tracks live TCP and HTTP probes across all platform clusters with an unedited 90-day uptime heatmap and automated email incident notifications.</p>
                <ul style="margin:1rem 0 0;padding-left:1.25rem;font-size:0.95rem;color:var(--muted);line-height:1.8">
                    <li>60-second probe intervals with 2-failure confirmations</li>
                    <li>Subscriber incident updates via email</li>
                    <li>Full historical downtime accountability</li>
                </ul>
            </div>
        </div>

        <div style="margin-top:3.5rem;text-align:center">
            <a href="{{ route('store.pricing') }}" class="btn">View Plans &amp; Start Deploying &rarr;</a>
        </div>
    </div>
</x-storefront.layout>
