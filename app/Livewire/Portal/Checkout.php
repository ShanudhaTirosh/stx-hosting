<?php

namespace App\Livewire\Portal;

use App\Models\Commerce\Invoice;
use App\Models\Commerce\InvoiceItem;
use App\Models\Commerce\Order;
use App\Models\Commerce\OrderItem;
use App\Models\Commerce\PaymentProof;
use App\Models\Commerce\ProductVariant;
use App\Notifications\Commerce\OrderPlacedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class Checkout extends Component
{
    use WithFileUploads;

    public array $cartPlanIds = [];

    public ?ProductVariant $selectedVariant = null;

    public string $billingType = '30-day';

    public string $currency = 'USD';

    public int $totalAmountCents = 0;

    // Payment proof upload
    public $proofFile;

    public string $bankReference = '';

    public string $depositorName = '';

    public string $notes = '';

    public ?string $invoiceUuid = null;

    public ?Invoice $existingInvoice = null;

    public function mount(): void
    {
        $this->invoiceUuid = request()->query('invoice');
        if ($this->invoiceUuid) {
            $this->existingInvoice = Invoice::with(['order.items.productVariant', 'items'])->where('uuid', $this->invoiceUuid)->first();
            if ($this->existingInvoice) {
                $item = $this->existingInvoice->order?->items?->first();
                if ($item?->productVariant) {
                    $this->selectedVariant = $item->productVariant;
                }
                $this->currency = $this->existingInvoice->currency;
                $this->totalAmountCents = $this->existingInvoice->total_cents;

                return;
            }
        }

        if (request()->query('currency')) {
            $this->currency = request()->query('currency') === 'LKR' ? 'LKR' : 'USD';
        }

        if (request()->query('billing')) {
            $this->billingType = request()->query('billing');
        }

        if (request()->query('tier')) {
            $tier = \App\Models\Commerce\PricingTier::where('uuid', request()->query('tier'))
                ->orWhere('id', request()->query('tier'))
                ->first();
            if ($tier) {
                $this->billingType = $tier->billing_type;
            }
        }

        $planIdParam = request()->query('planId') ?? request()->query('plan') ?? request()->query('variant');
        $planIdsParam = request()->query('planIds');

        if ($planIdsParam) {
            $this->cartPlanIds = explode(',', $planIdsParam);
        } elseif ($planIdParam) {
            $this->cartPlanIds = [$planIdParam];
        }

        // Default or find first variant (supports UUID, ID, or Slug)
        if (! empty($this->cartPlanIds)) {
            $first = $this->cartPlanIds[0];
            $this->selectedVariant = ProductVariant::with(['product', 'pricingTiers'])
                ->where('uuid', $first)
                ->orWhere('id', $first)
                ->orWhere('slug', $first)
                ->first();
        }

        if (! $this->selectedVariant) {
            $this->selectedVariant = ProductVariant::with(['product', 'pricingTiers'])->first();
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
        $this->currency = $curr;
        $this->recalculate();
    }

    private function recalculate(): void
    {
        if (! $this->selectedVariant) {
            $this->totalAmountCents = 0;

            return;
        }

        $tier = $this->selectedVariant->pricingTiers
            ->where('billing_type', $this->billingType)
            ->where('is_active', true)
            ->first() ?? $this->selectedVariant->pricingTiers->first();

        if ($tier) {
            $this->totalAmountCents = ($this->currency === 'LKR')
                ? $tier->price_lkr_cents
                : $tier->price_cents;
        } else {
            $this->totalAmountCents = 1500; // default fallback $15.00
        }
    }

    public function placeOrder()
    {
        $this->validate([
            'proofFile' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'bankReference' => 'nullable|string|max:100',
            'depositorName' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();
        $team = currentTeam();

        if (! $team) {
            session()->flash('error', 'No active team found for this user account.');

            return;
        }

        return DB::transaction(function () use ($user, $team) {
            if ($this->existingInvoice) {
                $invoice = $this->existingInvoice;
                $order = $invoice->order;
                $invoice->update(['status' => 'payment_pending']);
                if ($order) {
                    $order->update(['status' => 'payment_submitted']);
                }
                $invoiceNumber = $invoice->invoice_number;
            } else {
                // 1. Create Order
                $order = Order::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'status' => 'payment_submitted',
                    'currency' => $this->currency,
                    'subtotal_cents' => $this->totalAmountCents,
                    'tax_cents' => 0,
                    'discount_cents' => 0,
                    'total_cents' => $this->totalAmountCents,
                    'customer_notes' => $this->notes ?: 'Order placed via self-service portal checkout.',
                ]);

                // 2. Create Order Item
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $this->selectedVariant ? $this->selectedVariant->id : null,
                    'item_type' => 'vps',
                    'description' => ($this->selectedVariant ? $this->selectedVariant->name : 'Cloud VPS Node')." ({$this->billingType} term)",
                    'quantity' => 1,
                    'unit_price_cents' => $this->totalAmountCents,
                    'total_price_cents' => $this->totalAmountCents,
                ]);

                // 3. Create Invoice
                $invoiceNumber = 'INV-'.strtoupper(Str::random(8));
                $invoice = Invoice::create([
                    'uuid' => (string) Str::uuid(),
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'invoice_number' => $invoiceNumber,
                    'status' => 'payment_pending',
                    'currency' => $this->currency,
                    'subtotal_cents' => $this->totalAmountCents,
                    'tax_cents' => 0,
                    'total_cents' => $this->totalAmountCents,
                    'due_at' => now()->addDays(2),
                ]);

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => ($this->selectedVariant ? $this->selectedVariant->name : 'Cloud VPS Node')." ({$this->billingType} term)",
                    'quantity' => 1,
                    'unit_price_cents' => $this->totalAmountCents,
                    'total_price_cents' => $this->totalAmountCents,
                ]);
            }

            // 4. Store Payment Proof
            $tempPath = $this->proofFile->getRealPath();
            $sha256 = hash_file('sha256', $tempPath);
            $mimeType = $this->proofFile->getMimeType();
            $storedName = 'proof_'.Str::random(24).'.'.$this->proofFile->getClientOriginalExtension();
            $storagePath = "payment_proofs/{$storedName}";

            // Pure-pixel re-encoding for raster images to scrub polyglots
            if (in_array($mimeType, ['image/jpeg', 'image/png'])) {
                if ($mimeType === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
                    $img = @imagecreatefromjpeg($tempPath);
                    if ($img) {
                        ob_start();
                        imagejpeg($img, null, 90);
                        $cleanData = ob_get_clean();
                        imagedestroy($img);
                        Storage::disk('local')->put($storagePath, $cleanData);
                    } else {
                        Storage::disk('local')->putFileAs('payment_proofs', $this->proofFile, $storedName);
                    }
                } elseif ($mimeType === 'image/png' && function_exists('imagecreatefrompng')) {
                    $img = @imagecreatefrompng($tempPath);
                    if ($img) {
                        ob_start();
                        imagepng($img, 8);
                        $cleanData = ob_get_clean();
                        imagedestroy($img);
                        Storage::disk('local')->put($storagePath, $cleanData);
                    } else {
                        Storage::disk('local')->putFileAs('payment_proofs', $this->proofFile, $storedName);
                    }
                } else {
                    Storage::disk('local')->putFileAs('payment_proofs', $this->proofFile, $storedName);
                }
            } else {
                Storage::disk('local')->putFileAs('payment_proofs', $this->proofFile, $storedName);
            }

            PaymentProof::create([
                'uuid' => (string) Str::uuid(),
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
                'payment_method' => 'bank_transfer',
                'transaction_reference' => $this->bankReference ?: null,
                'file_path' => $storagePath,
                'file_mime' => $mimeType,
                'file_size_bytes' => $this->proofFile->getSize(),
                'sha256_hash' => $sha256,
                'status' => 'pending_review',
                'admin_notes' => $this->depositorName ? "Depositor: {$this->depositorName}".($this->notes ? " | Notes: {$this->notes}" : '') : ($this->notes ? "Notes: {$this->notes}" : null),
            ]);

            try {
                if ($order) {
                    $user->notify(new OrderPlacedNotification($order, $invoice));
                }
            } catch (\Throwable $e) {
                logger()->warning("Could not send OrderPlacedNotification: {$e->getMessage()}");
            }

            session()->flash('success', "Order placed successfully! Invoice {$invoiceNumber} has been submitted with your payment proof. Our operations team will verify the payment and provision your server within our 24-72 hour SLA.");

            return redirect()->route('portal.billing');
        });
    }

    public function render()
    {
        return view('livewire.portal.checkout');
    }
}
