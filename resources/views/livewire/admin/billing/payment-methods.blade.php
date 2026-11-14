<div class="max-w-5xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Payment Methods & Accounts</h1>
            <p class="text-xs text-neutral-400">Configure bank accounts, wallets, product limitations, and transfer instructions shown to customers.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.billing.verification-settings') }}" class="px-3.5 py-2 bg-neutral-800 hover:bg-neutral-700 text-purple-300 font-semibold rounded-lg text-xs border border-neutral-700 transition-all">
                &#9881; AI Verification Settings
            </a>
            <button type="button" wire:click="create" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs shadow-lg shadow-purple-600/20">
                + Add Payment Method
            </button>
        </div>
    </div>

    @if ($showForm)
        <form wire:submit.prevent="save" class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-5 shadow-xl">
            <div class="flex items-center justify-between pb-3 border-b border-neutral-800">
                <h2 class="text-sm font-bold uppercase tracking-wider text-purple-400">{{ $editingId ? 'Edit Payment Method' : 'Configure New Payment Method' }}</h2>
                <span class="text-[11px] text-neutral-500 font-mono">Owner Management</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label for="pm-label" class="block text-xs font-bold text-neutral-300 mb-1">Display Name *</label>
                    <input id="pm-label" type="text" wire:model="label" placeholder="e.g. Commercial Bank of Ceylon (Direct Wire / Online / CRM)" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                    @error('label') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="pm-type" class="block text-xs font-bold text-neutral-300 mb-1">Channel Type *</label>
                    <select id="pm-type" wire:model="type" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                        @foreach ($types as $value => $name)
                            <option value="{{ $value }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Dedicated Bank Details Grid -->
            <div class="bg-neutral-950 p-4 rounded-xl border border-neutral-800 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-400 font-mono">Bank Account / Destination Credentials</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="pm-bank" class="block text-xs font-bold text-neutral-300 mb-1">Bank / Institution Name</label>
                        <input id="pm-bank" type="text" wire:model="bankName" placeholder="e.g. Commercial Bank of Ceylon PLC" class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white rounded text-xs">
                        @error('bankName') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="pm-branch" class="block text-xs font-bold text-neutral-300 mb-1">Branch / Location</label>
                        <input id="pm-branch" type="text" wire:model="branch" placeholder="e.g. Foreign Branch / Head Office" class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white rounded text-xs">
                        @error('branch') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="pm-holder" class="block text-xs font-bold text-neutral-300 mb-1">Beneficiary / Account Holder Name</label>
                        <input id="pm-holder" type="text" wire:model="accountHolder" placeholder="e.g. STX ENTERPRISES (PVT) LTD" class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white rounded text-xs font-mono">
                        @error('accountHolder') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="pm-accno" class="block text-xs font-bold text-neutral-300 mb-1">Account / Wallet Number</label>
                        <input id="pm-accno" type="text" wire:model="accountNumber" placeholder="e.g. 8001234567" class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white rounded text-xs font-mono font-bold text-emerald-400">
                        @error('accountNumber') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <!-- Currency, Scoping & Limits -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="pm-currency" class="block text-xs font-bold text-neutral-300 mb-1">Currency</label>
                    <select id="pm-currency" wire:model="currency" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                        <option value="ANY">Any Currency</option>
                        <option value="LKR">LKR (Sri Lankan Rupee)</option>
                        <option value="USD">USD ($)</option>
                    </select>
                </div>
                <div>
                    <label for="pm-logo" class="block text-xs font-bold text-neutral-300 mb-1">Logo URL or Icon Path</label>
                    <input id="pm-logo" type="text" wire:model="logoPath" placeholder="https://... or /images/banks/combank.svg" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                </div>
                <div>
                    <label for="pm-sort" class="block text-xs font-bold text-neutral-300 mb-1">Display Sort Order</label>
                    <input id="pm-sort" type="number" min="0" wire:model="sortOrder" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                </div>
            </div>

            <!-- Product Category Scoping -->
            <div class="bg-neutral-950 p-4 rounded-xl border border-neutral-800 space-y-2">
                <span class="block text-xs font-bold text-neutral-300 uppercase tracking-wider font-mono">Limit to Product Types (Leave all unchecked for All Products)</span>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-1">
                    @foreach ($categories as $catKey => $catLabel)
                        <label class="flex items-center gap-2 text-xs text-neutral-300 cursor-pointer bg-neutral-900 p-2 rounded-lg border border-neutral-800 hover:border-purple-500/50 transition-all">
                            <input type="checkbox" wire:model="allowedCategories" value="{{ $catKey }}" class="checkbox checkbox-xs checkbox-primary">
                            <span>{{ $catLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Custom Details Repeater -->
            <div class="space-y-2">
                <span class="block text-xs font-bold text-neutral-300 font-mono">Additional Details (SWIFT code, IBAN, WhatsApp verification line, etc.)</span>
                @foreach ($details as $i => $row)
                    <div class="flex gap-2" wire:key="detail-{{ $i }}">
                        <input type="text" wire:model="details.{{ $i }}.label" aria-label="Detail label" placeholder="Label (e.g. SWIFT Code)" class="input input-sm w-1/3 bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                        <input type="text" wire:model="details.{{ $i }}.value" aria-label="Detail value" placeholder="Value (e.g. CCEYLKLX)" class="input input-sm flex-1 bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        <button type="button" wire:click="removeRow({{ $i }})" class="text-xs text-rose-400 hover:text-rose-300 px-2" aria-label="Remove row">&times;</button>
                    </div>
                @endforeach
                <button type="button" wire:click="addRow" class="text-xs text-purple-400 hover:text-purple-300 font-semibold">+ Add extra detail row</button>
            </div>

            <!-- Transfer Instructions -->
            <div>
                <label for="pm-instr" class="block text-xs font-bold text-neutral-300 mb-1">Payment Instructions (Markdown supported)</label>
                <textarea id="pm-instr" wire:model="instructions" rows="3" placeholder="Please put the unique Payment Reference in the transaction remarks when transferring funds..." class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs leading-relaxed font-mono"></textarea>
                @error('instructions') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-neutral-800">
                <label class="flex items-center gap-2 text-xs text-neutral-300 font-semibold cursor-pointer">
                    <input type="checkbox" wire:model="isActive" class="checkbox checkbox-sm checkbox-primary"> Enabled (Visible to customers at checkout)
                </label>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="cancel" class="px-4 py-2 text-xs text-neutral-400 hover:text-white">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs shadow-lg shadow-purple-600/30">
                        {{ $editingId ? 'Save Changes' : 'Create Payment Method' }}
                    </button>
                </div>
            </div>
        </form>
    @endif

    <!-- Payment Methods List -->
    <div class="space-y-3">
        @forelse ($methods as $method)
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 flex flex-wrap items-center justify-between gap-4 shadow-md transition-all hover:border-neutral-700" wire:key="method-{{ $method->id }}">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2.5">
                        @if ($method->logo_path)
                            <img src="{{ $method->logo_path }}" alt="{{ $method->label }}" class="w-6 h-6 object-contain rounded">
                        @endif
                        <h3 class="text-sm font-bold text-white">{{ $method->label }}</h3>
                        <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded bg-neutral-800 text-neutral-400 border border-neutral-700">
                            {{ $method->typeLabel() }}
                        </span>
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded {{ $method->is_active ? 'bg-emerald-950/60 text-emerald-300 border border-emerald-500/30' : 'bg-neutral-800 text-neutral-400' }}">
                            {{ $method->is_active ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>

                    <div class="text-xs text-neutral-400 font-mono flex flex-wrap items-center gap-3">
                        @if ($method->bank_name)
                            <span>Bank: <strong class="text-neutral-200">{{ $method->bank_name }}</strong></span>
                        @endif
                        @if ($method->account_number)
                            <span>A/C: <strong class="text-emerald-400">{{ $method->account_number }}</strong></span>
                        @endif
                        @if ($method->account_holder)
                            <span>Holder: <strong class="text-neutral-300">{{ $method->account_holder }}</strong></span>
                        @endif
                        <span>Currency: <strong class="text-purple-300">{{ $method->currency }}</strong></span>
                    </div>

                    @if (! empty($method->allowed_categories))
                        <div class="flex items-center gap-1.5 pt-1">
                            <span class="text-[10px] text-neutral-500 font-mono">Scoped to:</span>
                            @foreach ($method->allowed_categories as $cat)
                                <span class="text-[10px] bg-purple-950/60 text-purple-300 px-1.5 py-0.5 rounded font-mono border border-purple-500/20">
                                    {{ $categories[$cat] ?? $cat }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-3 text-xs">
                    <button type="button" wire:click="toggle({{ $method->id }})" class="px-3 py-1.5 rounded-lg border border-neutral-700 text-neutral-300 hover:text-white hover:bg-neutral-800 transition-all">
                        {{ $method->is_active ? 'Disable' : 'Enable' }}
                    </button>
                    <button type="button" wire:click="edit({{ $method->id }})" class="px-3 py-1.5 rounded-lg bg-neutral-800 text-purple-300 hover:text-purple-200 hover:bg-neutral-700 transition-all font-semibold">
                        Edit
                    </button>
                    <button type="button" wire:click="delete({{ $method->id }})" wire:confirm="Delete this payment method?" class="px-3 py-1.5 rounded-lg text-rose-400 hover:text-rose-300 hover:bg-rose-950/30 transition-all">
                        Delete
                    </button>
                </div>
            </div>
        @empty
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-8 text-center text-xs text-neutral-400 space-y-2">
                <p class="font-bold text-neutral-300">No payment methods configured yet.</p>
                <p>Click "Add Payment Method" above to configure your bank accounts and instructions.</p>
            </div>
        @endforelse
    </div>
</div>
