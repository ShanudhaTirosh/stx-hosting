<div class="max-w-4xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Order Checkout & Payment</h1>
            <p class="text-xs text-neutral-400">Place your order, pay using the details we show, then upload your payment proof.</p>
        </div>
        <a href="{{ route('portal.services') }}" class="text-xs text-purple-400 hover:text-purple-300 font-semibold">&larr; Back to Portal</a>
    </div>

    @if (session('error'))
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-xs font-semibold" role="alert">
            {{ session('error') }}
        </div>
    @endif

    @if (session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 rounded-xl text-xs font-semibold" role="status">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Left 2 Cols: Order Details & Payment Submission Form -->
        <div class="md:col-span-2 space-y-6">
            <!-- Selected Plan Card -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4 shadow-lg">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded bg-purple-950/60 text-purple-300 border border-purple-500/30 font-bold">
                            {{ $selectedVariant->product->name ?? 'High Performance VPS' }}
                        </span>
                        <h2 class="text-lg font-bold text-white mt-1">{{ $selectedVariant->name ?? 'Custom Cloud Node' }}</h2>
                    </div>

                    <div class="text-right">
                        <span class="text-xs text-neutral-400 block font-mono">Term Total</span>
                        <span class="text-xl font-extrabold text-emerald-400 font-mono">
                            @if ($currency === 'LKR')
                                LKR {{ number_format($totalAmountCents / 100, 2) }}
                            @else
                                ${{ number_format($totalAmountCents / 100, 2) }}
                            @endif
                        </span>
                    </div>
                </div>

                <!-- Hardware Specs -->
                @if ($selectedVariant)
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-neutral-950 p-3 rounded-lg border border-neutral-800/80 text-xs font-mono">
                        <div>
                            <span class="text-[10px] text-neutral-500 block uppercase">vCPU</span>
                            <span class="text-white font-bold">{{ $selectedVariant->vcpu }} Cores</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-neutral-500 block uppercase">RAM</span>
                            <span class="text-white font-bold">{{ $selectedVariant->ram_mb / 1024 }} GB ECC</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-neutral-500 block uppercase">NVMe SSD</span>
                            <span class="text-white font-bold">{{ $selectedVariant->disk_gb }} GB</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-neutral-500 block uppercase">Network</span>
                            <span class="text-white font-bold">1 Gbps</span>
                        </div>
                    </div>
                @endif

                @unless ($existingInvoice)
                <!-- Term and Currency Switchers -->
                <div class="grid grid-cols-2 gap-4 pt-3 border-t border-neutral-800">
                    <div>
                        <label class="block text-xs font-bold text-neutral-400 mb-1.5 uppercase font-mono">Billing Term</label>
                        <div class="flex items-center gap-1.5 bg-neutral-950 p-1 rounded-lg border border-neutral-800">
                            <button 
                                type="button" 
                                wire:click="selectBillingType('30-day')"
                                class="flex-1 py-1.5 rounded text-xs font-bold font-mono transition-all {{ $billingType === '30-day' ? 'bg-purple-600 text-white' : 'text-neutral-400 hover:text-white' }}"
                            >
                                30-Day
                            </button>
                            <button 
                                type="button" 
                                wire:click="selectBillingType('50-day')"
                                class="flex-1 py-1.5 rounded text-xs font-bold font-mono transition-all {{ $billingType === '50-day' ? 'bg-purple-600 text-white' : 'text-neutral-400 hover:text-white' }}"
                            >
                                50-Day
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-400 mb-1.5 uppercase font-mono">Payment Currency</label>
                        <div class="flex items-center gap-1.5 bg-neutral-950 p-1 rounded-lg border border-neutral-800">
                            <button 
                                type="button" 
                                wire:click="selectCurrency('USD')"
                                class="flex-1 py-1.5 rounded text-xs font-bold font-mono transition-all {{ $currency === 'USD' ? 'bg-purple-600 text-white' : 'text-neutral-400 hover:text-white' }}"
                            >
                                USD ($)
                            </button>
                            <button 
                                type="button" 
                                wire:click="selectCurrency('LKR')"
                                class="flex-1 py-1.5 rounded text-xs font-bold font-mono transition-all {{ $currency === 'LKR' ? 'bg-purple-600 text-white' : 'text-neutral-400 hover:text-white' }}"
                            >
                                LKR (Rs)
                            </button>
                        </div>
                    </div>
                </div>
                @endunless
            </div>

            @if (! $existingInvoice)
                <!-- Stage 1: place the order -->
                <form wire:submit.prevent="placeOrder" class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4 shadow-lg">
                    <div class="pb-3 border-b border-neutral-800">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-200">Place your order</h3>
                        <p class="text-[11px] text-neutral-400 mt-1">We create your invoice first. You pay after that and upload the proof on the next screen.</p>
                    </div>

                    <div>
                        <label for="order-notes" class="block text-xs font-bold text-neutral-300 mb-1">Order notes (optional)</label>
                        <textarea id="order-notes" wire:model="notes" rows="2" placeholder="Preferred Linux distro, extra requirements..." class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono"></textarea>
                        @error('notes') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-neutral-800 flex justify-end">
                        <button type="submit" wire:loading.attr="disabled" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-60 text-white font-bold rounded-lg shadow-lg shadow-purple-600/30 transition-all text-xs">
                            <span wire:loading.remove wire:target="placeOrder">Place order and get invoice</span>
                            <span wire:loading wire:target="placeOrder">Placing order...</span>
                        </button>
                    </div>
                </form>
            @else
                <!-- Stage 2: invoice and payment proof -->
                <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 flex flex-wrap items-center justify-between gap-3 text-xs font-mono">
                    <div>
                        <span class="text-[10px] text-neutral-500 block uppercase">Invoice</span>
                        <span class="text-white font-bold">{{ $existingInvoice->invoice_number }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-neutral-500 block uppercase">Status</span>
                        <span class="text-amber-300 font-bold">{{ str_replace('_', ' ', $existingInvoice->status) }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('invoices.show', $existingInvoice->uuid) }}" class="text-purple-400 hover:text-purple-300 font-semibold">View invoice</a>
                        <a href="{{ route('invoices.pdf', $existingInvoice->uuid) }}" class="text-purple-400 hover:text-purple-300 font-semibold">PDF</a>
                    </div>
                </div>

                @can('uploadProof', $existingInvoice)
                    <form wire:submit.prevent="submitProof" class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4 shadow-lg">
                        <div class="flex items-center justify-between pb-3 border-b border-neutral-800">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-200">Submit payment proof</h3>
                            <span class="text-[11px] text-amber-400 font-mono">Manual verification</span>
                        </div>

                        @if ($paymentMethods->count() > 1)
                            <div>
                                <label for="payment-method" class="block text-xs font-bold text-neutral-300 mb-1">Which payment method did you use?</label>
                                <select id="payment-method" wire:model="paymentMethodUuid" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                                    <option value="">Select a method</option>
                                    @foreach ($paymentMethods as $method)
                                        <option value="{{ $method->uuid }}">{{ $method->label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div>
                            <label for="proof-file" class="block text-xs font-bold text-neutral-300 mb-1">Receipt or screenshot *</label>
                            <input id="proof-file" type="file" wire:model="proofFile" accept="image/png,image/jpeg,image/webp,application/pdf" class="file-input file-input-sm w-full bg-neutral-950 border-neutral-700 text-xs">
                            <p class="text-[10px] text-neutral-500 font-mono mt-1">JPG, PNG, WEBP or PDF, up to 5 MB. Stored privately.</p>
                            @if ($proofFile)
                                <div class="mt-2 p-2 bg-emerald-950/40 border border-emerald-500/30 rounded text-xs text-emerald-300 font-mono">
                                    Selected: {{ $proofFile->getClientOriginalName() }} ({{ round($proofFile->getSize() / 1024) }} KB)
                                </div>
                            @endif
                            @error('proofFile') <span class="text-rose-400 text-xs block mt-1" role="alert">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="depositor" class="block text-xs font-bold text-neutral-300 mb-1">Sender name</label>
                                <input id="depositor" type="text" wire:model="depositorName" placeholder="Name shown on the transfer" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                                @error('depositorName') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="bank-ref" class="block text-xs font-bold text-neutral-300 mb-1">Reference / transaction ID</label>
                                <input id="bank-ref" type="text" wire:model="bankReference" placeholder="e.g. TXN9872145" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                                @error('bankReference') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="pt-4 border-t border-neutral-800 flex justify-end">
                            <button type="submit" wire:loading.attr="disabled" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-60 text-white font-bold rounded-lg shadow-lg shadow-purple-600/30 transition-all text-xs">
                                <span wire:loading.remove wire:target="submitProof">Upload proof</span>
                                <span wire:loading wire:target="submitProof">Uploading...</span>
                            </button>
                        </div>
                    </form>
                @else
                    <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 text-xs text-neutral-300">
                        @if ($existingInvoice->status === 'paid')
                            This invoice is paid. Thank you.
                        @else
                            This invoice is not open for a new proof right now.
                        @endif
                    </div>
                @endcan
            @endif
        </div>

        <!-- Right 1 Col: Bank Account Transfer Instructions & SLA Card -->
        <div class="space-y-6">
            <!-- Payment methods configured by the owner -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4 shadow-lg">
                <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-300 pb-2 border-b border-neutral-800">How to pay</h3>

                @forelse ($paymentMethods as $method)
                    <div class="space-y-2 font-mono text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-white font-bold">{{ $method->label }}</span>
                            <span class="text-[10px] uppercase text-neutral-500">{{ $method->typeLabel() }}</span>
                        </div>
                        @if (! empty($method->details))
                            <dl class="bg-neutral-950 p-3 rounded border border-neutral-800 space-y-2">
                                @foreach ($method->details as $row)
                                    <div>
                                        <dt class="text-[10px] text-neutral-500 uppercase">{{ $row['label'] ?? '' }}</dt>
                                        <dd class="text-emerald-400 font-bold select-all break-all">{{ $row['value'] ?? '' }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                        @if ($method->instructions)
                            <p class="text-[11px] text-neutral-400 font-sans leading-relaxed whitespace-pre-line">{{ $method->instructions }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-neutral-400 leading-relaxed">
                        Payment details for {{ $currency }} have not been published yet. Please contact support and we will send them to you.
                    </p>
                @endforelse

                <p class="text-[11px] text-neutral-400 font-sans leading-relaxed pt-2 border-t border-neutral-800">
                    Transfer the exact invoice total, keep the receipt, then upload it here.
                </p>
            </div>

            <!-- Fulfillment SLA Card -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-3 shadow-lg">
                <div class="flex items-center gap-2 text-amber-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <h4 class="text-xs font-bold uppercase tracking-wider">Provisioning SLA</h4>
                </div>
                <p class="text-xs text-neutral-300 leading-relaxed font-sans">
                    Servers are manually ordered from our certified infrastructure partners and provisioned by our engineering staff within <strong class="text-white">24 to 72 hours</strong>.
                </p>
                <div class="p-2.5 bg-neutral-950 rounded border border-neutral-800 text-[11px] text-neutral-400 font-mono space-y-1">
                    <div>1. Payment Verified &rarr; <span class="text-emerald-400">Approved</span></div>
                    <div>2. VPS Node Acquired &rarr; <span class="text-purple-400">Configuring</span></div>
                    <div>3. SSH Delivered &rarr; <span class="text-white font-bold">Customer Portal</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

