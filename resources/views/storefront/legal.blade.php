<x-storefront.layout :title="$title">
    <div class="wrap">
        <div class="pagehead"><div><h1>{{ $title }}</h1><p>Last updated September 2026</p></div></div>
        <nav aria-label="Legal documents">
            <ul class="jump">
                <li><a href="{{ route('store.terms') }}" @if ($type === 'terms') aria-current="page" @endif>Terms</a></li>
                <li><a href="{{ route('store.privacy') }}" @if ($type === 'privacy') aria-current="page" @endif>Privacy</a></li>
                <li><a href="{{ route('store.refund') }}" @if ($type === 'refund') aria-current="page" @endif>Refunds</a></li>
                <li><a href="{{ route('store.cookies') }}" @if ($type === 'cookies') aria-current="page" @endif>Cookies</a></li>
            </ul>
        </nav>
        <div class="prose">
            @include('storefront.legal.'.$type)
        </div>
    </div>
</x-storefront.layout>
