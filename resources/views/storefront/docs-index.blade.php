@php
    $base = $audience === 'admin' ? 'admin.docs' : 'docs';
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => $audience === 'admin' ? 'Staff Handbook' : 'Help Center', 'url' => route($base.'.index')],
    ];
@endphp

<x-storefront.layout 
    :title="$audience === 'admin' ? 'Staff Handbook' : 'Help Center & Documentation'"
    :breadcrumbs="$breadcrumbs"
>
    <div class="wrap-narrow band">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span style="color:var(--ink);font-weight:600">{{ $audience === 'admin' ? 'Handbook' : 'Docs' }}</span>
        </nav>

        <div class="pagehead" data-reveal>
            <div>
                <span class="pill signal" style="margin-bottom:1rem">
                    <span class="pill-dot"></span> Knowledge Base &amp; Runbooks
                </span>
                <h1>{{ $audience === 'admin' ? 'Staff Handbook' : 'Help Center' }}</h1>
                <p class="lead">{{ $audience === 'admin' ? 'Setup, daily runbooks and platform architecture operations.' : 'Production documentation for ordering, deploying Git repositories, custom domains, VPS management, and disaster recovery.' }}</p>
            </div>
        </div>

        <form method="get" action="{{ route($base.'.index') }}" role="search" style="display:flex;gap:.75rem;margin: 2rem 0 2.5rem" data-reveal>
            <label for="docs-q" class="sr-only">Search the {{ $audience === 'admin' ? 'handbook' : 'help center' }}</label>
            <input id="docs-q" type="search" name="q" value="{{ $query }}" placeholder="Search articles (e.g. domain, backup, refund, vps)..." maxlength="100" style="flex:1">
            <button class="btn" type="submit">Search Docs</button>
        </form>

        @if ($results !== null)
            <div style="margin-bottom: 2.5rem;" data-reveal>
                <h2 style="font-size:1.35rem;margin-bottom:1rem">{{ $results->count() }} {{ $results->count() === 1 ? 'Result' : 'Results' }} for &ldquo;{{ $query }}&rdquo;</h2>
                <div style="display:flex;flex-direction:column;gap:1rem">
                    @forelse ($results as $r)
                        <div class="card card-lift" style="padding:1.25rem">
                            <a href="{{ route($base.'.show', $r['article']['slug']) }}" style="font:600 1.2rem/1.3 var(--display);color:var(--ink);text-decoration:none">{{ $r['article']['title'] }}</a>
                            <p style="margin:.4rem 0 0;color:var(--muted);font-size:0.92rem">{{ $r['snippet'] }}</p>
                        </div>
                    @empty
                        <div class="card empty" style="text-align:center;padding:2.5rem">
                            <p>No matching articles found. Try broader terms or <a href="{{ route('store.contact') }}">contact support</a>.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        <div data-reveal>
            <h2 style="font-size:1.45rem;margin-bottom:1.25rem">All Documentation Guides</h2>
            <div style="display:flex;flex-direction:column;gap:1rem">
                @foreach ($articles as $a)
                    <div class="card card-lift" style="padding:1.5rem">
                        <a href="{{ route($base.'.show', $a['slug']) }}" style="font:600 1.25rem/1.3 var(--display);color:var(--ink);text-decoration:none">{{ $a['title'] }}</a>
                        @if ($a['summary'])
                            <p style="margin:.45rem 0 0;color:var(--muted);font-size:0.94rem;line-height:1.6">{{ $a['summary'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-storefront.layout>
