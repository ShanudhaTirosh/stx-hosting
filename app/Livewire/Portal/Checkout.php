<?php

namespace App\Livewire\Portal;

use App\Models\Commerce\Invoice;
use App\Models\Commerce\InvoiceItem;
use App\Models\Commerce\Order;
use App\Models\Commerce\OrderItem;
use App\Models\Commerce\PaymentProof;
use App\Models\Commerce\ProductVariant;
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

    public function mount(): void
    {
        $planIdParam = request()->query('planId') ?? request()->query('plan');
        $planIdsParam = request()->query('planIds');

        if ($planIdsParam) {
            $this->cartPlanIds = explode(',', $planIdsParam);
        } elseif ($planIdParam) {
            $this->cartPlanIds = [$planIdParam];
        }

        // Default or find first variant
        if (!empty($this->cartPlanIds)) {
            $first = $this->cartPlanIds[0];
            $this->selectedVariant = ProductVariant::with(['product', 'pricingTiers'])
                ->where('id', $first)
                ->orWhere('slug', $first)
                ->first();
        }

        if (!$this->selectedVariant) {
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
        if (!$this->selectedVariant) {
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

        if (!$team) {
            session()->flash('error', 'No active team found for this user account.');
            return;
        }

        return DB::transaction(function () use ($user, $team) {
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
                'description' => ($this->selectedVariant ? $this->selectedVariant->name : 'Cloud VPS Node') . " ({$this->billingType} term)",
                'quantity' => 1,
                'unit_price_cents' => $this->totalAmountCents,
                'total_price_cents' => $this->totalAmountCents,
            ]);

            // 3. Create Invoice
            $invoiceNumber = 'INV-' . strtoupper(Str::random(8));
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
                'description' => ($this->selectedVariant ? $this->selectedVariant->name : 'Cloud VPS Node') . " ({$this->billingType} term)",
                'quantity' => 1,
                'unit_price_cents' => $this->totalAmountCents,
                'total_price_cents' => $this->totalAmountCents,
            ]);

            // 4. Store Payment Proof
            $tempPath = $this->proofFile->getRealPath();
            $sha256 = hash_file('sha256', $tempPath);
            $mimeType = $this->proofFile->getMimeType();
            $storedName = 'proof_' . Str::random(24) . '.' . $this->proofFile->getClientOriginalExtension();
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
                'team_id' => $team->id,
                'user_id' => $user->id,
                'file_path' => $storagePath,
                'sha256_hash' => $sha256,
                'original_filename' => $this->proofFile->getClientOriginalName(),
                'mime_type' => $mimeType,
                'file_size_bytes' => $this->proofFile->getSize(),
                'status' => 'pending',
                'bank_reference' => $this->bankReference,
                'depositor_name' => $this->depositorName,
            ]);

            session()->flash('success', "Order placed successfully! Invoice {$invoiceNumber} has been submitted with your payment proof. Our operations team will verify the payment and provision your server within our 24-72 hour SLA.");

            return redirect()->route('portal.billing');
        });
    }

    public function render()
    {
        return view('livewire.portal.checkout');
    }
}

