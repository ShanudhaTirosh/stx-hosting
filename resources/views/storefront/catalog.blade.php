@php
    $productSchemas = [];
    foreach ($groups as $g) {
        foreach ($g['rows'] as $r) {
            $firstTerm = $r['terms']->first();
            $productSchemas[] = [
                'name' => $r['variant']->name . ' (' . $r['product']->name . ')',
                'description' => $r['variant']->vcpu . ' vCPU, ' . $r['ram'] . ' RAM, ' . $r['disk'] . ' storage, ' . $r['traffic'] . ' traffic. ' . ($g['slug'] === 'vps' ? 'Manual provisioning SLA 24 to 72 hours.' : 'Instant container start on payment approval.'),
                'price' => $firstTerm ? (string) ($firstTerm['cents'] / 100) : '0',
                'currency' => $currency,
            ];
        }
    }
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => $single ? $single['label'] : 'Plans & Pricing', 'url' => url()->current()],
    ];
@endphp

<x-storefront.layout 
    :title="$single ? $single['label'] . ' Plans & Pricing' : 'Plans & Pricing Ledger'" 
    :description="$single['blurb'] ?? 'Complete pricing matrix for Cloud VPS, Docker app hosting, managed databases, and bots. Priced in ' . $currency . ' with bank transfer payment.'"
    :breadcrumbs="$breadcrumbs"
    :plans="$productSchemas"
