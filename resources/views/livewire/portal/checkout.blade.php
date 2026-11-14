<div class="max-w-5xl mx-auto p-6 space-y-6" @if ($latestProof && $latestProof->isVerifying()) wire:poll.4s @endif>
    <!-- Page Header -->
    <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Checkout & Payment</h1>
            <p class="text-xs text-neutral-400">Review your order, transfer the exact amount using your payment code, and upload the slip.</p>
        </div>
        <a href="{{ route('portal.services') }}" class="text-xs text-purple-400 hover:text-purple-300 font-semibold">&larr; Back to Portal</a>
    </div>

    <!-- Alert Messages -->
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

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left 7 Cols: Order Summary & Slip Upload Form -->
        <div class="lg:col-span-7 space-y-6">
            <!-- Selected Plan Card -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4 shadow-xl">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded bg-purple-950/60 text-purple-300 border border-purple-500/30 font-bold">
                            {{ $selectedVariant->product->name ?? 'Cloud Infrastructure' }}
                        </span>
                        <h2 class="text-lg font-bold text-white mt-1">{{ $selectedVariant->name ?? 'Custom Cloud Node' }}</h2>
                    </div>

                    <div class="text-right">
                        <span class="text-xs text-neutral-400 block font-mono">Amount Due</span>
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
                            <label class="block text-xs font-bold text-neutral-400 mb-1.5 uppercase font-mono">Currency</label>
                            <div class="flex items-center gap-1.5 bg-neutral-950 p-1 rounded-lg border border-neutral-800">
                                <button 
                                    type="button" 
                                    wire:click="selectCurrency('LKR')"
                                    class="flex-1 py-1.5 rounded text-xs font-bold font-mono transition-all {{ $currency === 'LKR' ? 'bg-purple-600 text-white' : 'text-neutral-400 hover:text-white' }}"
                                >
                                    LKR (Rs)
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="selectCurrency('USD')"
                                    class="flex-1 py-1.5 rounded text-xs font-bold font-mono transition-all {{ $currency === 'USD' ? 'bg-purple-600 text-white' : 'text-neutral-400 hover:text-white' }}"
                                >
                                    USD ($)
                                </button>
                            </div>
                        </div>
                    </div>
                @endunless
            </div>

            @if (! $existingInvoice)
                <!-- Stage 1: Place the order and get invoice -->
                <form wire:submit.prevent="placeOrder" class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4 shadow-xl">
                    <div class="pb-3 border-b border-neutral-800">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-200">1. Place Order</h3>
                        <p class="text-[11px] text-neutral-400 mt-0.5">We create your order and generate your unique bank transfer reference code.</p>
                    </div>

                    <div>
                        <label for="order-notes" class="block text-xs font-bold text-neutral-300 mb-1">Order Notes (Optional)</label>
                        <textarea id="order-notes" wire:model="notes" rows="2" placeholder="Preferred OS distribution, hostname, or custom instructions..." class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono"></textarea>
                        @error('notes') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-3 border-t border-neutral-800 flex justify-end">
                        <button type="submit" wire:loading.attr="disabled" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-60 text-white font-bold rounded-lg shadow-lg shadow-purple-600/30 transition-all text-xs">
                            <span wire:loading.remove wire:target="placeOrder">Generate Invoice & Payment Details &rarr;</span>
                            <span wire:loading wire:target="placeOrder">Generating Invoice...</span>
                        </button>
                    </div>
                </form>
            @else
                <!-- Stage 2: Payment Details, Reference Code & Slip Upload -->
                <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4 shadow-xl">
                    <div class="flex flex-wrap items-center justify-between gap-3 text-xs font-mono pb-3 border-b border-neutral-800">
                        <div>
                            <span class="text-[10px] text-neutral-500 block uppercase">Invoice Number</span>
                            <span class="text-white font-bold">{{ $existingInvoice->invoice_number }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-neutral-500 block uppercase">Status</span>
                            <span class="font-bold {{ $existingInvoice->status === 'paid' ? 'text-emerald-400' : 'text-amber-400' }}">
                                {{ strtoupper(str_replace('_', ' ', $existingInvoice->status)) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('invoices.show', $existingInvoice->uuid) }}" target="_blank" class="text-purple-400 hover:text-purple-300 font-semibold underline">View Invoice</a>
                            <a href="{{ route('invoices.pdf', $existingInvoice->uuid) }}" target="_blank" class="text-purple-400 hover:text-purple-300 font-semibold underline">PDF Download</a>
                        </div>
                    </div>

                    <!-- Unique Payment Reference Highlight Box -->
                    <div class="p-4 bg-purple-950/40 border border-purple-500/30 rounded-xl space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-mono uppercase font-bold text-purple-300">Your Unique Transfer Reference</span>
                            <span class="text-[10px] text-neutral-400 font-mono">Put this in your bank transfer note</span>
                        </div>
                        <div class="flex items-center justify-between gap-3 bg-neutral-950 p-2.5 rounded-lg border border-purple-500/20">
                            <span class="text-lg font-mono font-extrabold text-white tracking-wider select-all" id="payment-ref-code">
                                {{ $paymentReference }}
                            </span>
                            <button 
                                type="button" 
                                onclick="navigator.clipboard.writeText('{{ $paymentReference }}'); alert('Reference code copied to clipboard: {{ $paymentReference }}');"
                                class="px-3 py-1 bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold rounded shadow transition-all font-mono"
                            >
                                Copy Reference
                            </button>
                        </div>
                    </div>

                    <!-- Live AI Verification State Banner -->
                    @if ($latestProof)
                        @if ($latestProof->isVerifying())
                            <div class="p-4 bg-purple-950/50 border border-purple-500/40 rounded-xl space-y-2 text-xs">
                                <div class="flex items-center gap-2.5 text-purple-300 font-bold">
                                    <svg class="animate-spin h-4 w-4 text-purple-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span>PayVerify AI Verification in Progress...</span>
                                </div>
                                <p class="text-neutral-300 text-[11px] leading-relaxed">
                                    We are analyzing your uploaded slip to extract the amount, bank details, and transfer reference. This usually takes 5 to 15 seconds. This page will update automatically.
                                </p>
                            </div>
                        @elseif ($latestProof->isApproved() || $existingInvoice->status === 'paid')
                            <div class="p-4 bg-emerald-950/60 border border-emerald-500/40 rounded-xl space-y-2 text-xs">
                                <div class="flex items-center gap-2 text-emerald-300 font-bold">
                                    <span class="text-base">&#10003;</span>
                                    <span>Payment Verified & Approved!</span>
                                </div>
                                <p class="text-emerald-200/80 text-[11px]">
                                    Your bank deposit slip has been verified successfully. Your hosting service entitlement has been activated.
                                </p>
                                <div class="pt-2 flex items-center gap-3">
                                    <a href="{{ route('portal.services') }}" class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg text-xs shadow">
                                        View Active Services &rarr;
                                    </a>
                                </div>
                            </div>
                        @elseif ($latestProof->isPendingReview())
                            <div class="p-4 bg-amber-950/50 border border-amber-500/40 rounded-xl space-y-2 text-xs">
                                <div class="flex items-center gap-2 text-amber-300 font-bold">
                                    <span>&#9888; Slip Under Staff Review</span>
                                </div>
                                <p class="text-neutral-300 text-[11px] leading-relaxed">
                                    Your slip was received and is queued for manual confirmation by our billing team. You will receive an email as soon as it is approved.
                                </p>
                            </div>
                        @elseif ($latestProof->isRejected())
                            <div class="p-4 bg-rose-950/60 border border-rose-500/40 rounded-xl space-y-2 text-xs">
                                <div class="flex items-center gap-2 text-rose-300 font-bold">
                                    <span>&#9888; Slip Verification Failed</span>
                                </div>
                                <p class="text-rose-200 text-[11px] leading-relaxed">
                                    {{ $latestProof->rejection_reason ?: 'The uploaded receipt could not be verified.' }}
                                </p>
                                <p class="text-[10px] text-neutral-400">
                                    Please take a clear photo of the original slip or e-banking advice and upload a replacement below.
                                </p>
                            </div>
                        @endif
                    @endif

                    <!-- Upload Form (Shown if not yet paid) -->
                    @if ($existingInvoice->status !== 'paid')
                        @can('uploadProof', $existingInvoice)
                            <form wire:submit.prevent="submitProof" class="space-y-4 pt-2">
                                <div class="flex items-center justify-between pb-2 border-b border-neutral-800">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-200 font-mono">
                                        {{ $latestProof && $latestProof->isRejected() ? 'Upload Replacement Slip' : 'Upload Payment Slip' }}
                                    </h3>
                                    <span class="text-[10px] text-neutral-400 font-mono">AI Slip Extraction</span>
                                </div>

                                @if ($paymentMethods->count() > 1)
                                    <div>
                                        <label for="payment-method" class="block text-xs font-bold text-neutral-300 mb-1">Which payment method did you use?</label>
                                        <select id="payment-method" wire:model="paymentMethodUuid" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                                            <option value="">Select payment method</option>
                                            @foreach ($paymentMethods as $method)
                                                <option value="{{ $method->uuid }}">{{ $method->label }} ({{ $method->bank_name ?: $method->typeLabel() }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <div>
                                    <label for="proof-file" class="block text-xs font-bold text-neutral-300 mb-1">
                                        Payment Slip / Screenshot / PDF Advice *
                                    </label>
                                    <input 
                                        id="proof-file" 
                                        type="file" 
                                        wire:model="proofFile" 
                                        accept="image/png,image/jpeg,image/jpg,image/webp,application/pdf"
                                        capture="environment"
                                        class="file-input file-input-sm w-full bg-neutral-950 border-neutral-700 text-xs text-white"
                                    >
                                    <p class="text-[10px] text-neutral-500 font-mono mt-1">
                                        PNG, JPG, WEBP, or PDF up to 5 MB. Mobile users can take a photo with their camera.
                                    </p>
                                    @if ($proofFile)
                                        <div class="mt-2 p-2.5 bg-emerald-950/40 border border-emerald-500/30 rounded text-xs text-emerald-300 font-mono flex items-center justify-between">
                                            <span>&#10003; Selected: {{ $proofFile->getClientOriginalName() }} ({{ round($proofFile->getSize() / 1024) }} KB)</span>
                                        </div>
                                    @endif
                                    @error('proofFile') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="depositor" class="block text-xs font-bold text-neutral-300 mb-1">Sender Name (Optional)</label>
                                        <input id="depositor" type="text" wire:model="depositorName" placeholder="Name on bank account / slip" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                                        @error('depositorName') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label for="bank-ref" class="block text-xs font-bold text-neutral-300 mb-1">Bank Reference / STAN (Optional)</label>
                                        <input id="bank-ref" type="text" wire:model="bankReference" placeholder="e.g. 032699400003" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                                        @error('bankReference') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <div class="pt-3 border-t border-neutral-800 flex justify-end">
                                    <button type="submit" wire:loading.attr="disabled" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-60 text-white font-bold rounded-lg shadow-lg shadow-purple-600/30 transition-all text-xs">
                                        <span wire:loading.remove wire:target="submitProof">Submit for AI Verification &rarr;</span>
                                        <span wire:loading wire:target="submitProof">Uploading & Rasterizing...</span>
                                    </button>
                                </div>
                            </form>
                        @endcan
                    @endif
                </div>
            @endif
        </div>

        <!-- Right 5 Cols: Configured Payment Details & Copy Helpers -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Bank Transfer Details Card -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-5 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-800">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-purple-400 font-mono">Bank Transfer Instructions</h3>
                    <span class="text-[10px] text-neutral-500 font-mono">{{ $currency }}</span>
                </div>

                @forelse ($paymentMethods as $method)
                    <div class="space-y-3 font-mono text-xs p-4 bg-neutral-950 rounded-xl border border-neutral-800">
                        <div class="flex items-center justify-between pb-2 border-b border-neutral-800/80">
                            <div class="flex items-center gap-2">
                                @if ($method->logo_path)
                                    <img src="{{ $method->logo_path }}" alt="{{ $method->label }}" class="w-5 h-5 object-contain">
                                @endif
                                <strong class="text-white text-xs">{{ $method->label }}</strong>
                            </div>
                            <span class="text-[10px] uppercase text-purple-400 bg-purple-950/60 px-2 py-0.5 rounded border border-purple-500/20">
                                {{ $method->typeLabel() }}
                            </span>
                        </div>

                        <!-- Dedicated Bank Account Details with Copy Buttons -->
                        @if ($method->bank_name)
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-500 text-[11px]">Bank:</span>
                                <span class="text-neutral-200 font-bold select-all">{{ $method->bank_name }}</span>
                            </div>
                        @endif

                        @if ($method->branch)
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-500 text-[11px]">Branch:</span>
                                <span class="text-neutral-200 select-all">{{ $method->branch }}</span>
                            </div>
                        @endif

                        @if ($method->account_holder)
                            <div class="space-y-1">
                                <span class="text-neutral-500 text-[10px] uppercase block">Account Holder:</span>
                                <div class="flex items-center justify-between bg-neutral-900 p-2 rounded border border-neutral-800">
                                    <span class="text-neutral-200 select-all text-xs font-bold">{{ $method->account_holder }}</span>
                                    <button 
                                        type="button" 
                                        onclick="navigator.clipboard.writeText('{{ $method->account_holder }}'); alert('Account Holder copied to clipboard');" 
                                        class="text-[10px] text-purple-400 hover:text-purple-300 font-sans font-semibold underline"
                                    >
                                        Copy
                                    </button>
                                </div>
                            </div>
                        @endif

                        @if ($method->account_number)
                            <div class="space-y-1">
                                <span class="text-neutral-500 text-[10px] uppercase block">Account Number:</span>
                                <div class="flex items-center justify-between bg-neutral-900 p-2 rounded border border-neutral-800">
                                    <span class="text-emerald-400 select-all text-sm font-bold tracking-wider">{{ $method->account_number }}</span>
                                    <button 
                                        type="button" 
                                        onclick="navigator.clipboard.writeText('{{ $method->account_number }}'); alert('Account Number copied to clipboard');" 
                                        class="px-2.5 py-0.5 bg-emerald-950 text-emerald-300 border border-emerald-500/30 rounded text-[10px] font-sans font-bold hover:bg-emerald-900 transition-all"
                                    >
                                        Copy
                                    </button>
                                </div>
                            </div>
                        @endif

                        <!-- Additional custom rows -->
                        @if (! empty($method->details))
                            <div class="pt-2 border-t border-neutral-800/80 space-y-1.5">
                                @foreach ($method->details as $row)
                                    <div class="flex items-center justify-between text-[11px]">
                                        <span class="text-neutral-500">{{ $row['label'] ?? '' }}:</span>
                                        <span class="text-neutral-300 select-all font-bold">{{ $row['value'] ?? '' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($method->instructions)
                            <div class="pt-2 border-t border-neutral-800/80 text-[11px] text-neutral-400 font-sans leading-relaxed whitespace-pre-line">
                                {{ $method->instructions }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-4 bg-neutral-950 rounded-xl border border-neutral-800 text-xs text-neutral-400">
                        Payment details for {{ $currency }} have not been published yet. Please contact support to receive wire details.
                    </div>
                @endforelse

                <div class="p-3 bg-neutral-950/80 rounded-xl border border-neutral-800/80 text-[11px] text-neutral-400 font-sans space-y-1">
                    <p class="font-bold text-neutral-300">&#128161; Fast Verification Tip:</p>
                    <p>Include your reference code <strong class="text-purple-300 font-mono">{{ $paymentReference }}</strong> in your transaction description or note. Our AI reads this from the slip to auto-verify your payment instantly.</p>
                </div>
            </div>
        </div>
    </div>
</div>
