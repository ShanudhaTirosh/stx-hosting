@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Blog', 'url' => route('blog.index')],
    ];
@endphp

<x-storefront.layout 
    :title="'Engineering Blog & Cloud Guides'" 
    :description="'Technical guides, cloud infrastructure architecture, VPS buying advice in Sri Lanka, Next.js Docker deployments, and business email deliverability best practices.'"
    :breadcrumbs="$breadcrumbs"
>
    <div class="wrap band">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span style="color:var(--ink);font-weight:600">Blog</span>
        </nav>

        <div class="pagehead" data-reveal>
            <div>
                <span class="pill signal" style="margin-bottom:1rem">
                    <span class="pill-dot"></span> Engineering &amp; Operational Guides
                </span>
                <h1>Engineering Blog &amp; Cloud Guides</h1>
                <p class="lead">Practical, production-tested guides on deploying applications, running Cloud VPS servers, and managing private email infrastructure in Sri Lanka and globally.</p>
            </div>
        </div>

        @if (!empty($categories))
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin: 2rem 0 2.5rem;" data-reveal>
                <a href="{{ route('blog.index') }}" class="pill {{ empty($activeCategory) ? 'signal' : 'ink' }}" style="text-decoration:none;padding:0.4rem 0.85rem">All Topics</a>
                @foreach ($categories as $cat)
                    <a href="{{ route('blog.index', ['category' => $cat]) }}" class="pill {{ strtolower($activeCategory ?? '') === strtolower($cat) ? 'signal' : 'ink' }}" style="text-decoration:none;padding:0.4rem 0.85rem">{{ $cat }}</a>
                @endforeach
            </div>
        @endif

        @if ($articles->isEmpty())
            <div class="card empty" style="text-align:center;padding:3rem 1.5rem" data-reveal>
                <p>No articles published in this category yet. Check back soon!</p>
            </div>
        @else
            <div class="card-grid-3" data-reveal>
                @foreach ($articles as $post)
                    <article class="card card-lift" style="display:flex;flex-direction:column;justify-content:space-between">
                        <div>
                            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;font-size:0.82rem;color:var(--muted)">
                                <span class="pill signal">{{ $post['category'] }}</span>
                                <time datetime="{{ $post['date'] }}">{{ date('M j, Y', strtotime($post['date'])) }}</time>
                                <span>&bull; {{ $post['read_time'] }}</span>
                            </div>
                            <h2 style="font-size:1.35rem;line-height:1.25;margin-bottom:0.75rem">
                                <a href="{{ route('blog.show', ['slug' => $post['slug']]) }}" style="color:inherit;text-decoration:none">
                                    {{ $post['title'] }}
                                </a>
                            </h2>
                            <p style="font-size:0.92rem;color:var(--muted);line-height:1.6;margin:0">
                                {{ $post['summary'] }}
                            </p>
                        </div>
                        <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--line-subtle);display:flex;justify-content:space-between;align-items:center">
                            <span style="font-size:0.85rem;color:var(--muted)">By {{ $post['author'] }}</span>
                            <a href="{{ route('blog.show', ['slug' => $post['slug']]) }}" style="font-weight:600;font-size:0.9rem">Read Guide &rarr;</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-storefront.layout>
