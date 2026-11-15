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
    <div class="wrap-narrow band">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span style="color:var(--ink);font-weight:600">FAQ</span>
        </nav>

        <div class="pagehead" data-reveal>
            <div>
                <span class="pill signal" style="margin-bottom:1rem">
                    <span class="pill-dot"></span> Knowledge &amp; Answers
                </span>
                <h1>Questions and Answers</h1>
                <p class="lead">Clear answers to common questions about payments, server provisioning, custom domains, and platform architecture. Need custom help? <a href="{{ route('store.contact') }}">Contact our engineering team</a>.</p>
            </div>
        </div>

        <div style="margin-top:2.5rem" data-reveal>
            @foreach ($sections as $heading => $items)
                <div style="margin: 2.5rem 0 1rem">
                    <h2 style="font-size: 1.45rem; margin-bottom: 1rem;">{{ $heading }}</h2>
                    <div class="accordion">
                        @foreach ($items as [$q, $a])
                            <div class="accordion-item">
                                <button class="accordion-trigger" aria-expanded="false">
                                    <span>{{ $q }}</span>
                                    <svg class="accordion-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div class="accordion-content">
                                    <p>{{ $a }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card card-lift" style="margin-top:3.5rem;text-align:center;padding:2.5rem" data-reveal>
            <h3>Have a question not answered here?</h3>
            <p>Our infrastructure team is available on Discord, WhatsApp, and email.</p>
            <div style="display:inline-flex;gap:1rem;margin-top:1rem">
                <a href="{{ route('store.contact') }}" class="btn">Send a Message</a>
                <a href="https://discord.gg/shanutechx" target="_blank" rel="noopener" class="btn ghost">Join Discord Community</a>
            </div>
        </div>
    </div>
</x-storefront.layout>
