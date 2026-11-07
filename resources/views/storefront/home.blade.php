<x-storefront.layout>
    <section class="hero">
        <div class="wrap">
            <h1>Hosting you can pay for with a bank transfer.</h1>
            <p class="lede">Servers, app hosting, bots and databases, priced in rupees or dollars. You order, you pay the way you normally pay, a person checks your receipt, and your service goes live.</p>
            <div class="actions">
                <a class="btn" href="{{ route('store.pricing') }}">See all plans</a>
                <a class="btn ghost" href="#how">How ordering works</a>
            </div>

            @if ($index->isEmpty())
                <p class="empty" style="margin-top:2.5rem">Plans are being added. Check back soon, or <a href="{{ route('store.contact') }}">ask us what is coming</a>.</p>
            @else
                <ul class="index" aria-label="Plans by category">
                    @foreach ($index as $row)
                        <li>
                            <a href="{{ route($row['route']) }}">
                                <span class="name">{{ $row['label'] }}</span>
                                <span class="from">from <strong>{{ $row['from'] }}</strong>@if ($row['term']) / {{ $row['term'] }}@endif</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <section class="band ink" id="how">
        <div class="wrap">
            <h2>How ordering works</h2>
            <ol class="steps">
                <li><strong>Choose a plan</strong><p>Add what you need to the cart. Prices are final: what you see is what the invoice says.</p></li>
                <li><strong>Pay by the method on your invoice</strong><p>Bank transfer or another method we list. Keep the receipt.</p></li>
                <li><strong>We check your receipt</strong><p>A person reviews it. If it does not match, we tell you why and you can upload a new one.</p></li>
                <li><strong>Your service goes live</strong><p>Apps and databases start after approval. VPS servers are set up by hand, usually within 24 to 72 hours.</p></li>
            </ol>
        </div>
    </section>

    @if ($status['total'] > 0)
        <section class="band slim">
            <div class="wrap">
                <p style="margin:0">
                    @if ($status['down'] === 0)
                        All {{ $status['total'] }} monitored services are running.
                    @else
                        {{ $status['down'] }} of {{ $status['total'] }} monitored services have an open issue.
                    @endif
                    <a href="{{ route('status.public') }}">See the live status page</a>.
                </p>
            </div>
        </section>
    @endif
</x-storefront.layout>
