@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Contact', 'url' => route('store.contact')],
    ];
@endphp

<x-storefront.layout 
    title="Contact Sales & Technical Support" 
    description="Get in touch with the STX Hosting team for cloud server quotes, custom plan inquiries, invoice assistance, or technical support."
    :breadcrumbs="$breadcrumbs"
>
    <div class="wrap band">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span style="color:var(--ink);font-weight:600">Contact</span>
        </nav>

        <div class="pagehead" data-reveal>
            <div>
                <span class="pill signal" style="margin-bottom:1rem">
                    <span class="pill-dot"></span> Direct Support Channels
                </span>
                <h1>Contact Our Infrastructure Team</h1>
                <p class="lead">Have a custom server specification, volume billing requirement, or technical question? We respond promptly via email and Discord.</p>
            </div>
        </div>

        <div class="checkout" style="margin-top:2.5rem" data-reveal>
            <div class="card">
                <form method="post" action="{{ route('store.contact.send') }}" class="formgrid" novalidate>
                    @csrf
                    <div class="hp" aria-hidden="true">
                        <label for="website">Leave this field empty</label>
                        <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div>
                        <label for="name">Your Name</label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name">
                        @error('name')<div class="pill" style="background:#fee2e2;color:#991b1b;margin-top:0.4rem">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="email">Corporate or Personal Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                        @error('email')<div class="pill" style="background:#fee2e2;color:#991b1b;margin-top:0.4rem">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="department">Inquiry Type</label>
                        <select id="department" name="department">
                            @foreach (['tech' => 'Technical Support & Architecture', 'sales' => 'Custom Server Specification & Quotes', 'billing' => 'Bank Transfer & Invoicing Help', 'abuse' => 'Security & Abuse Report'] as $value => $text)
                                <option value="{{ $value }}" @selected(old('department', 'tech') === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="subject">Subject</label>
                        <input id="subject" type="text" name="subject" value="{{ old('subject') }}" placeholder="Brief summary of request">
                    </div>
                    <div class="full">
                        <label for="message">Detailed Message</label>
                        <textarea id="message" name="message" rows="5" required placeholder="Describe your workload, tech stack, or assistance needed...">{{ old('message') }}</textarea>
                        @error('message')<div class="pill" style="background:#fee2e2;color:#991b1b;margin-top:0.4rem">{{ $message }}</div>@enderror
                    </div>
                    <div class="full">
                        <button class="btn" type="submit">Submit Inquiry &rarr;</button>
                    </div>
                </form>
            </div>

            <!-- Sidebar -->
            <div style="display:flex;flex-direction:column;gap:1.5rem">
                <div class="card card-lift">
                    <h2 style="font-size:1.25rem;margin-bottom:0.75rem">Direct Channels</h2>
                    <p style="font-size:0.92rem;color:var(--muted);line-height:1.6;margin-bottom:1rem">For real-time discussions or fast verification inquiries, contact our team via Discord or email:</p>
                    <ul style="list-style:none;padding:0;margin:0;font-size:0.92rem;line-height:2">
                        @if (config('storefront.support.discord'))
                            <li>💬 <a href="{{ config('storefront.support.discord') }}" target="_blank" rel="noopener"><strong>STX Discord Community</strong></a></li>
                        @endif
                        @if (config('storefront.support.whatsapp'))
                            <li>📱 <a href="{{ config('storefront.support.whatsapp') }}" target="_blank" rel="noopener"><strong>WhatsApp Direct</strong></a></li>
                        @endif
                        @if (config('storefront.support.email'))
                            <li>✉️ <a href="mailto:{{ config('storefront.support.email') }}"><strong>{{ config('storefront.support.email') }}</strong></a></li>
                        @endif
                    </ul>
                </div>

                <div class="card" style="background:var(--paper-soft)">
                    <h3 style="font-size:1.1rem;margin-bottom:0.5rem">Operating Hours</h3>
                    <p style="font-size:0.88rem;color:var(--muted);margin:0;line-height:1.6">
                        Support staff monitor billing verifications and emergency infrastructure tickets <strong>7 days a week</strong>, 08:00 to 22:00 Sri Lanka Time (IST).
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-storefront.layout>