>
    <div class="wrap band">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span style="color:var(--ink);font-weight:600">{{ $single ? $single['label'] : 'Pricing' }}</span>
        </nav>

        <div class="pagehead" data-reveal>
            <div>
                <span class="pill emerald" style="margin-bottom:1rem">
                    <span class="pill-dot"></span> 100% Predictable Flat Billing &bull; Zero Hidden Hikes
                </span>
                <h1>{{ $single ? $single['label'] : 'Plans and Pricing' }}</h1>
                <p class="lead">{{ $single['blurb'] ?? 'Transparent server specifications and exact rates. Prices shown in '.$currency.'; switch currency at the top of any page.' }}</p>
                
                <div style="margin-top:1.25rem;display:inline-flex;align-items:center;gap:0.6rem;font-size:0.88rem;color:var(--muted);background:var(--card);padding:0.45rem 0.85rem;border-radius:var(--radius-sm);border:1px solid var(--line);box-shadow:var(--shadow-subtle)">
                    <span>💳 Zero credit card required &bull; Transfer via ComBank, Sampath, HNB, or BOC &bull; VPS delivered in 24–72 hours</span>
                </div>
            </div>

            <!-- Interactive Monthly / Annual Billing Cycle Toggle -->
            <div style="text-align:right" class="pricing-toggle-wrap">
                <div class="pricing-toggle" role="group" aria-label="Billing cycle selector">
                    <button type="button" class="toggle-btn active" data-term="monthly">Monthly</button>
                    <button type="button" class="toggle-btn" data-term="annual">
                        Annual <span class="savings-badge">Save ~15%</span>
                    </button>
                </div>
            </div>
        </div>

        @if (! $single && count($groups) > 1)
            <div style="margin: 2rem 0;" data-reveal>
                <ul class="jump" aria-label="Jump to category" style="display:flex;gap:0.75rem;flex-wrap:wrap;list-style:none;padding:0">
                    @foreach ($groups as $g)
                        <li>
                            <a href="#{{ $g['slug'] }}" class="pill ink" style="text-decoration:none;font-weight:600;font-size:0.88rem;padding:0.4rem 0.85rem">
                                {{ $g['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @forelse ($groups as $g)
            <section class="group" id="{{ $g['slug'] }}" aria-labelledby="h-{{ $g['slug'] }}" style="margin: 3.5rem 0;" data-reveal>
                @if (! $single)
                    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:1.25rem">
                        <h2 id="h-{{ $g['slug'] }}" style="font-size:1.75rem">
                            <a href="{{ route($g['route']) }}" style="color:inherit;text-decoration:none">{{ $g['label'] }}</a>
                        </h2>
                        <span style="font-size:0.9rem;color:var(--muted)">{{ $g['blurb'] }}</span>
                    </div>
                @else
                    <h2 class="sr-only" id="h-{{ $g['slug'] }}">{{ $g['label'] }} plans</h2>
                @endif

                <div class="ledger-wrap">
                    <table class="ledger">
                        <caption class="sr-only">{{ $g['label'] }} plans with specifications and price in {{ $currency }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" style="width:28%">Plan</th>
                                <th scope="col" style="width:10%">vCPU</th>
                                <th scope="col" style="width:12%">Memory</th>
                                <th scope="col" style="width:12%">Storage</th>
                                <th scope="col" style="width:12%">Traffic</th>
                                <th scope="col" style="width:14%">Price</th>
                                <th scope="col" style="width:12%"><span class="sr-only">Order</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($g['rows'] as $row)
                                @php
                                    $first = $row['terms']->first();
                                    $monthlyTerm = $row['terms']->firstWhere('billing_type', 'monthly') ?: $first;
                                    $annualTerm = $row['terms']->firstWhere('billing_type', 'annual') ?: $monthlyTerm;
                                    $monthlyCents = $monthlyTerm ? (int) $monthlyTerm['cents'] : 0;
                                    $annualCents = $annualTerm ? (int) ($annualTerm['cents'] / 12) : $monthlyCents;
                                @endphp
                                <tr>
                                    <td class="plan-col">
                                        <strong>{{ $row['variant']->name }}
                                            @if ($row['is_sold_out'])
                                                <span class="pill" style="background:#fee2e2;color:#991b1b;margin-left:0.4rem">Sold out</span>
                                            @elseif ($row['product']->is_popular)
                                                <span class="pill signal" style="margin-left:0.4rem">Most chosen</span>
                                            @endif
                                        </strong>
                                        <small>
                                            {{ $row['product']->name }}
                                            @if (!empty($row['location']))
                                                &bull; {{ $row['location'] }}
                                            @endif
                                            @if ($g['slug'] === 'vps')
                                                &bull; <span style="color:#7c3aed;font-weight:600">24–72h manual delivery</span>
                                            @else
                                                &bull; <span style="color:var(--emerald);font-weight:600">Instant container start</span>
                                            @endif
                                        </small>
                                    </td>
                                    <td data-label="vCPU"><strong>{{ $row['variant']->vcpu }}</strong> Core</td>
                                    <td data-label="Memory"><strong>{{ $row['ram'] }}</strong></td>
                                    <td data-label="Storage">{{ $row['disk'] }}</td>
                                    <td data-label="Traffic">{{ $row['traffic'] }}</td>
                                    <td class="price-col" data-label="Price">
                                        @if ($first)
                                            <span 
                                                data-price-monthly="{{ (int) ($monthlyCents / 100) }}" 
                                                data-price-annual="{{ (int) ($annualCents / 100) }}"
                                                data-currency="{{ $currency }}"
                                                data-current-val="{{ (int) ($monthlyCents / 100) }}"
                                            >
                                                {{ $first['price'] }}
                                            </span>
                                            <small data-term-label>per {{ $first['label'] }}</small>
                                        @else
                                            <span style="font-weight:500;font-size:.9rem;color:var(--muted)">Not priced in {{ $currency }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($row['is_sold_out'])
                                            <button class="btn small" type="button" disabled>Sold out</button>
                                        @elseif ($first)
                                            <form class="buy" method="post" action="{{ route('cart.add') }}" style="display:flex;gap:0.5rem;align-items:center;justify-content:flex-end">
                                                @csrf
                                                <input type="hidden" name="variant" value="{{ $row['variant']->uuid }}">
                                                @if ($row['terms']->count() > 1)
                                                    <label class="sr-only" for="t-{{ $row['variant']->uuid }}">Billing term for {{ $row['variant']->name }}</label>
                                                    <select id="t-{{ $row['variant']->uuid }}" name="billing_type" style="padding:0.35rem 0.5rem;font-size:0.85rem;max-width:9rem">
                                                        @foreach ($row['terms'] as $t)
                                                            <option value="{{ $t['billing_type'] }}">{{ $t['price'] }} / {{ $t['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="hidden" name="billing_type" value="{{ $first['billing_type'] }}">
                                                @endif
                                                <button class="btn small" type="submit">Order</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <div class="card empty" style="text-align:center;padding:3.5rem 1.5rem" data-reveal>
                <h3>No plans currently listed in this category</h3>
                <p>We regularly expand our catalog based on capacity. Need a tailored server configuration?</p>
                <a href="{{ route('store.contact') }}" class="btn ghost">Contact Infrastructure Team</a>
            </div>
        @endforelse
    </div>
</x-storefront.layout>
