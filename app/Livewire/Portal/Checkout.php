<?php

namespace App\Livewire\Portal;

use App\Models\Commerce\Invoice;
use App\Models\Commerce\PaymentMethod;
use App\Models\Commerce\PricingTier;
use App\Models\Commerce\ProductVariant;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\ProofStorageService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Two stages on one page:
 *   1. Pick a plan, place the order. The order and invoice are created with prices
 *      read from the catalog on the server.
 *   2. (?invoice=uuid) See the payment instructions the owner configured and upload
 *      the payment proof for review.
 */
class Checkout extends Component
{
    use WithFileUploads;

    // Everything that decides what the customer pays is locked: the browser cannot change it.
    #[Locked]
    public array $cartPlanIds = [];

    #[Locked]
    public ?ProductVariant $selectedVariant = null;

    #[Locked]
    public int $totalAmountCents = 0;

    #[Locked]
    public ?string $invoiceUuid = null;

    #[Locked]
    public ?Invoice $existingInvoice = null;

    public string $billingType = '30-day';

    public string $currency = 'USD';

    // Stage 2: payment proof
    public $proofFile;

    public string $paymentMethodUuid = '';

    public string $bankReference = '';

    public string $depositorName = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->invoiceUuid = request()->query('invoice');

        if ($this->invoiceUuid) {
            $invoice = Invoice::with(['order.items.productVariant', 'items'])
                ->where('uuid', $this->invoiceUuid)
                ->first();

            // Same answer for "does not exist" and "not yours".
            abort_unless($invoice && auth()->user()->can('view', $invoice), 404);

            $this->existingInvoice = $invoice;
            $this->selectedVariant = $invoice->order?->items?->first()?->productVariant;
            $this->currency = $invoice->currency;
            $this->totalAmountCents = $invoice->total_cents;

            return;
        }

        if (request()->query('currency')) {
            $this->currency = request()->query('currency') === 'LKR' ? 'LKR' : 'USD';
        }

        if (request()->query('billing')) {
            $this->billingType = (string) request()->query('billing');
        }

        if ($tierParam = request()->query('tier')) {
            $tier = PricingTier::where('uuid', $tierParam)->orWhere('id', is_numeric($tierParam) ? (int) $tierParam : 0)->first();
            if ($tier) {
                $this->billingType = $tier->billing_type;
            }
        }

        $planParam = request()->query('planId') ?? request()->query('plan') ?? request()->query('variant');
        $this->cartPlanIds = $planParam ? [(string) $planParam] : [];

        if ($this->cartPlanIds) {
            $first = $this->cartPlanIds[0];
            $this->selectedVariant = ProductVariant::with(['product', 'pricingTiers'])
                ->where('uuid', $first)
                ->orWhere('id', is_numeric($first) ? (int) $first : 0)
                ->orWhere('slug', $first)
                ->first();
        }

        // No plan chosen (or an unknown one): send the customer to pick one. Never guess a plan.
        if (! $this->selectedVariant) {
            session()->flash('error', 'Please choose a plan first.');
            $this->redirectRoute('store.index', navigate: true);

            return;
        }

        $this->recalculate();
    }

    public function selectBillingType(string $type): void
    {
        $this->billingType = $type;
        $this->recalculate();
    }

    public function selectCurrency(string $curr): void
    {
        $this->currency = $curr === 'LKR' ? 'LKR' : 'USD';
        $this->recalculate();
    }

    /** Display only. The real price is recomputed on the server when the order is placed. */
    private function recalculate(): void
    {
        $tiers = $this->selectedVariant?->pricingTiers?->where('is_active', true);
        $tier = $tiers?->firstWhere('billing_type', $this->billingType) ?? $tiers?->first();

        $this->totalAmountCents = $tier
            ? (int) ($this->currency === 'LKR' ? ($tier->price_lkr_cents ?: 0) : $tier->price_cents)
            : 0;
    }

    public function placeOrder(OrderService $orders)
    {
        $this->validate(['notes' => 'nullable|string|max:1000']);

        $team = currentTeam();
        if (! $team) {
            session()->flash('error', 'No active team found for this user account.');

            return;
        }

        if (! $this->selectedVariant) {
            session()->flash('error', 'Please choose a plan first.');

            return;
        }

        try {
            [, $invoice] = $orders->place(
                auth()->user(),
                $team,
                [['variant' => $this->selectedVariant, 'billing_type' => $this->billingType, 'quantity' => 1]],
                $this->currency,
                $this->notes ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', "Order placed. Invoice {$invoice->invoice_number} is ready: pay using the details below, then upload your proof.");

        return redirect()->route('portal.checkout', ['invoice' => $invoice->uuid]);
    }

    public function submitProof(ProofStorageService $proofs)
    {
        abort_unless($this->existingInvoice, 404);
        $this->authorize('uploadProof', $this->existingInvoice);

        $this->validate([
            'proofFile' => 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:5120',
            'paymentMethodUuid' => 'nullable|string|max:64',
            'bankReference' => 'nullable|string|max:100',
            'depositorName' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $method = $this->paymentMethodUuid
            ? PaymentMethod::active()->where('uuid', $this->paymentMethodUuid)->first()
            : null;

        $note = collect([
            $this->depositorName ? "Depositor: {$this->depositorName}" : null,
            $this->notes ? "Notes: {$this->notes}" : null,
        ])->filter()->implode(' | ');

        try {
            $proofs->store(
                $this->proofFile,
                $this->existingInvoice,
                auth()->user(),
                $method?->type ?? 'bank_transfer',
                $this->bankReference ?: null,
                $note ?: null,
            );
        } catch (ValidationException $e) {
            $this->addError('proofFile', collect($e->errors())->flatten()->first());

            return;
        }

        session()->flash('success', 'Payment proof received. Our team will verify it and email you. Servers are delivered within 24 to 72 hours after approval.');

        return redirect()->route('portal.billing');
    }

    public function render()
    {
        return view('livewire.portal.checkout', [
            'paymentMethods' => PaymentMethod::forCurrency($this->currency)->get(),
        ]);
    }
}
