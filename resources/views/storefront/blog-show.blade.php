@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Blog', 'url' => route('blog.index')],
        ['name' => $article['title'], 'url' => route('blog.show', ['slug' => $article['slug']])],
    ];
@endphp

<x-storefront.layout 
    :title="$article['title']" 
    :description="$article['summary']"
    :breadcrumbs="$breadcrumbs"
    :article="$article"
    :type="'article'"
>
    <div class="wrap-narrow band">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; 
            <a href="{{ route('blog.index') }}">Blog</a> &rarr; 
            <span style="color:var(--ink);font-weight:600">{{ $article['title'] }}</span>
        </nav>

        <article data-reveal>
            <header style="margin-bottom:2.5rem;padding-bottom:2rem;border-bottom:1px solid var(--line)">
                <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;font-size:0.85rem;color:var(--muted)">
                    <span class="pill signal">{{ $article['category'] }}</span>
                    <time datetime="{{ $article['date'] }}">{{ date('F j, Y', strtotime($article['date'])) }}</time>
                    <span>&bull; {{ $article['read_time'] }}</span>
                </div>
                <h1 style="font-size: clamp(2rem, 4.5vw, 3rem); line-height: 1.15; margin-bottom: 1rem;">{{ $article['title'] }}</h1>
                <p class="lead" style="margin:0">{{ $article['summary'] }}</p>
                <div style="margin-top:1.25rem;font-size:0.9rem;color:var(--muted)">
                    Written by <strong>{{ $article['author'] }}</strong> &middot; Verified for STX Cloud Platform
                </div>
            </header>

            <div class="prose" style="font-size:1.05rem;line-height:1.75;color:var(--ink);max-width:none">
                {!! $html !!}
            </div>

            <!-- Conversion Action Box -->
            <div class="card card-lift" style="margin-top:4rem;padding:2.5rem;text-align:center" data-reveal>
                <h2 style="font-size:1.6rem;margin-bottom:0.75rem">Ready to Experience High-Performance Cloud Hosting?</h2>
                <p style="color:var(--muted);max-width:60ch;margin:0 auto 1.5rem">Deploy your Next.js application, start a Cloud VPS with root access, or spin up private business email in minutes. Pay easily via local bank transfer in LKR or USD.</p>
                <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
                    <a href="{{ route('store.pricing') }}" class="btn">View All Plans &amp; Pricing &rarr;</a>
                    <a href="{{ route('store.how-it-works') }}" class="btn ghost">How Ordering Works</a>
                </div>
            </div>

            <!-- Related Articles -->
            @if ($related->isNotEmpty())
                <div style="margin-top:4rem;padding-top:2.5rem;border-top:1px solid var(--line)" data-reveal>
                    <h2 style="font-size:1.4rem;margin-bottom:1.5rem">Related Cloud Engineering Guides</h2>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:1.5rem">
                        @foreach ($related as $rel)
                            <div class="card card-lift" style="padding:1.25rem">
                                <span class="pill ink" style="margin-bottom:0.5rem">{{ $rel['category'] }}</span>
                                <h3 style="font-size:1.1rem;margin:0.5rem 0">
                                    <a href="{{ route('blog.show', ['slug' => $rel['slug']]) }}" style="color:inherit;text-decoration:none">
                                        {{ $rel['title'] }}
                                    </a>
                                </h3>
                                <p style="font-size:0.88rem;color:var(--muted);line-height:1.5;margin:0">{{ $rel['summary'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </article>
    </div>
</x-storefront.layout>
