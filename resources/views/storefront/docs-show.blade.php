@php($base = $audience === 'admin' ? 'admin.docs' : 'docs')
<x-storefront.layout :title="$article['title']" :description="$article['summary']">
    <div class="wrap">
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
