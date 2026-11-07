<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Commerce\CartService;
use App\Services\Commerce\OrderService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(CartService $cart)
    {
        return view('storefront.cart', [
            'lines' => $cart->lines(),
            'total' => $cart->totalCents(),
            'currency' => $cart->currency(),
            'canCheckout' => $cart->canCheckout(),
        ]);
    }

    public function add(Request $request, CartService $cart)
    {
        $data = $request->validate([
            'variant' => 'required|string|max:36',
            'billing_type' => 'nullable|string|max:32',
            'quantity' => 'nullable|integer|min:1|max:'.CartService::MAX_QTY,
        ]);

        $cart->add($data['variant'], $data['billing_type'] ?? null, (int) ($data['quantity'] ?? 1));

        return back()->with('success', 'Added to your cart. Review it and check out when you are ready.');
    }

    public function update(Request $request, CartService $cart, string $line)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:0|max:'.CartService::MAX_QTY]);
        $cart->setQuantity($line, (int) $data['quantity']);

        return redirect()->route('cart.show');
    }

    public function remove(CartService $cart, string $line)
    {
        $cart->remove($line);

        return redirect()->route('cart.show');
    }

    public function checkout(Request $request, CartService $cart, OrderService $orders)
    {
        $request->validate(['notes' => 'nullable|string|max:1000']);

        if (! $request->user()) {
            // The cart lives in the session and survives sign-in; come back to it afterwards.
            session(['url.intended' => route('cart.show')]);

            return redirect()->route('login')->with('error', 'Sign in or create an account to place your order. Your cart is saved.');
        }

        if (! $cart->canCheckout()) {
            return redirect()->route('cart.show')->with('error', 'Some items in your cart cannot be ordered. Remove them or change the currency.');
        }

        $team = currentTeam();
        if (! $team) {
            return redirect()->route('cart.show')->with('error', 'Your account has no active team. Contact support.');
        }

        try {
            [, $invoice] = $orders->place($request->user(), $team, $cart->orderLines(), $cart->currency(), $request->input('notes'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('cart.show')->with('error', $e->getMessage());
        }

        $cart->clear();

        return redirect()->route('portal.checkout', ['invoice' => $invoice->uuid])
            ->with('success', "Order placed. Invoice {$invoice->invoice_number} is ready: pay using the details below, then upload your proof.");
    }
}
