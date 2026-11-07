<x-storefront.layout title="Your cart">
    <div class="wrap">
        <div class="pagehead"><div><h1>Your cart</h1></div></div>

        @if ($lines->isEmpty())
            <p class="empty">Your cart is empty. <a href="{{ route('store.pricing') }}">Browse plans</a>.</p>
        @else
            <table class="cart">
                <caption class="sr-only">Items in your cart</caption>
                <thead>
                    <tr>
                        <th scope="col">Plan</th>
                        <th scope="col">Quantity</th>
                        <th scope="col" class="r">Price</th>
                        <th scope="col"><span class="sr-only">Remove</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td>
                                <strong>{{ $line['variant']?->name ?? 'Unavailable plan' }}</strong><br>
                                <small>{{ $line['variant']?->product?->name }} &middot; billed per {{ str_replace('_', ' ', $line['billing_type']) }}</small>
                                @if ($line['problem'])<div class="problem" role="alert">{{ $line['problem'] }}</div>@endif
                            </td>
                            <td>
                                <form method="post" action="{{ route('cart.update', $line['id']) }}" style="display:flex;gap:.4rem;align-items:center">
                                    @csrf @method('patch')
                                    <label class="sr-only" for="q-{{ $line['id'] }}">Quantity</label>
                                    <input id="q-{{ $line['id'] }}" type="number" name="quantity" min="1" max="10" value="{{ $line['quantity'] }}" style="width:5rem">
                                    <button class="btn small ghost" type="submit">Update</button>
                                </form>
                            </td>
                            <td class="r">
                                @if ($line['line_cents'] !== null)
                                    <strong>{{ \App\Support\Money::format($line['line_cents'], $currency) }}</strong>
                                    @if ($line['quantity'] > 1)<br><small>{{ \App\Support\Money::format($line['unit_cents'], $currency) }} each</small>@endif
                                @else
                                    &ndash;
                                @endif
                            </td>
                            <td class="r">
                                <form method="post" action="{{ route('cart.remove', $line['id']) }}">
                                    @csrf @method('delete')
                                    <button class="linkbtn" type="submit">Remove<span class="sr-only"> {{ $line['variant']?->name }}</span></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="checkout">
                <div>
                    <p style="color:var(--muted)">You will not be charged yet. Checkout creates your invoice, then you pay by one of our payment methods and upload your receipt.</p>
                    @if ($currency === 'LKR')
                        <p style="color:var(--muted)">Prices are in LKR. <a href="{{ request()->fullUrlWithQuery(['currency' => 'USD']) }}">Switch to USD</a>.</p>
                    @endif
                </div>
                <div class="panel">
                    <div class="total"><span>Total</span><span>{{ \App\Support\Money::format($total, $currency) }}</span></div>
                    <form method="post" action="{{ route('cart.checkout') }}" style="margin-top:1rem">
                        @csrf
                        <label for="notes">Notes for us (optional)</label>
                        <textarea id="notes" name="notes" maxlength="1000" style="min-height:5rem" placeholder="Preferred Linux distribution, extra requirements...">{{ old('notes') }}</textarea>
                        @error('notes')<div class="err">{{ $message }}</div>@enderror

                        <button class="btn" type="submit" style="width:100%;margin-top:1rem" @disabled(! $canCheckout)>
                            {{ auth()->check() ? 'Place order' : 'Sign in to place order' }}
                        </button>
                        @unless ($canCheckout)<div class="err">Fix the items marked above to continue.</div>@endunless
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-storefront.layout>
