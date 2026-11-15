@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Templates', 'url' => route('store.templates')],
    ];
@endphp

<x-storefront.layout 
    :title="'Curated 1-Click Docker Application Templates'" 
    :description="'Deploy 31+ curated production Docker application templates with automated SSL, isolated container networks, and zero configuration.'"
    :breadcrumbs="$breadcrumbs"
>
    <div class="wrap band">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span style="color:var(--ink);font-weight:600">Templates</span>
        </nav>

        <div class="pagehead" data-reveal>
            <div>
                <span class="pill signal" style="margin-bottom:1rem">
                    <span class="pill-dot"></span> 31 Active Production Stacks
                </span>
                <h1>Curated 1-Click Application Templates</h1>
                <p class="lead">Launch hardened open-source applications, headless backends, and databases on isolated Docker containers with automated Let's Encrypt SSL certificates.</p>
            </div>
            <div>
                <a href="{{ route('store.app-hosting') }}" class="btn">Deploy Custom Container</a>
            </div>
        </div>

        @if (!empty($featured))
            <div style="margin: 3.5rem 0 2rem;" data-reveal>
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:1.5rem">
                    <h2>Featured Applications</h2>
                    <span style="font-size:0.9rem;color:var(--muted)">Automated Traefik SSL &bull; High Reliability</span>
                </div>

                <div class="card-grid-3">
                    @foreach ($featured as $tpl)
                        <div class="card card-lift" style="display:flex;flex-direction:column;justify-content:space-between">
                            <div>
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                                    <span class="pill ink">{{ $tpl['category'] }}</span>
                                    <span class="pill emerald"><span class="pill-dot"></span> Ready</span>
                                </div>
                                <h3 style="font-size:1.35rem;margin-bottom:.5rem">{{ $tpl['name'] }}</h3>
                                <p style="font-size:0.92rem;line-height:1.55;color:var(--muted)">{{ $tpl['desc'] }}</p>
                                
                                <div style="display:flex;gap:1rem;margin-top:1rem;font-size:0.82rem;color:var(--muted)">
                                    <span>RAM: <strong>{{ $tpl['min_ram_mb'] }} MB</strong></span>
                                    <span>vCPU: <strong>{{ $tpl['min_vcpu'] }} Core</strong></span>
                                </div>
                            </div>
                            <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--line-subtle)">
                                <a href="{{ route('store.app-hosting') }}" class="btn ghost small full">Deploy {{ $tpl['name'] }} &rarr;</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if (!empty($templates))
            <div style="margin-top: 4.5rem;" data-reveal>
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:1.5rem">
                    <h2>All Available Templates ({{ count($templates) }})</h2>
                    <span style="font-size:0.9rem;color:var(--muted)">Verified Docker Compose v2 Stacks</span>
                </div>

                <div class="card" style="padding: 2rem;">
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:1rem">
                        @foreach ($templates as $t)
                            <a href="{{ route('store.app-hosting') }}" class="card-lift" style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;background:var(--paper-soft);border:1px solid var(--line);border-radius:var(--radius-sm);color:var(--ink);text-decoration:none;font-weight:600;font-size:.92rem">
                                <span>{{ $t['name'] }}</span>
                                <span style="font-size:.75rem;color:var(--muted);background:var(--card);padding:.15rem .45rem;border-radius:var(--radius-full);border:1px solid var(--line)">{{ $t['category'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Architecture Guarantee Callout -->
        <div class="card" style="margin-top:4rem;background:#071726;border-color:#1c364e;color:#e2ecf5;padding:2.5rem" data-reveal>
            <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:2.5rem;align-items:center">
                <div>
                    <span class="pill signal" style="margin-bottom:1rem">
                        <span class="pill-dot"></span> Zero-Vendor-Lockin Standard
                    </span>
                    <h3 style="color:#fff;font-size:1.8rem;margin-bottom:.75rem">Production Stacks Built on Standard Docker Compose</h3>
                    <p style="color:#9bb0c0;margin:0">Every template in our catalog runs on pure Docker Compose v2 definitions. Persistent data volumes are stored on NVMe drives with automated daily snapshots to S3/Cloudflare R2.</p>
                </div>
                <div style="text-align:right">
                    <a href="{{ route('store.pricing') }}" class="btn signal">Browse Application Plans</a>
                </div>
            </div>
        </div>
    </div>
</x-storefront.layout>
