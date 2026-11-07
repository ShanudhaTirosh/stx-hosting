@php($base = $audience === 'admin' ? 'admin.docs' : 'docs')
<x-storefront.layout :title="$audience === 'admin' ? 'Staff handbook' : 'Help center'">
    <div class="wrap">
        <div class="pagehead">
            <div>
                <h1>{{ $audience === 'admin' ? 'Staff handbook' : 'Help center' }}</h1>
                <p>{{ $audience === 'admin' ? 'Setup, daily runbooks and how the platform fits together.' : 'Guides for ordering, deploying, domains, servers, backups and fixing problems.' }}</p>
            </div>
        </div>

        <form method="get" action="{{ route($base.'.index') }}" role="search" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem">
            <label for="docs-q" class="sr-only">Search the {{ $audience === 'admin' ? 'handbook' : 'help center' }}</label>
            <input id="docs-q" type="search" name="q" value="{{ $query }}" placeholder="Search, for example: domain, backup, refund" maxlength="100" style="flex:1;min-width:14rem">
            <button class="btn" type="submit">Search</button>
        </form>

        @if ($results !== null)
            <h2 style="font-size:1.3rem;margin-bottom:.8rem">{{ $results->count() }} {{ $results->count() === 1 ? 'result' : 'results' }} for &ldquo;{{ $query }}&rdquo;</h2>
            <ul style="list-style:none;padding:0;margin:0 0 2rem">
                @forelse ($results as $r)
                    <li style="padding:.9rem 0;border-top:1px solid var(--line)">
                        <a href="{{ route($base.'.show', $r['article']['slug']) }}" style="font:600 1.15rem/1.3 var(--display)">{{ $r['article']['title'] }}</a>
                        <p style="margin:.3rem 0 0;color:var(--muted)">{{ $r['snippet'] }}</p>
                    </li>
                @empty
                    <li class="empty">Nothing matched. Try fewer or simpler words, or <a href="{{ route('store.contact') }}">ask us</a>.</li>
                @endforelse
            </ul>
        @endif

        <h2 style="font-size:1.3rem;margin-bottom:.8rem">All articles</h2>
        <ul style="list-style:none;padding:0;margin:0 0 3rem">
            @foreach ($articles as $a)
                <li style="padding:.9rem 0;border-top:1px solid var(--line)">
                    <a href="{{ route($base.'.show', $a['slug']) }}" style="font:600 1.15rem/1.3 var(--display)">{{ $a['title'] }}</a>
                    @if ($a['summary'])<p style="margin:.3rem 0 0;color:var(--muted)">{{ $a['summary'] }}</p>@endif
                </li>
            @endforeach
        </ul>
    </div>
</x-storefront.layout>
