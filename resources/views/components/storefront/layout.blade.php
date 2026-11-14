@props([
    'title' => null,
    'description' => null,
    'breadcrumbs' => [],
    'article' => null,
    'faqs' => [],
    'plans' => [],
    'type' => 'website',
])
@php
    $brand = config('storefront.name', 'STX Hosting');
    $parentBrand = config('storefront.parent_brand', 'STX Enterprises');
    $cart = app(\App\Services\Commerce\CartService::class);
    $currency = $cart->currency();
    $cartCount = $cart->count();
    $categories = config('storefront.categories', []);
    $currentUrl = url()->current();
    $curLink = fn (string $c) => request()->fullUrlWithQuery(['currency' => $c]);

    $metaTitle = $title 
        ? $title . ' | ' . $brand . ' — Sri Lanka & Global Cloud Hosting' 
        : $brand . ': Cloud VPS, App Hosting, Databases & Email in Sri Lanka';
    
    $metaDescription = $description ?? 'High-performance NVMe Cloud VPS, 1-click Docker app templates, and business email hosting. Pay easily via local bank transfer in LKR or USD with transparent pricing and zero credit card hassles.';
    $ogImage = asset('STKcdr.svg');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b2239">

    <!-- Primary Meta Tags -->
    <title>{{ $metaTitle }}</title>
    <meta name="title" content="{{ $metaTitle }}">
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $currentUrl }}">

    <!-- Regional & Multi-Language Alternate Tags -->
    <link rel="alternate" hreflang="en-LK" href="{{ $currentUrl }}">
    <link rel="alternate" hreflang="x-default" href="{{ $currentUrl }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="{{ $type === 'article' ? 'article' : 'website' }}">
    <meta property="og:url" content="{{ $currentUrl }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:site_name" content="{{ $brand }}">
    <meta property="og:locale" content="en_LK">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ $currentUrl }}">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    <!-- Favicons & Manifest -->
    <link rel="icon" href="{{ asset('STKcdr.svg') }}" type="image/svg+xml">
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    <!-- Performance Preconnect & Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Familjen+Grotesk:wght@600;700&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/storefront.css') }}">

    <!-- Schema.org JSON-LD Structured Data -->
    <x-storefront.schema-org 
        :title="$title" 
        :description="$metaDescription" 
        :type="$type" 
        :breadcrumbs="$breadcrumbs" 
        :article="$article" 
        :faqs="$faqs" 
        :plans="$plans" 
    />
</head>
<body>
    <a class="skip" href="#main">Skip to main content</a>

    <header class="bar">
        <div class="wrap">
            <a class="brand" href="{{ route('store.index') }}" aria-label="{{ $brand }} Home">
                <img src="{{ asset('STKcdr.svg') }}" alt="" width="28" height="28">
                <span>{{ $brand }}</span>
            </a>

            <nav class="nav desk" aria-label="Main Navigation">
                <x-storefront.nav-links />
            </nav>

            <details class="menu">
                <summary aria-label="Toggle navigation menu">Menu</summary>
                <nav class="nav" aria-label="Mobile Navigation">
                    <x-storefront.nav-links />
                </nav>
            </details>

            <div class="tools">
                <div class="cur" role="group" aria-label="Select Currency">
                    <a href="{{ $curLink('USD') }}" @if ($currency === 'USD') aria-current="true" @endif title="Show prices in USD">USD</a>
                    <a href="{{ $curLink('LKR') }}" @if ($currency === 'LKR') aria-current="true" @endif title="Show prices in Sri Lankan Rupees">LKR</a>
                </div>
                <a class="cartlink" href="{{ route('cart.show') }}" aria-label="Shopping Cart, {{ $cartCount }} {{ $cartCount === 1 ? 'item' : 'items' }}">Cart @if ($cartCount > 0)<b>{{ $cartCount }}</b>@endif</a>
                @auth
                    <a href="{{ route('portal.services') }}" class="btn small" style="background:var(--teal)">My Services</a>
                @else
                    <a href="{{ route('login') }}" class="btn small ghost">Sign In</a>
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

    <x-storefront.footer />
</body>
</html>
