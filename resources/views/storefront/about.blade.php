<x-storefront.layout title="About" description="Who runs {{ config('storefront.name') }} and how ordering works.">
    <div class="wrap">
        <div class="pagehead"><div><h1>About {{ config('storefront.name') }}</h1><p>Hosting from {{ config('storefront.parent_brand') }}, built for people who pay by bank transfer.</p></div></div>
        <div class="prose">
            <p>We sell cloud servers, app and website hosting, bot hosting and databases. Plans are priced in LKR and USD, and you can pay with a bank transfer or one of the other methods we list on your invoice.</p>

            <h2>How we work</h2>
            <p>Every payment is checked by a person. That keeps fraud out and means a human reads your receipt if something looks off. We tell you the reason for any rejection, and you can send a new receipt.</p>
            <p>Apps, bots and databases start after your payment is approved. VPS servers are prepared by hand, so they take longer: usually 24 to 72 hours.</p>

            <h2>What you manage yourself</h2>
            <ul>
                <li>Deployments from your GitHub repository, with environment variables, logs and rollbacks.</li>
                <li>Your domains and the HTTPS certificates for them.</li>
                <li>Backups, on a schedule you set.</li>
                <li>For a VPS: restart, a terminal, a file manager and usage graphs.</li>
            </ul>

            @if (! empty($claims))
                <h2>In numbers</h2>
                <dl class="facts">
                    @foreach ($claims as $label => $value)
                        <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
                    @endforeach
                </dl>
            @endif

            <p><a class="btn" href="{{ route('store.pricing') }}">See plans</a></p>
        </div>
    </div>
</x-storefront.layout>
