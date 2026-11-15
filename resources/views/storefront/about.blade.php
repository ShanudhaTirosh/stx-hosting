@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'About', 'url' => route('store.about')],
    ];
@endphp

<x-storefront.layout 
    title="About STX Enterprises & Cloud Platform" 
    description="Who runs STX Hosting, our infrastructure principles, Sri Lanka corporate identity, and our transparent 24 to 72 hour manual delivery model."
    :breadcrumbs="$breadcrumbs"
>
    <div class="wrap-narrow band">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span style="color:var(--ink);font-weight:600">About</span>
        </nav>
        
        <div class="pagehead" data-reveal>
            <div>
                <span class="pill signal" style="margin-bottom:1rem">
                    <span class="pill-dot"></span> Corporate Identity &bull; Colombo, Sri Lanka
                </span>
                <h1>About {{ config('storefront.name', 'STX Hosting') }}</h1>
                <p class="lead">Enterprise cloud hosting and managed database services from {{ config('storefront.parent_brand', 'STX Enterprises') }}, built purposefully for teams who pay by bank transfer.</p>
            </div>
        </div>

        <div class="prose" style="margin-top:2.5rem" data-reveal>
            <p>STX Hosting is a sovereign cloud PaaS, managed database, and business email infrastructure platform. We provide dedicated NVMe Cloud VPS instances, 1-click Docker application stacks, and custom domain mailboxes. All plans are priced honestly in <strong>Sri Lankan Rupees (LKR)</strong> and <strong>US Dollars (USD)</strong> with direct local bank transfer payment support.</p>

            <h2>Engineering &amp; Security Principles</h2>
            <div class="card card-lift" style="margin: 1.5rem 0;">
                <ul style="margin:0;padding-left:1.25rem;line-height:1.8;color:var(--ink-light)">
                    <li><strong>Pure Isolation</strong>: Every customer workload runs in kernel-isolated Docker containers or dedicated NVMe virtual machines. No shared Apache runtimes, and zero noisy neighbors.</li>
                    <li><strong>Pure-Pixel Slip Sanitization</strong>: Payment receipts uploaded by customers are decoded and re-encoded into pure image pixels, permanently purging webshells or malicious polyglot attachments.</li>
                    <li><strong>Zero-Leak In-Memory OTPs</strong>: Banking SMS OTP verification codes are buffered transiently in server memory with a strict 60-second TTL, never touched to persistent databases or logs.</li>
                    <li><strong>Zero Lock-In</strong>: Built on open-source standards — pure Docker Compose v2, standard Linux environments, and full offsite R2/S3 automated snapshots.</li>
                </ul>
            </div>

            <h2>What You Control in Your Client Portal</h2>
            <ul>
                <li><strong>Application Deployments</strong>: Connect your GitHub repository, configure environment variables, inspect live build logs, and roll back versions instantly.</li>
                <li><strong>Custom Domains &amp; SSL</strong>: Automated HTTP/2 and TLS 1.3 Let's Encrypt certificates provisioned on request.</li>
                <li><strong>Database Backups</strong>: Automated daily and weekly scheduled backups with one-click restore.</li>
                <li><strong>Interactive VPS Tools</strong>: In-browser web terminal, SFTP file manager, and live CPU/RAM usage graphs.</li>
            </ul>

            @if (! empty($claims))
                <h2>Platform at a Glance</h2>
                <div class="card" style="margin: 1.5rem 0; padding: 1.5rem;">
                    <dl class="facts">
                        @foreach ($claims as $label => $value)
                            <dt style="color:var(--ink)">{{ $label }}</dt>
                            <dd style="color:var(--muted);font-weight:600">{{ $value }}</dd>
                        @endforeach
                    </dl>
                </div>
            @endif

            <div style="margin-top:3rem">
                <a class="btn" href="{{ route('store.pricing') }}">Browse Server Catalog &rarr;</a>
            </div>
        </div>
    </div>
</x-storefront.layout>
