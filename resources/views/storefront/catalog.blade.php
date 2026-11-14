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
    <div class="wrap">
        <nav aria-label="Breadcrumb" style="padding-top:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span>{{ $single ? $single['label'] : 'Pricing' }}</span>
        </nav>

        <div class="pagehead">
            <div>
                <h1>{{ $single ? $single['label'] : 'Plans and pricing' }}</h1>
                <p>{{ $single['blurb'] ?? 'Every plan with its specs and price. Prices are shown in '.$currency.'; switch currency at the top of the page.' }}</p>
                
                <div style="margin-top:1rem;display:inline-flex;align-items:center;gap:0.5rem;font-size:0.85rem;color:var(--muted);background:var(--card);padding:0.35rem 0.75rem;border-radius:var(--radius);border:1px solid var(--line)">
                    <span>💳 No credit card needed &bull; Pay with local bank transfer in {{ $currency }} &bull; VPS delivered in 24–72 hours</span>
                </div>
            </div>
        </div>

        @if (! $single && count($groups) > 1)
            <ul class="jump" aria-label="Jump to a category">
                @foreach ($groups as $g)
                    <li><a href="#{{ $g['slug'] }}">{{ $g['label'] }}</a></li>
                @endforeach
            </ul>
        @endif

        @forelse ($groups as $g)
            <section class="group" id="{{ $g['slug'] }}" aria-labelledby="h-{{ $g['slug'] }}">
                @if (! $single)
                    <h2 id="h-{{ $g['slug'] }}"><a href="{{ route($g['route']) }}" style="color:inherit;text-decoration:none">{{ $g['label'] }}</a></h2>
                    <p class="blurb">{{ $g['blurb'] }}</p>
                @else
                    <h2 class="sr-only" id="h-{{ $g['slug'] }}">{{ $g['label'] }} plans</h2>
                @endif

                <table class="ledger">
                    <caption class="sr-only">{{ $g['label'] }} plans with specifications and price in {{ $currency }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Plan</th>
                            <th scope="col">vCPU</th>
                            <th scope="col">Memory</th>
                            <th scope="col">Storage</th>
                            <th scope="col">Traffic</th>
                            <th scope="col">Price</th>
                            <th scope="col"><span class="sr-only">Order</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($g['rows'] as $row)
                            @php($first = $row['terms']->first())
                            <tr>
                                <td class="plan">
                                    <strong>{{ $row['variant']->name }}
                                        @if ($row['is_sold_out'])
                                            <span class="tag" style="background:#dc2626;color:#fff">Sold out</span>
                                        @elseif ($row['product']->is_popular)
                                            <span class="tag">Most chosen</span>
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
                                            &bull; <span style="color:var(--ok-ink);font-weight:600">Immediate container start</span>
                                        @endif
                                    </small>
                                </td>
                                <td class="num" data-label="vCPU">{{ $row['variant']->vcpu }}</td>
                                <td class="num" data-label="Memory">{{ $row['ram'] }}</td>
                                <td class="num" data-label="Storage">{{ $row['disk'] }}</td>
                                <td class="num" data-label="Traffic">{{ $row['traffic'] }}</td>
                                <td class="price" data-label="Price">
                                    @if ($first)
                                        <span>{{ $first['price'] }}<small>per {{ $first['label'] }}</small></span>
                                    @else
                                        <span style="font-weight:500;font-size:.95rem">Not priced in {{ $currency }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['is_sold_out'])
                                        <button class="btn small" type="button" disabled style="opacity:0.5;cursor:not-allowed">Sold out</button>
                                    @elseif ($first)
                                        <form class="buy" method="post" action="{{ route('cart.add') }}">
                                            @csrf
                                            <input type="hidden" name="variant" value="{{ $row['variant']->uuid }}">
                                            @if ($row['terms']->count() > 1)
                                                <label class="sr-only" for="t-{{ $row['variant']->uuid }}">Billing term for {{ $row['variant']->name }}</label>
                                                <select id="t-{{ $row['variant']->uuid }}" name="billing_type">
                                                    @foreach ($row['terms'] as $t)
                                                        <option value="{{ $t['billing_type'] }}">{{ $t['price'] }} / {{ $t['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="hidden" name="billing_type" value="{{ $first['billing_type'] }}">
                                            @endif
                                            <button class="btn small" type="submit">Add<span class="sr-only"> {{ $row['variant']->name }} to cart</span></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @empty
            <p class="empty">No plans are listed here yet. <a href="{{ route('store.contact') }}">Tell us what you need</a> and we will let you know.</p>
        @endforelse
    </div>
</x-storefront.layout>
