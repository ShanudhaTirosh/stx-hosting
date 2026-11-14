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
    <div class="wrap" style="padding-top:2.5rem;padding-bottom:4rem">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; 
            <a href="{{ route($base.'.index') }}">{{ $audience === 'admin' ? 'Handbook' : 'Docs' }}</a> &rarr; 
            <span>{{ $article['title'] }}</span>
        </nav>
        <div class="pagehead">
            <div>
                <p style="margin:0 0 .5rem"><a href="{{ route($base.'.index') }}">&larr; {{ $audience === 'admin' ? 'Staff handbook' : 'Help center' }}</a></p>
                <h1>{{ $article['title'] }}</h1>
                @if ($article['summary'])<p>{{ $article['summary'] }}</p>@endif
            </div>
        </div>
        <div class="prose">{!! $html !!}</div>

        <nav aria-label="More articles" style="margin:1rem 0 3rem">
            <h2 style="font-size:1.1rem;margin-bottom:.5rem">More articles</h2>
            <ul style="margin:0;padding-left:1.1rem">
                @foreach ($articles as $a)
                    @unless ($a['slug'] === $article['slug'])
                        <li><a href="{{ route($base.'.show', $a['slug']) }}">{{ $a['title'] }}</a></li>
                    @endunless
                @endforeach
            </ul>
        </nav>
    </div>
</x-storefront.layout>
