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
    <div class="wrap" style="padding-top:2.5rem;padding-bottom:5rem">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; 
            <a href="{{ route('blog.index') }}">Blog</a> &rarr; 
            <span>{{ $article['title'] }}</span>
        </nav>

        <article style="max-width:820px;margin:0 auto">
            <header style="margin-bottom:2.5rem;padding-bottom:2rem;border-bottom:1px solid var(--line)">
                <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;font-size:0.875rem;color:var(--muted)">
                    <span style="font-weight:700;color:var(--signal);text-transform:uppercase;letter-spacing:0.04em">{{ $article['category'] }}</span>
                    &bull;
                    <time datetime="{{ $article['date'] }}">{{ date('F j, Y', strtotime($article['date'])) }}</time>
                    &bull;
                    <span>{{ $article['read_time'] }}</span>
                </div>
                <h1 style="font-size:2.5rem;line-height:1.15;margin-bottom:1rem">{{ $article['title'] }}</h1>
                <p style="font-size:1.2rem;color:var(--muted);line-height:1.6;margin:0">{{ $article['summary'] }}</p>
                <div style="margin-top:1.25rem;font-size:0.9rem;color:var(--muted)">
                    Written by <strong>{{ $article['author'] }}</strong> &middot; Verified for STX Cloud Platform
                </div>
            </header>

            <div class="prose" style="font-size:1.05rem;line-height:1.75;color:var(--ink)">
                {!! $html !!}
            </div>

            <!-- Conversion Action Box -->
            <div style="margin-top:4rem;padding:2.5rem;background:var(--card);border:1px solid var(--line);border-radius:var(--radius);text-align:center">
                <h2 style="font-size:1.5rem;margin-bottom:0.75rem">Ready to Experience High-Performance Cloud Hosting?</h2>
                <p style="color:var(--muted);max-width:60ch;margin:0 auto 1.5rem">Deploy your Next.js application, start a Cloud VPS with root access, or spin up private business email in minutes. Pay easily via local bank transfer in LKR or USD.</p>
                <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
                    <a href="{{ route('store.pricing') }}" class="btn">View All Plans &amp; Pricing &rarr;</a>
                    <a href="{{ route('store.how-it-works') }}" class="btn ghost">How Ordering Works</a>
                </div>
            </div>

            <!-- Related Articles -->
            @if ($related->isNotEmpty())
                <div style="margin-top:4rem;padding-top:2.5rem;border-top:1px solid var(--line)">
                    <h2 style="font-size:1.5rem;margin-bottom:1.5rem">Related Cloud Engineering Guides</h2>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:1.5rem">
                        @foreach ($related as $rel)
                            <div style="background:var(--paper);padding:1.25rem;border-radius:var(--radius);border:1px solid var(--line)">
                                <span style="font-size:0.75rem;font-weight:700;color:var(--signal);text-transform:uppercase">{{ $rel['category'] }}</span>
                                <h3 style="font-size:1.05rem;margin:0.5rem 0">
                                    <a href="{{ route('blog.show', ['slug' => $rel['slug']]) }}" style="color:inherit;text-decoration:none">
                                        {{ $rel['title'] }}
                                    </a>
                                </h3>
                                <p style="font-size:0.85rem;color:var(--muted);margin:0;line-height:1.5">{{ Str::limit($rel['summary'], 90) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </article>
    </div>
</x-storefront.layout>
