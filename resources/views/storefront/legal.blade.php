@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Legal', 'url' => route('store.terms')],
        ['name' => $title, 'url' => url()->current()],
    ];
@endphp

<x-storefront.layout :title="$title" :breadcrumbs="$breadcrumbs">
    <div class="wrap-narrow band">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span>Legal</span> &rarr; <span style="color:var(--ink);font-weight:600">{{ $title }}</span>
        </nav>

        <div class="pagehead" data-reveal>
            <div>
                <span class="pill ink" style="margin-bottom:1rem">
                    Compliance &bull; Colombo, Sri Lanka
                </span>
                <h1>{{ $title }}</h1>
                <p class="lead">Official commercial policy &middot; Last updated November 2026</p>
            </div>
        </div>

        <nav aria-label="Legal documents" style="margin:2rem 0" data-reveal>
            <ul class="jump" style="display:flex;gap:0.75rem;flex-wrap:wrap;list-style:none;padding:0">
                <li><a href="{{ route('store.terms') }}" class="pill {{ $type === 'terms' ? 'signal' : 'ink' }}" style="text-decoration:none;padding:0.4rem 0.85rem">Terms of Service</a></li>
                <li><a href="{{ route('store.privacy') }}" class="pill {{ $type === 'privacy' ? 'signal' : 'ink' }}" style="text-decoration:none;padding:0.4rem 0.85rem">Privacy Policy</a></li>
                <li><a href="{{ route('store.refund') }}" class="pill {{ $type === 'refund' ? 'signal' : 'ink' }}" style="text-decoration:none;padding:0.4rem 0.85rem">Refunds &amp; Cancellation</a></li>
                <li><a href="{{ route('store.cookies') }}" class="pill {{ $type === 'cookies' ? 'signal' : 'ink' }}" style="text-decoration:none;padding:0.4rem 0.85rem">Cookie Policy</a></li>
            </ul>
        </nav>

        <div class="card prose" style="max-width:none;padding:2.5rem" data-reveal>
            @include('storefront.legal.'.$type)
        </div>
    </div>
</x-storefront.layout>
