@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Templates', 'url' => route('store.templates')],
    ];

    $popularTemplates = [
        ['slug' => 'nextjs', 'name' => 'Next.js 15', 'category' => 'Framework', 'desc' => 'Full-stack React framework with SSR, static optimization, and API routes.'],
        ['slug' => 'supabase', 'name' => 'Supabase BaaS', 'category' => 'Backend', 'desc' => 'Open source Firebase alternative with Postgres, Auth, Realtime, and Storage.'],
        ['slug' => 'wordpress', 'name' => 'WordPress & MySQL', 'category' => 'CMS', 'desc' => 'Production WordPress with high-performance Caddy/Nginx reverse proxy and PHP 8.4.'],
        ['slug' => 'ghost', 'name' => 'Ghost Publishing', 'category' => 'Publishing', 'desc' => 'Modern professional publishing platform with rich editor and newsletter engine.'],
        ['slug' => 'n8n', 'name' => 'n8n Workflow Automation', 'category' => 'Automation', 'desc' => 'Fair-code workflow automation tool connecting hundreds of APIs and webhooks.'],
        ['slug' => 'directus', 'name' => 'Directus Headless CMS', 'category' => 'CMS', 'desc' => 'Real-time API and app dashboard wrapping any SQL database.'],
        ['slug' => 'appwrite', 'name' => 'Appwrite Backend', 'category' => 'Backend', 'desc' => 'Complete Web, Mobile, and Flutter backend server with auth, db, and storage.'],
        ['slug' => 'plausible', 'name' => 'Plausible Analytics', 'category' => 'Analytics', 'desc' => 'Lightweight, privacy-friendly Google Analytics alternative.'],
        ['slug' => 'meilisearch', 'name' => 'Meilisearch', 'category' => 'Search', 'desc' => 'Lightning-fast, typo-tolerant search engine with RESTful API.'],
        ['slug' => 'redis', 'name' => 'Redis Cache', 'category' => 'Database', 'desc' => 'In-memory data structure store used as a database, cache, and message broker.'],
        ['slug' => 'postgresql', 'name' => 'PostgreSQL 16', 'category' => 'Database', 'desc' => 'Powerful open-source object-relational database system.'],
        ['slug' => 'pocketbase', 'name' => 'PocketBase', 'category' => 'Backend', 'desc' => 'Single-file open source backend with embedded SQLite and real-time subscriptions.'],
    ];
@endphp

<x-storefront.layout 
    :title="'Curated 1-Click Docker Application Templates'" 
    :description="'Deploy 31+ curated production Docker application templates: Next.js, Supabase, WordPress, Ghost, n8n, Directus, and PostgreSQL on STX Hosting with auto-SSL.'"
    :breadcrumbs="$breadcrumbs"
>
    <div class="wrap" style="padding-top:2.5rem;padding-bottom:4rem">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span>Templates</span>
        </nav>

        <div class="pagehead" style="margin-bottom:3rem">
            <h1>Curated 1-Click Application Templates</h1>
            <p style="font-size:1.15rem;color:var(--muted);max-width:75ch">Launch production-ready open source applications, headless backends, and databases on isolated Docker containers with automated SSL certificates.</p>
        </div>

        <h2 style="font-size:1.5rem;margin-bottom:1.25rem">Popular Production Templates</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1.5rem;margin-bottom:3.5rem">
            @foreach ($popularTemplates as $tpl)
                <div style="background:var(--card);padding:1.5rem;border-radius:var(--radius);border:1px solid var(--line);display:flex;flex-direction:column;justify-content:space-between">
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem">
                            <span style="font-size:0.75rem;text-transform:uppercase;font-weight:700;color:var(--signal);letter-spacing:0.05em">{{ $tpl['category'] }}</span>
                            <span style="font-size:0.75rem;padding:0.2rem 0.5rem;background:var(--ok-bg);color:var(--ok-ink);border-radius:100px;font-weight:600">Active</span>
                        </div>
                        <h3 style="font-size:1.2rem;margin-bottom:0.5rem">{{ $tpl['name'] }}</h3>
                        <p style="font-size:0.9rem;color:var(--muted);line-height:1.5;margin:0">{{ $tpl['desc'] }}</p>
                    </div>
                    <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--line)">
                        <a href="{{ route('store.app-hosting') }}" class="btn small" style="width:100%;text-align:center">Deploy Template &rarr;</a>
                    </div>
                </div>
            @endforeach
        </div>

        @if (!empty($templates))
            <h2 style="font-size:1.5rem;margin-bottom:1rem">Complete Curated Directory ({{ count($templates) }} Templates)</h2>
            <div style="background:var(--card);padding:1.5rem;border-radius:var(--radius);border:1px solid var(--line)">
                <div style="display:flex;flex-wrap:wrap;gap:0.75rem">
                    @foreach ($templates as $t)
                        <a href="{{ route('store.app-hosting') }}" style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.4rem 0.85rem;background:var(--paper);border:1px solid var(--line);border-radius:var(--radius);color:var(--ink);text-decoration:none;font-size:0.875rem;font-weight:500">
                            <span>📦</span> {{ $t['name'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-storefront.layout>
