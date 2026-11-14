@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'FAQ', 'url' => route('store.faqs')],
    ];

    $allFaqs = [];
    foreach ($sections as $heading => $items) {
        foreach ($items as [$q, $a]) {
            $allFaqs[] = ['question' => $q, 'answer' => $a];
        }
    }
@endphp

<x-storefront.layout 
    title="Frequently Asked Questions & Answers" 
    description="Frequently asked questions about STX Hosting: bank transfer payments, 24 to 72 hour Cloud VPS fulfillment, custom domains, automated SSL, and backups."
    :breadcrumbs="$breadcrumbs"
    :faqs="$allFaqs"
>
    <div class="wrap" style="padding-top:2.5rem;padding-bottom:4rem">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span>FAQ</span>
        </nav>

        <div class="pagehead">
            <div>
                <h1>Questions and Answers</h1>
                <p>Find answers to common questions about our platform, payments, and servers. Cannot find yours? <a href="{{ route('store.contact') }}">Contact our support team</a>.</p>
            </div>
        </div>

        <div class="prose" style="max-width:800px;margin-top:2.5rem">
            @foreach ($sections as $heading => $items)
                <h2 style="margin-top:2.5rem;font-size:1.4rem">{{ $heading }}</h2>
                @foreach ($items as [$q, $a])
                    <details class="faq" style="background:var(--card);padding:1rem 1.25rem;border:1px solid var(--line);border-radius:var(--radius);margin-bottom:0.75rem">
                        <summary style="font-weight:600;cursor:pointer">{{ $q }}</summary>
                        <p style="margin:0.75rem 0 0;color:var(--muted);line-height:1.6">{{ $a }}</p>
                    </details>
                @endforeach
            @endforeach
        </div>
    </div>
</x-storefront.layout>
