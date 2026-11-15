@php
    $base = $audience === 'admin' ? 'admin.docs' : 'docs';
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => $audience === 'admin' ? 'Staff Handbook' : 'Help Center', 'url' => route($base.'.index')],
        ['name' => $article['title'], 'url' => route($base.'.show', $article['slug'])],
    ];
@endphp
<x-storefront.layout 
    :title="$article['title']" 
    :description="$article['summary']"
    :breadcrumbs="$breadcrumbs"
    :article="['title' => $article['title'], 'summary' => $article['summary'], 'date' => date('Y-m-d'), 'author' => 'STX Documentation']"
    :type="'article'"
>
    <div class="wrap-narrow band">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; 
            <a href="{{ route($base.'.index') }}">{{ $audience === 'admin' ? 'Handbook' : 'Docs' }}</a> &rarr; 
            <span style="color:var(--ink);font-weight:600">{{ $article['title'] }}</span>
        </nav>

        <article data-reveal>
            <div class="pagehead" style="margin-bottom:2rem;padding-bottom:1.5rem;border-bottom:1px solid var(--line)">
                <div>
                    <span class="pill signal" style="margin-bottom:0.75rem">
                        <span class="pill-dot"></span> {{ $audience === 'admin' ? 'Internal Operational Runbook' : 'Verified Documentation' }}
                    </span>
                    <h1 style="font-size: clamp(2rem, 4.5vw, 2.8rem); line-height: 1.15; margin: 0.5rem 0;">{{ $article['title'] }}</h1>
                    @if ($article['summary'])
                        <p class="lead" style="margin:0">{{ $article['summary'] }}</p>
                    @endif
                </div>
            </div>

            <div class="card prose" style="max-width:none;padding:2.5rem">
                {!! $html !!}
            </div>

            <nav aria-label="More articles" class="card" style="margin-top:3rem;padding:1.5rem" data-reveal>
                <h3 style="font-size:1.15rem;margin-bottom:0.75rem">More in this section</h3>
                <ul style="margin:0;padding-left:1.25rem;line-height:1.8">
                    @foreach ($articles as $a)
                        @unless ($a['slug'] === $article['slug'])
                            <li><a href="{{ route($base.'.show', $a['slug']) }}" style="font-weight:500">{{ $a['title'] }}</a></li>
                        @endunless
                    @endforeach
                </ul>
            </nav>
        </article>
    </div>
</x-storefront.layout>
