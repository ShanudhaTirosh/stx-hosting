<x-storefront.layout title="Contact" description="Talk to the team about plans, billing or a technical problem.">
    <div class="wrap">
        <div class="pagehead"><div><h1>Contact us</h1><p>Tell us what you need. We answer by email.</p></div></div>
        <div class="prose" style="max-width:none">
            <div class="checkout" style="margin-top:0">
                <form method="post" action="{{ route('store.contact.send') }}" class="formgrid" novalidate>
                    @csrf
                    <div class="hp" aria-hidden="true"><label for="website">Leave this field empty</label><input id="website" type="text" name="website" tabindex="-1" autocomplete="off"></div>

                    <div>
                        <label for="name">Your name</label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" style="width:100%">
                        @error('name')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="email">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" style="width:100%">
                        @error('email')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="department">What is it about?</label>
                        <select id="department" name="department" style="width:100%">
                            @foreach (['tech' => 'A technical problem', 'sales' => 'Choosing a plan', 'billing' => 'Billing or a payment', 'abuse' => 'Report abuse'] as $value => $text)
                                <option value="{{ $value }}" @selected(old('department', 'tech') === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="subject">Subject (optional)</label>
                        <input id="subject" type="text" name="subject" value="{{ old('subject') }}" style="width:100%">
                    </div>
                    <div class="full">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" required>{{ old('message') }}</textarea>
                        @error('message')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <div class="full"><button class="btn" type="submit">Send message</button></div>
                </form>

                <aside class="panel">
                    <h2 style="font-size:1.2rem;margin-bottom:.6rem">Other ways to reach us</h2>
                    <ul style="padding-left:1.1rem;margin:0">
                        @if (config('storefront.support.discord'))<li><a href="{{ config('storefront.support.discord') }}">Discord</a></li>@endif
                        @if (config('storefront.support.whatsapp'))<li><a href="{{ config('storefront.support.whatsapp') }}">WhatsApp</a></li>@endif
                        @if (config('storefront.support.email'))<li><a href="mailto:{{ config('storefront.support.email') }}">{{ config('storefront.support.email') }}</a></li>@endif
                    </ul>
                </aside>
            </div>
        </div>
    </div>
</x-storefront.layout>
