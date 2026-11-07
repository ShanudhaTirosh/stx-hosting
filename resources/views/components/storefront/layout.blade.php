@props(['title' => null, 'description' => null])
@php
    $brand = config('storefront.name');
    $cart = app(\App\Services\Commerce\CartService::class);
    $currency = $cart->currency();
    $cartCount = $cart->count();
    $categories = config('storefront.categories');
    $here = request()->url();
    $curLink = fn (string $c) => request()->fullUrlWithQuery(['currency' => $c]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b2239">
    <title>{{ $title ? $title.' | '.$brand : $brand.': servers, apps, bots and databases' }}</title>
    <meta name="description" content="{{ $description ?? 'Cloud VPS, app hosting, bot hosting and databases. Pay by bank transfer in LKR or USD; a person verifies your receipt.' }}">
    <link rel="icon" href="{{ asset('STKcdr.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Familjen+Grotesk:wght@600;700&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/storefront.css') }}">
</head>
<body>
    <a class="skip" href="#main">Skip to content</a>

    <header class="bar">
        <div class="wrap">
            <a class="brand" href="{{ route('store.index') }}">
                <img src="{{ asset('STKcdr.svg') }}" alt="" width="28" height="28">
                {{ $brand }}
            </a>

            <nav class="nav desk" aria-label="Main">
                @include('components.storefront.nav-links')
            </nav>

            <details class="menu">
                <summary>Menu</summary>
                <nav class="nav" aria-label="Main (mobile)">
                    @include('components.storefront.nav-links')
                </nav>
            </details>

            <div class="tools">
                <div class="cur" role="group" aria-label="Currency">
                    <a href="{{ $curLink('USD') }}" @if ($currency === 'USD') aria-current="true" @endif>USD</a>
                    <a href="{{ $curLink('LKR') }}" @if ($currency === 'LKR') aria-current="true" @endif>LKR</a>
                </div>
                <a class="cartlink" href="{{ route('cart.show') }}" aria-label="Cart, {{ $cartCount }} {{ $cartCount === 1 ? 'item' : 'items' }}">Cart @if ($cartCount > 0)<b>{{ $cartCount }}</b>@endif</a>
                @auth
                    <a href="{{ route('portal.services') }}">My services</a>
                @else
                    <a href="{{ route('login') }}">Sign in</a>
                @endauth
            </div>
        </div>
    </header>

    <main id="main">
        @if (session('success'))
            <div class="wrap"><div class="notice ok" role="status">{{ session('success') }}</div></div>
        @endif
        @if (session('error'))
            <div class="wrap"><div class="notice err" role="alert">{{ session('error') }}</div></div>
        @endif

        {{ $slot }}
    </main>

    <footer class="foot">
        <div class="wrap">
            <div class="cols">
                <div>
                    <h2>{{ $brand }}</h2>
                    <p>Hosting you can pay for with a bank transfer, in rupees or dollars.</p>
                </div>
                <div>
                    <h2>Plans</h2>
                    <ul>
                        @foreach ($categories as $cat)
                            <li><a href="{{ route($cat['route']) }}">{{ $cat['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h2>Company</h2>
                    <ul>
                        <li><a href="{{ route('store.about') }}">About</a></li>
                        <li><a href="{{ route('docs.index') }}">Help center</a></li>
                        <li><a href="{{ route('store.faqs') }}">FAQ</a></li>
                        <li><a href="{{ route('status.public') }}">Status</a></li>
                        <li><a href="{{ route('store.contact') }}">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h2>Legal</h2>
                    <ul>
                        <li><a href="{{ route('store.terms') }}">Terms</a></li>
                        <li><a href="{{ route('store.privacy') }}">Privacy</a></li>
                        <li><a href="{{ route('store.refund') }}">Refunds</a></li>
                        <li><a href="{{ route('store.cookies') }}">Cookies</a></li>
                    </ul>
                </div>
            </div>
            <small>&copy; {{ date('Y') }} {{ $brand }}, a {{ config('storefront.parent_brand') }} service.</small>
        </div>
    </footer>
</body>
</html>
