<x-storefront.layout title="Email Hosting | STX Hosting" description="Sellable email hosting with custom domains, pure webmail, IMAP/SMTP access, and shared storage pools.">
    <div class="wrap">
        <div class="pagehead">
            <div>
                <span style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;color:#a391ff;font-weight:700">Dedicated Mail Infrastructure</span>
                <h1 style="margin-top:0.35rem">Email hosting for your own domain.</h1>
                <p>Run your business email with your custom domain. Includes pure Webmail, modern IMAP/SMTP for phone and desktop, 2048-bit DKIM deliverability, and pooled storage.</p>
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
                    <div style="background:var(--ui-well, #141414);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between">
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:baseline">
                                <h3 style="font-size:1.25rem;font-weight:700;margin:0">{{ $p['name'] }}</h3>
                                <span style="font-size:1.1rem;font-weight:800;color:#a391ff">{{ $formattedPrice }}<small style="font-size:0.75rem;font-weight:500;color:#888">/mo</small></span>
                            </div>
                            <p style="font-size:0.85rem;color:#aaa;margin:0.25rem 0 1rem 0">{{ $storageLabel }} shared mailbox storage</p>

                            <ul style="list-style:none;padding:0;margin:1rem 0;font-size:0.85rem;line-height:1.8;color:#ddd;border-top:1px solid rgba(255,255,255,0.06);padding-top:1rem">
                                <li>✓ <strong>{{ $storageLabel }}</strong> pooled storage</li>
                                <li>✓ Up to <strong>{{ $p['max_mailboxes'] }}</strong> user mailboxes</li>
                                <li>✓ <strong>Unlimited</strong> daily send & receive</li>
                                <li>✓ <strong>Custom domain</strong> (bring your own)</li>
                                <li>✓ Modern pure <strong>Webmail UI</strong></li>
                                <li>✓ <strong>IMAP / SMTP</strong> with TLS/SSL</li>
                                <li>✓ Automated <strong>2048-bit DKIM</strong> & SPF</li>
                            </ul>
                        </div>

                        <div style="margin-top:1.5rem;border-top:1px solid rgba(255,255,255,0.06);padding-top:1rem">
                            <a class="btn" href="{{ route('portal.mail') }}" style="width:100%;text-align:center;display:block">
                                Get Started
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- How it works section --}}
        <section class="band ink" style="margin-top:3.5rem;border-radius:14px;padding:2.5rem">
            <h2 style="font-size:1.35rem;font-weight:700;margin-top:0">How onboarding works</h2>
            <ol class="steps" style="margin-top:1.5rem">
                <li>
                    <strong>1. Choose your plan</strong>
                    <p>Pick the storage tier that fits your team. You can expand storage or mailboxes anytime.</p>
                </li>
                <li>
                    <strong>2. Enter your domain</strong>
                    <p>Type the domain name you want for your email (e.g. <code>mycompany.com</code>).</p>
                </li>
                <li>
                    <strong>3. Add DNS records</strong>
                    <p>We provide the exact MX, SPF, DKIM, and DMARC records with copy buttons and live verification.</p>
                </li>
                <li>
                    <strong>4. Create mailboxes</strong>
                    <p>Set up names and passwords, then sign into Webmail or connect Apple Mail, Outlook, or Thunderbird.</p>
                </li>
            </ol>
        </section>

        {{-- Add-Ons Catalog --}}
        @if (count($addons) > 0)
            <section class="group" style="margin-top:3.5rem">
                <h2 style="font-size:1.35rem;font-weight:700">Expansion Add-Ons</h2>
                <p class="blurb">Upgrade your plan whenever you need more capacity. Add-ons are billed with your subscription.</p>

                <table class="ledger" style="margin-top:1rem">
                    <thead>
                        <tr>
                            <th scope="col">Add-On</th>
                            <th scope="col">Category</th>
                            <th scope="col">Monthly Price</th>
                            <th scope="col"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($addons as $addon)
                            @php
                                $addonPrice = $currency === 'LKR' ? ($addon['price_cents'] * 315) : $addon['price_cents'];
                            @endphp
                            <tr>
                                <td class="plan">
                                    <strong>{{ $addon['name'] }}</strong>
                                </td>
                                <td>
                                    <span style="text-transform:capitalize;font-size:0.85rem;color:#aaa">{{ $addon['type'] }}</span>
                                </td>
                                <td class="price">
                                    <span>{{ \App\Support\Money::format((int) $addonPrice, $currency) }}<small>per month</small></span>
                                </td>
                                <td>
                                    <a class="btn small ghost" href="{{ route('portal.mail') }}">Add in Portal</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        {{-- Connection Details & Protocols --}}
        <section class="group" style="margin-top:3.5rem;margin-bottom:3rem">
            <h2 style="font-size:1.35rem;font-weight:700">Client Connection Details</h2>
            <p class="blurb">Standard open protocols compatible with every desktop and mobile mail app.</p>

            <table class="ledger" style="margin-top:1rem">
                <thead>
                    <tr>
                        <th scope="col">Protocol</th>
                        <th scope="col">Host</th>
                        <th scope="col">Port</th>
                        <th scope="col">Security / Encryption</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>IMAP</strong> (Incoming)</td>
                        <td><code>mail.{{ env('PLATFORM_ROOT_DOMAIN', 'stxapp.io') }}</code></td>
                        <td>993</td>
                        <td>SSL / TLS</td>
                    </tr>
                    <tr>
                        <td><strong>SMTP</strong> (Submission)</td>
                        <td><code>mail.{{ env('PLATFORM_ROOT_DOMAIN', 'stxapp.io') }}</code></td>
                        <td>587 (or 465)</td>
                        <td>STARTTLS / SSL</td>
                    </tr>
                    <tr>
                        <td><strong>Webmail</strong> (Browser)</td>
                        <td><code>webmail.{{ env('PLATFORM_ROOT_DOMAIN', 'stxapp.io') }}</code></td>
                        <td>443</td>
                        <td>HTTPS (Pure Webmail UI)</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</x-storefront.layout>

