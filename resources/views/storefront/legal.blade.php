@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Legal', 'url' => route('store.terms')],
        ['name' => $title, 'url' => url()->current()],
    ];
@endphp

<x-storefront.layout :title="$title" :breadcrumbs="$breadcrumbs">
    <div class="wrap" style="padding-top:2.5rem;padding-bottom:4rem">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span>Legal</span> &rarr; <span>{{ $title }}</span>
        </nav>

        <div class="pagehead">
            <div>
                <h1>{{ $title }}</h1>
                <p>Official commercial policy &middot; Last updated November 2026</p>
            </div>
        </div>

        <nav aria-label="Legal documents" style="margin:2rem 0">
            <ul class="jump">
                <li><a href="{{ route('store.terms') }}" @if ($type === 'terms') aria-current="page" @endif>Terms of Service</a></li>
                <li><a href="{{ route('store.privacy') }}" @if ($type === 'privacy') aria-current="page" @endif>Privacy Policy</a></li>
                <li><a href="{{ route('store.refund') }}" @if ($type === 'refund') aria-current="page" @endif>Refunds &amp; Cancellation</a></li>
                <li><a href="{{ route('store.cookies') }}" @if ($type === 'cookies') aria-current="page" @endif>Cookie Policy</a></li>
            </ul>
        </nav>

        <div class="prose" style="max-width:800px;background:var(--card);padding:2.5rem;border-radius:var(--radius);border:1px solid var(--line)">
            @include('storefront.legal.'.$type)
        </div>
    </div>
</x-storefront.layout>
