@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Email Hosting', 'url' => route('store.email-hosting')],
    ];

    $planSchemas = [];
    foreach ($plans as $p) {
        $priceCents = $currency === 'LKR' ? ($p['price_lkr_cents'] ?? ($p['price_cents'] * 315)) : $p['price_cents'];
        $storageLabel = $p['storage_mb'] >= 1024 ? round($p['storage_mb'] / 1024, 1).' GB' : $p['storage_mb'].' MB';
        $planSchemas[] = [
            'name' => $p['name'] . ' Business Email Hosting',
            'description' => $storageLabel . ' shared storage, up to ' . $p['max_mailboxes'] . ' user mailboxes, custom domain, DKIM, and pure Webmail.',
            'price' => (string) ($priceCents / 100),
            'currency' => $currency,
        ];
    }
@endphp

<x-storefront.layout 
    title="Business Email Hosting for Custom Domains" 
    description="Business email hosting on your custom domain with modern pure Webmail, IMAP/SMTP phone sync, automated 2048-bit DKIM, SPF, DMARC, and pooled storage."
    :breadcrumbs="$breadcrumbs"
    :plans="$planSchemas"
>
    <div class="wrap band">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span style="color:var(--ink);font-weight:600">Email Hosting</span>
        </nav>

        <div class="pagehead" data-reveal>
            <div>
                <span class="pill signal" style="margin-bottom:1rem">
                    <span class="pill-dot"></span> Flat Domain Pricing &bull; No Per-User Seat Tax
                </span>
                <h1>Business Email Hosting on Your Custom Domain</h1>
                <p class="lead">Run corporate mailboxes on your company domain with lightweight STX Webmail, full IMAP/SMTP client sync, 2048-bit DKIM deliverability, and pooled team storage priced honestly in {{ $currency }}.</p>
            </div>
            <div>
                <div class="pill emerald">
                    <span class="pill-dot"></span> 100% DKIM &bull; SPF &bull; DMARC Verified
                </div>
            </div>
        </div>

        <!-- 4 Plans Grid -->
        <section style="margin: 3.5rem 0;" data-reveal>
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:1.5rem">
                <h2>Choose Your Storage Tier</h2>
                <span style="font-size:0.9rem;color:var(--muted)">Storage is shared across all mailboxes on your domain</span>
            </div>

            <div class="card-grid-4">
                @foreach ($plans as $p)
                    @php
                        $priceCents = $currency === 'LKR' ? ($p['price_lkr_cents'] ?? ($p['price_cents'] * 315)) : $p['price_cents'];
                        $formattedPrice = \App\Support\Money::format((int) $priceCents, $currency);
                        $storageLabel = $p['storage_mb'] >= 1024 ? round($p['storage_mb'] / 1024, 1).' GB' : $p['storage_mb'].' MB';
                        $isFeatured = strtolower($p['name']) === 'pro' || strtolower($p['name']) === 'basic';
                    @endphp
                    <div class="plan-card {{ $isFeatured ? 'featured' : '' }} card-lift">
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:baseline">
                                <h3 class="plan-title">{{ $p['name'] }}</h3>
                            </div>
                            <p class="plan-desc">{{ $storageLabel }} shared mailbox storage for up to {{ $p['max_mailboxes'] }} accounts</p>

                            <div class="plan-price">
                                <span class="plan-price-num">{{ $formattedPrice }}</span>
                                <span class="plan-price-term">/month</span>
                            </div>

                            <ul class="plan-specs">
                                <li><span>Pooled Storage</span><strong>{{ $storageLabel }}</strong></li>
                                <li><span>Max Mailboxes</span><strong>{{ $p['max_mailboxes'] }} Users</strong></li>
                                <li><span>Daily Sends</span><strong>Unlimited</strong></li>
                                <li><span>Webmail UI</span><strong>Included</strong></li>
                                <li><span>IMAP / SMTP</span><strong>SSL / TLS</strong></li>
                                <li><span>DKIM Key</span><strong>2048-bit RSA</strong></li>
                            </ul>
                        </div>

                        <div class="plan-cta">
                            <form action="{{ route('cart.add') }}" method="POST">
                                @csrf
                                <input type="hidden" name="variant" value="{{ $p['slug'] ?? 'mail-basic' }}">
                                <input type="hidden" name="billing_type" value="monthly">
                                <button type="submit" class="btn small full {{ $isFeatured ? 'signal' : 'ghost' }}">
                                    Order {{ $p['name'] }} &rarr;
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- Why STX Email Beats Per-User Licensing -->
        <section class="band soft" style="margin-top:4rem" data-reveal>
            <div class="wrap">
                <div class="band-header">
                    <h2>Why Engineering Teams &amp; Agencies Switch to STX Mail</h2>
                    <p>Designed around domain-level resource pooling rather than expensive per-user seat subscriptions.</p>
                </div>

                <div class="card-grid-3">
                    <div class="card card-lift">
                        <div class="pill emerald" style="margin-bottom:1rem">Pooled Quota</div>
                        <h3 style="font-size:1.25rem;margin-bottom:0.5rem">No Per-User Seat Tax</h3>
                        <p style="margin:0;font-size:0.92rem;color:var(--muted)">Never pay $6–$12 per user every month for staff members who only read a few emails. One pooled storage tier covers your entire team.</p>
                    </div>

                    <div class="card card-lift">
                        <div class="pill signal" style="margin-bottom:1rem">Deliverability</div>
                        <h3 style="font-size:1.25rem;margin-bottom:0.5rem">2048-bit Cryptographic DKIM</h3>
                        <p style="margin:0;font-size:0.92rem;color:var(--muted)">Automated DNS validation with strict SPF, 2048-bit DKIM keys, and DMARC policies ensures your emails land straight in inboxes, not spam folders.</p>
                    </div>

                    <div class="card card-lift">
                        <div class="pill ink" style="margin-bottom:1rem">Cross-Platform</div>
                        <h3 style="font-size:1.25rem;margin-bottom:0.5rem">Standard IMAP &amp; SMTP</h3>
                        <p style="margin:0;font-size:0.92rem;color:var(--muted)">Native phone synchronization with Apple Mail, Android Gmail, Outlook, or Thunderbird over encrypted SSL ports (993 / 587).</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-storefront.layout>
