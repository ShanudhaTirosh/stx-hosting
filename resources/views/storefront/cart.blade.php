<x-storefront.layout title="Your Order Cart">
    <div class="wrap band">
        <div class="pagehead" data-reveal>
            <div>
                <span class="pill signal" style="margin-bottom:1rem">
                    <span class="pill-dot"></span> Secure Commercial Checkout
                </span>
                <h1>Your Order Cart</h1>
                <p class="lead">Review your selected cloud hosting services and billing cycles before generating your official invoice.</p>
            </div>
            <div>
                <a href="{{ route('store.pricing') }}" class="btn ghost small">&larr; Continue Shopping</a>
            </div>
        </div>

        @if ($lines->isEmpty())
            <div class="card empty" style="text-align:center;padding:4rem 1.5rem;margin-top:2rem" data-reveal>
                <h3>Your shopping cart is currently empty</h3>
                <p>Select a Cloud VPS, Docker app container, or email hosting package to get started.</p>
                <a href="{{ route('store.pricing') }}" class="btn">Explore Server Plans &rarr;</a>
            </div>
        @else
            <div class="cart-wrap" data-reveal>
                <div>
                    <div class="ledger-wrap">
                        <table class="cart" style="width:100%;border-collapse:collapse">
                            <caption class="sr-only">Items in your cart</caption>
                            <thead>
                                <tr>
                                    <th scope="col" style="padding:1rem 1.25rem;background:var(--paper-soft);border-bottom:2px solid var(--ink);text-align:left">Plan &amp; Tier</th>
                                    <th scope="col" style="padding:1rem 1.25rem;background:var(--paper-soft);border-bottom:2px solid var(--ink);text-align:left">Qty</th>
                                    <th scope="col" style="padding:1rem 1.25rem;background:var(--paper-soft);border-bottom:2px solid var(--ink);text-align:right">Price</th>
                                    <th scope="col" style="padding:1rem 1.25rem;background:var(--paper-soft);border-bottom:2px solid var(--ink);text-align:right"><span class="sr-only">Action</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lines as $line)
                                    <tr>
                                        <td style="padding:1.25rem;border-bottom:1px solid var(--line);vertical-align:top">
                                            <strong style="font-size:1.1rem;color:var(--ink)">{{ $line['variant']?->name ?? 'Unavailable plan' }}</strong><br>
                                            <small style="color:var(--muted)">{{ $line['variant']?->product?->name }} &middot; billed per {{ str_replace('_', ' ', $line['billing_type']) }}</small>
                                            @if ($line['problem'])
                                                <div class="pill" style="background:#fee2e2;color:#991b1b;margin-top:0.4rem" role="alert">{{ $line['problem'] }}</div>
                                            @endif
                                        </td>
                                        <td style="padding:1.25rem;border-bottom:1px solid var(--line);vertical-align:middle">
                                            @if (($line['variant']?->product?->category ?? '') === 'vps')
                                                <form method="post" action="{{ route('cart.update', $line['id']) }}" style="display:flex;gap:.4rem;align-items:center">
                                                    @csrf @method('patch')
                                                    <label class="sr-only" for="q-{{ $line['id'] }}">Quantity</label>
                                                    <input id="q-{{ $line['id'] }}" type="number" name="quantity" min="1" max="10" value="{{ $line['quantity'] }}" style="width:4.5rem;padding:0.35rem 0.5rem;font-size:0.9rem">
                                                    <button class="btn small ghost" type="submit" style="padding:0.3rem 0.6rem;font-size:0.8rem">Update</button>
                                                </form>
                                            @else
                                                <span class="pill ink">1</span>
                                            @endif
                                        </td>
                                        <td style="padding:1.25rem;border-bottom:1px solid var(--line);vertical-align:middle;text-align:right">
                                            @if ($line['line_cents'] !== null)
                                                <strong style="font:700 1.2rem/1 var(--display);color:var(--ink)">{{ \App\Support\Money::format($line['line_cents'], $currency) }}</strong>
                                                @if ($line['quantity'] > 1)
                                                    <br><small style="color:var(--muted);font-size:0.8rem">{{ \App\Support\Money::format($line['unit_cents'], $currency) }} each</small>
                                                @endif
                                            @else
                                                &ndash;
                                            @endif
                                        </td>
                                        <td style="padding:1.25rem;border-bottom:1px solid var(--line);vertical-align:middle;text-align:right">
                                            <form method="post" action="{{ route('cart.remove', $line['id']) }}">
                                                @csrf @method('delete')
                                                <button class="linkbtn" type="submit" style="color:var(--rose);font-size:0.88rem;font-weight:600">
                                                    Remove<span class="sr-only"> {{ $line['variant']?->name }}</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top:1.5rem;padding:1.25rem;background:var(--paper-soft);border:1px solid var(--line);border-radius:var(--radius-sm)">
                        <p style="margin:0;font-size:0.92rem;color:var(--muted);line-height:1.6">
                            <strong>No payment is taken at this moment.</strong> Placing your order generates an official PDF invoice containing our Commercial Bank, Sampath Bank, HNB, and BOC account numbers. Transfer the exact balance via mobile banking, upload your slip, and your service goes live.
                        </p>
                    </div>
                </div>

                <!-- Order Summary Sidebar -->
                <div class="order-summary">
                    <h3 style="font:700 1.3rem/1.2 var(--display);margin-bottom:1.25rem;padding-bottom:0.75rem;border-bottom:1px solid var(--line)">Order Summary</h3>
                    
                    <div style="display:flex;justify-content:space-between;color:var(--muted);margin-bottom:0.6rem;font-size:0.95rem">
                        <span>Currency</span>
                        <strong>{{ $currency }}</strong>
                    </div>
                    
                    <div class="order-total-row">
                        <span>Total Due</span>
                        <span style="color:var(--teal)">{{ \App\Support\Money::format($total, $currency) }}</span>
                    </div>

                    <form method="post" action="{{ route('cart.checkout') }}" style="margin-top:1.5rem">
                        @csrf
                        <label for="notes" style="font-weight:600;font-size:0.9rem;margin-bottom:0.4rem;display:block">
                            Deployment Notes / Distribution (Optional)
                        </label>
                        <textarea id="notes" name="notes" maxlength="1000" rows="3" placeholder="Preferred OS (Ubuntu 24.04, Debian 12, Rocky 9), hostname requirements, or special notes...">{{ old('notes') }}</textarea>
                        @error('notes')<div class="pill" style="background:#fee2e2;color:#991b1b;margin-top:0.4rem">{{ $message }}</div>@enderror

                        <button class="btn full" type="submit" style="margin-top:1.5rem" @disabled(! $canCheckout)>
                            {{ auth()->check() ? 'Generate Official Invoice &rarr;' : 'Sign In to Complete Order &rarr;' }}
                        </button>
                        
                        @unless ($canCheckout)
                            <div class="pill" style="background:#fee2e2;color:#991b1b;margin-top:0.75rem;width:100%;text-align:center">
                                Please resolve line item issues to proceed.
                            </div>
                        @endunless
                    </form>

                    <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--line-subtle);font-size:0.8rem;color:var(--muted);text-align:center">
                        🔒 Enterprise Bank Transfer Verification &bull; 100% Secure
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-storefront.layout>
