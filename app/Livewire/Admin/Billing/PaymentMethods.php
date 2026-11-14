<?php

namespace App\Livewire\Admin\Billing;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\PaymentMethod;
use App\Services\RbacService;
use Livewire\Component;

/**
 * Owner-managed payment instructions shown to clients on their invoice and checkout.
 * Configures bank accounts, wallets, currency, product category scoping, and logos.
 */
class PaymentMethods extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $type = 'bank_transfer';

    public string $label = '';

    public string $bankName = '';

    public string $branch = '';

    public string $accountHolder = '';

    public string $accountNumber = '';

    public string $currency = 'ANY';

    public string $logoPath = '';

    public array $allowedCategories = [];

    public ?int $minAmount = null;

    public ?int $maxAmount = null;

    public string $instructions = '';

    /** @var array<int, array{label: string, value: string}> */
    public array $details = [];

    public bool $isActive = true;

    public int $sortOrder = 0;

    /** Runs on every request, including every action call. */
    public function boot(): void
    {
        abort_unless(RbacService::can(auth()->user(), 'payment_methods.manage'), 403, 'Only the owner can manage payment methods.');
    }

    public function create(): void
    {
        $this->resetForm();
        $this->details = [['label' => 'SWIFT / Routing', 'value' => '']];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $method = PaymentMethod::findOrFail($id);

        $this->editingId = $method->id;
        $this->type = $method->type;
        $this->label = $method->label;
        $this->bankName = (string) $method->bank_name;
        $this->branch = (string) $method->branch;
        $this->accountHolder = (string) $method->account_holder;
        $this->accountNumber = (string) $method->account_number;
        $this->currency = $method->currency;
        $this->logoPath = (string) $method->logo_path;
        $this->allowedCategories = $method->allowed_categories ?? [];
        $this->minAmount = $method->min_amount_cents ? (int) ($method->min_amount_cents / 100) : null;
        $this->maxAmount = $method->max_amount_cents ? (int) ($method->max_amount_cents / 100) : null;
        $this->instructions = (string) $method->instructions;
        $this->details = array_values($method->details ?? []);
        $this->isActive = $method->is_active;
        $this->sortOrder = $method->sort_order;
        $this->showForm = true;
    }

    public function addRow(): void
    {
        if (count($this->details) < 12) {
            $this->details[] = ['label' => '', 'value' => ''];
        }
    }

    public function removeRow(int $index): void
    {
        unset($this->details[$index]);
        $this->details = array_values($this->details);
    }

    public function save(): void
    {
        $this->validate([
            'type' => 'required|in:'.implode(',', array_keys(PaymentMethod::TYPES)),
            'label' => 'required|string|max:120',
            'bankName' => 'nullable|string|max:128',
            'branch' => 'nullable|string|max:128',
            'accountHolder' => 'nullable|string|max:255',
            'accountNumber' => 'nullable|string|max:128',
            'currency' => 'required|in:ANY,USD,LKR',
            'logoPath' => 'nullable|string|max:512',
            'allowedCategories' => 'array',
            'allowedCategories.*' => 'string|in:'.implode(',', array_keys(PaymentMethod::PRODUCT_CATEGORIES)),
            'minAmount' => 'nullable|numeric|min:0',
            'maxAmount' => 'nullable|numeric|min:0',
            'instructions' => 'nullable|string|max:2000',
            'details' => 'array|max:12',
            'details.*.label' => 'nullable|string|max:80',
            'details.*.value' => 'nullable|string|max:300',
            'sortOrder' => 'integer|min:0|max:9999',
        ]);

        $customRows = collect($this->details)
            ->map(fn ($r) => ['label' => trim((string) ($r['label'] ?? '')), 'value' => trim((string) ($r['value'] ?? ''))])
            ->filter(fn ($r) => $r['label'] !== '' && $r['value'] !== '')
            ->values()
            ->all();

        $method = PaymentMethod::updateOrCreate(
            ['id' => $this->editingId],
            [
                'type' => $this->type,
                'label' => trim($this->label),
                'bank_name' => trim($this->bankName) ?: null,
                'branch' => trim($this->branch) ?: null,
                'account_holder' => trim($this->accountHolder) ?: null,
                'account_number' => trim($this->accountNumber) ?: null,
                'currency' => $this->currency,
                'logo_path' => trim($this->logoPath) ?: null,
                'allowed_categories' => empty($this->allowedCategories) ? null : array_values($this->allowedCategories),
                'min_amount_cents' => $this->minAmount ? (int) ($this->minAmount * 100) : null,
                'max_amount_cents' => $this->maxAmount ? (int) ($this->maxAmount * 100) : null,
                'instructions' => trim($this->instructions) ?: null,
                'details' => $customRows,
                'is_active' => $this->isActive,
                'sort_order' => $this->sortOrder,
            ]
        );

        CommerceAuditLog::record($this->editingId ? 'payment_method.updated' : 'payment_method.created', PaymentMethod::class, $method->id, [
            'label' => $method->label,
            'type' => $method->type,
            'currency' => $method->currency,
            'active' => $method->is_active,
        ]);

        $this->resetForm();
        $this->dispatch('success', 'Payment method saved.');
    }

    public function toggle(int $id): void
    {
        $method = PaymentMethod::findOrFail($id);
        $method->update(['is_active' => ! $method->is_active]);

        CommerceAuditLog::record('payment_method.toggled', PaymentMethod::class, $method->id, ['active' => $method->is_active]);
    }

    public function delete(int $id): void
    {
        $method = PaymentMethod::findOrFail($id);
        CommerceAuditLog::record('payment_method.deleted', PaymentMethod::class, $method->id, ['label' => $method->label]);
        $method->delete();

        $this->dispatch('success', 'Payment method deleted.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'showForm',
            'editingId',
            'label',
            'bankName',
            'branch',
            'accountHolder',
            'accountNumber',
            'logoPath',
            'allowedCategories',
            'minAmount',
            'maxAmount',
            'instructions',
            'details',
            'sortOrder',
        ]);
        $this->type = 'bank_transfer';
        $this->currency = 'ANY';
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.billing.payment-methods', [
            'methods' => PaymentMethod::orderBy('sort_order')->orderBy('id')->get(),
            'types' => PaymentMethod::TYPES,
            'categories' => PaymentMethod::PRODUCT_CATEGORIES,
        ]);
    }
}
