<?php

namespace App\Livewire\Portal;

use App\Models\Commerce\Invoice;
use App\Models\Commerce\PaymentMethod;
use App\Models\Commerce\PaymentProof;
use App\Models\Commerce\PricingTier;
use App\Models\Commerce\ProductVariant;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\ProofStorageService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Two stages on one page:
 *   1. Pick a plan, place the order. Generates unique payment reference code.
 *   2. (?invoice=uuid) See configured payment method bank details with copy buttons,
 *      upload slip (PNG/JPG/PDF) with camera or gallery, and view live AI verification status.
 */
class Checkout extends Component
{
    use WithFileUploads;

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
            $invoice = Invoice::with(['order.items.productVariant.product', 'items', 'proofs'])
                ->where('uuid', $this->invoiceUuid)
                ->first();

            abort_unless($invoice && auth()->user()->can('view', $invoice), 404);

            $this->existingInvoice = $invoice;
            $this->selectedVariant = $invoice->order?->items?->first()?->productVariant;
            $this->currency = $invoice->currency;
            $this->totalAmountCents = $invoice->total_cents;

            // Ensure payment reference exists
            if (! $invoice->payment_reference) {
                $ref = 'STX-' . strtoupper(substr(md5($invoice->invoice_number . uniqid()), 0, 8));
                $invoice->update(['payment_reference' => $ref]);
                $invoice->order?->update(['payment_reference' => $ref]);
            }

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

            // Generate unique, collision-free payment reference code
            $paymentRef = 'STX-' . strtoupper(Str::random(8));
            $invoice->update(['payment_reference' => $paymentRef]);
            $invoice->order?->update(['payment_reference' => $paymentRef]);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', "Order placed! Invoice {$invoice->invoice_number} is ready. Transfer the exact amount using reference {$paymentRef}, then upload your receipt slip below.");

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
            $proof = $proofs->store(
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

        $this->reset(['proofFile', 'bankReference', 'depositorName', 'notes']);
        $this->existingInvoice->refresh();

        session()->flash('success', 'Payment slip uploaded successfully! PayVerify AI verification is running. Watch live status below.');

        return redirect()->route('portal.checkout', ['invoice' => $this->existingInvoice->uuid]);
    }

    public function render()
    {
        $category = $this->selectedVariant?->product?->category;
        $methods = PaymentMethod::forCheckout($this->currency, $category)->get();
        $latestProof = $this->existingInvoice?->proofs()->latest()->first();

        return view('livewire.portal.checkout', [
            'paymentMethods' => $methods,
            'latestProof' => $latestProof,
            'paymentReference' => $this->existingInvoice?->payment_reference ?: ($this->existingInvoice?->invoice_number ?? 'STX-PENDING'),
        ]);
    }
}
