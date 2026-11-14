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
    <div class="wrap" style="padding-top:2.5rem;padding-bottom:4rem">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span>Blog</span>
        </nav>

        <div class="pagehead" style="margin-bottom:2.5rem">
            <h1>Engineering Blog &amp; Cloud Guides</h1>
            <p style="font-size:1.15rem;color:var(--muted);max-width:75ch">Practical, production-tested guides on deploying applications, running Cloud VPS servers, and managing private email infrastructure.</p>
        </div>

        @if (!empty($categories))
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:2.5rem">
                <a href="{{ route('blog.index') }}" style="padding:0.35rem 0.85rem;border-radius:100px;font-size:0.85rem;text-decoration:none;font-weight:600;{{ empty($activeCategory) ? 'background:var(--ink);color:#fff' : 'background:var(--card);color:var(--ink);border:1px solid var(--line)' }}">All Topics</a>
                @foreach ($categories as $cat)
                    <a href="{{ route('blog.index', ['category' => $cat]) }}" style="padding:0.35rem 0.85rem;border-radius:100px;font-size:0.85rem;text-decoration:none;font-weight:600;{{ strtolower($activeCategory ?? '') === strtolower($cat) ? 'background:var(--ink);color:#fff' : 'background:var(--card);color:var(--ink);border:1px solid var(--line)' }}">{{ $cat }}</a>
                @endforeach
            </div>
        @endif

        @if ($articles->isEmpty())
            <p class="empty">No articles published in this category yet. Check back soon!</p>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:2rem">
                @foreach ($articles as $post)
                    <article style="background:var(--card);padding:2rem;border-radius:var(--radius);border:1px solid var(--line);display:flex;flex-direction:column;justify-content:space-between">
                        <div>
                            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;font-size:0.8rem;color:var(--muted)">
                                <span style="font-weight:700;color:var(--signal);text-transform:uppercase;letter-spacing:0.04em">{{ $post['category'] }}</span>
                                &bull;
                                <time datetime="{{ $post['date'] }}">{{ date('M j, Y', strtotime($post['date'])) }}</time>
                                &bull;
                                <span>{{ $post['read_time'] }}</span>
                            </div>
                            <h2 style="font-size:1.35rem;line-height:1.3;margin-bottom:0.75rem">
                                <a href="{{ route('blog.show', ['slug' => $post['slug']]) }}" style="color:inherit;text-decoration:none">
                                    {{ $post['title'] }}
                                </a>
                            </h2>
                            <p style="font-size:0.95rem;color:var(--muted);line-height:1.6;margin:0">
                                {{ $post['summary'] }}
                            </p>
                        </div>
                        <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
                            <span style="font-size:0.85rem;color:var(--muted)">By {{ $post['author'] }}</span>
                            <a href="{{ route('blog.show', ['slug' => $post['slug']]) }}" style="font-weight:600;font-size:0.9rem">Read Guide &rarr;</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-storefront.layout>
