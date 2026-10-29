<div class="max-w-4xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Order Checkout & Payment</h1>
            <p class="text-xs text-neutral-400">Review your server specifications, view our bank deposit details, and upload your payment slip.</p>
        </div>
        <a href="{{ route('portal.services') }}" class="text-xs text-purple-400 hover:text-purple-300 font-semibold">&larr; Back to Portal</a>
    </div>

    @if (session('error'))
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-xs font-semibold">
            {{ session('error') }}
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
            </div>

            <!-- Payment Receipt Upload Form -->
            <form wire:submit.prevent="placeOrder" class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4 shadow-lg">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-800">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-200">Submit Payment Proof</h3>
                    <span class="text-[11px] text-amber-400 font-mono">Manual Verification Queue</span>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-neutral-300 mb-1">Upload Bank Transfer Slip / Receipt *</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-dashed border-neutral-700 rounded-xl hover:border-purple-500/50 transition-all bg-neutral-950">
                            <div class="space-y-2 text-center">
                                <svg class="mx-auto h-8 w-8 text-neutral-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-xs text-neutral-400 justify-center">
                                    <label class="relative cursor-pointer rounded-md font-bold text-purple-400 hover:text-purple-300 focus-within:outline-none">
                                        <span>Choose receipt file</span>
                                        <input type="file" wire:model="proofFile" class="sr-only" accept="image/png,image/jpeg,application/pdf">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-[10px] text-neutral-500 font-mono">JPG, PNG, PDF up to 10MB (Scrubbed & Encrypted)</p>
                            </div>
                        </div>
                        @if ($proofFile)
                            <div class="mt-2 p-2 bg-emerald-950/40 border border-emerald-500/30 rounded flex items-center justify-between text-xs text-emerald-300 font-mono">
                                <span>Selected: {{ $proofFile->getClientOriginalName() }} ({{ round($proofFile->getSize() / 1024) }} KB)</span>
                                <span class="text-[10px] uppercase font-bold text-emerald-400">Ready</span>
                            </div>
                        @endif
                        @error('proofFile') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-neutral-300 mb-1">Depositor / Sender Name</label>
                            <input 
                                type="text" 
                                wire:model="depositorName" 
                                placeholder="Name as shown on transfer" 
                                class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono"
                            />
                            @error('depositorName') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-neutral-300 mb-1">Bank Reference / Txn ID</label>
                            <input 
                                type="text" 
                                wire:model="bankReference" 
                                placeholder="e.g. TXN9872145" 
                                class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono"
                            />
                            @error('bankReference') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-300 mb-1">Order Notes (Optional)</label>
                        <textarea 
                            wire:model="notes" 
                            rows="2" 
                            placeholder="Preferred Linux distro, extra requirements..." 
                            class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono"
                        ></textarea>
                    </div>
                </div>

                <div class="pt-4 border-t border-neutral-800 flex justify-end">
                    <button 
                        type="submit" 
                        class="px-6 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg shadow-lg shadow-purple-600/30 transition-all flex items-center gap-2 text-xs"
                    >
                        <svg wire:loading.remove wire:target="placeOrder" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span wire:loading wire:target="placeOrder" class="loading loading-spinner loading-xs"></span>
                        Submit Order & Upload Receipt
                    </button>
                </div>
            </form>
        </div>

        <!-- Right 1 Col: Bank Account Transfer Instructions & SLA Card -->
        <div class="space-y-6">
            <!-- Bank Details -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4 shadow-lg">
                <div class="flex items-center gap-2 pb-2 border-b border-neutral-800">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-300">Bank Transfer Details</h3>
                </div>

                <div class="space-y-3 font-mono text-xs">
                    <div class="bg-neutral-950 p-3 rounded border border-neutral-800 space-y-2">
                        <div>
                            <span class="text-[10px] text-neutral-500 block uppercase">Bank Name</span>
                            <span class="text-white font-bold">Commercial Bank of Ceylon / Sampath Bank</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-neutral-500 block uppercase">Account Name</span>
                            <span class="text-white font-bold">STX HOSTING (PVT) LTD</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-neutral-500 block uppercase">Account Number</span>
                            <span class="text-emerald-400 font-bold select-all tracking-wider">8012 3456 7890</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-neutral-500 block uppercase">Branch</span>
                            <span class="text-white">Colombo Main Branch</span>
                        </div>
                    </div>

                    <p class="text-[11px] text-neutral-400 font-sans leading-relaxed">
                        Transfer the exact invoice total. Once transferred, save the receipt/screenshot and upload it using the form on the left.
                    </p>
                </div>
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

