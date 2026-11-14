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
    <div class="wrap">
        <nav aria-label="Breadcrumb" style="padding-top:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span>Email Hosting</span>
        </nav>

        <div class="pagehead">
            <div>
                <span style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--signal);font-weight:700">Enterprise Mail Infrastructure</span>
                <h1 style="margin-top:0.35rem">Business Email Hosting for Your Own Domain.</h1>
                <p>Run your corporate email on your custom domain. Includes modern pure Webmail, IMAP/SMTP for mobile and desktop, 2048-bit DKIM deliverability, and pooled team storage priced in {{ $currency }}.</p>
            </div>
        </div>

        {{-- 4 Plans Grid --}}
        <section class="group" style="margin-top:2rem">
            <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:0.5rem">Choose your storage tier</h2>
            <p class="blurb" style="margin-bottom:1.5rem">Storage is pooled across all your mailboxes. No daily send or receive quotas are enforced.</p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:1.5rem">
                @foreach ($plans as $p)
                    @php
                        $priceCents = $currency === 'LKR' ? ($p['price_lkr_cents'] ?? ($p['price_cents'] * 315)) : $p['price_cents'];
                        $formattedPrice = \App\Support\Money::format((int) $priceCents, $currency);
                        $storageLabel = $p['storage_mb'] >= 1024 ? round($p['storage_mb'] / 1024, 1).' GB' : $p['storage_mb'].' MB';
                    @endphp
                    <div style="background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between">
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:baseline">
                                <h3 style="font-size:1.25rem;font-weight:700;margin:0">{{ $p['name'] }}</h3>
                                <span style="font-size:1.1rem;font-weight:800;color:var(--signal)">{{ $formattedPrice }}<small style="font-size:0.75rem;font-weight:500;color:var(--muted)">/mo</small></span>
                            </div>
                            <p style="font-size:0.85rem;color:var(--muted);margin:0.25rem 0 1rem 0">{{ $storageLabel }} shared mailbox storage</p>

                            <ul style="list-style:none;padding:0;margin:1rem 0;font-size:0.875rem;line-height:1.8;color:var(--ink);border-top:1px solid var(--line);padding-top:1rem">
                                <li>✓ <strong>{{ $storageLabel }}</strong> pooled storage</li>
                                <li>✓ Up to <strong>{{ $p['max_mailboxes'] }}</strong> user mailboxes</li>
                                <li>✓ <strong>Unlimited</strong> daily send & receive</li>
                                <li>✓ <strong>Custom domain</strong> (bring your own)</li>
                                <li>✓ Modern pure <strong>Webmail UI</strong></li>
                                <li>✓ <strong>IMAP / SMTP</strong> with TLS/SSL</li>
                                <li>✓ Automated <strong>2048-bit DKIM</strong> &amp; SPF</li>
                            </ul>
                        </div>

                        <div style="margin-top:1.5rem;border-top:1px solid var(--line);padding-top:1rem">
                            <form action="{{ route('cart.add') }}" method="POST">
                                @csrf
                                <input type="hidden" name="variant" value="{{ $p['slug'] ?? 'mail-basic' }}">
                                <input type="hidden" name="billing_type" value="monthly">
                                <button type="submit" class="btn small" style="width:100%;text-align:center;display:block;cursor:pointer">
                                    Order {{ $p['name'] }} &rarr;
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Deliverability & Features Grid --}}
        <section class="band" style="margin-top:4rem;padding:3rem 0;border-top:1px solid var(--line)">
            <h2 style="font-size:1.5rem;margin-bottom:1.5rem">Why STX Email Beats Per-User Licensing</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1.5rem">
                <div style="background:var(--card);padding:1.5rem;border-radius:var(--radius);border:1px solid var(--line)">
                    <h3 style="font-size:1.15rem;margin-bottom:0.5rem">💰 Pooled Team Storage</h3>
                    <p style="font-size:0.9rem;color:var(--muted);margin:0;line-height:1.6">Never pay $6/user/month for inactive team members. A single flat pooled quota is shared across your whole domain.</p>
                </div>
                <div style="background:var(--card);padding:1.5rem;border-radius:var(--radius);border:1px solid var(--line)">
                    <h3 style="font-size:1.15rem;margin-bottom:0.5rem">🛡️ Enterprise Anti-Spam &amp; DKIM</h3>
                    <p style="font-size:0.9rem;color:var(--muted);margin:0;line-height:1.6">Automated 2048-bit cryptographic DKIM keys and SPF verification guarantee maximum deliverability into Gmail, Outlook, and Apple Mail.</p>
                </div>
                <div style="background:var(--card);padding:1.5rem;border-radius:var(--radius);border:1px solid var(--line)">
                    <h3 style="font-size:1.15rem;margin-bottom:0.5rem">📱 Multi-Device IMAP / SMTP</h3>
                    <p style="font-size:0.9rem;color:var(--muted);margin:0;line-height:1.6">Sync seamlessly with iPhone Mail, Android Gmail app, Thunderbird, or Outlook with SSL encryption on ports 993 and 587.</p>
                </div>
            </div>
        </section>
    </div>
</x-storefront.layout>
