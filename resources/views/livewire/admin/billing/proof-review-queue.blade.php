<div class="p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-700">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Manual Payment Verification Queue</h1>
            <p class="text-sm text-neutral-400">Review submitted bank transfer slips, crypto hashes, and proof files before activating services.</p>
        </div>

        <div class="flex items-center gap-3">
            <label class="text-xs font-semibold text-neutral-400 uppercase">Status:</label>
            <select wire:model.live="filterStatus" class="select select-sm bg-neutral-800 border-neutral-700 text-white rounded-md text-xs">
                <option value="pending_review">Pending Review</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="all">All Submissions</option>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Submissions List (Left Col) -->
        <div class="{{ $selectedProof ? 'lg:col-span-5' : 'lg:col-span-12' }} space-y-3">
            @forelse ($proofs as $proof)
                <div 
                    wire:click="selectProof({{ $proof->id }})"
                    class="p-4 rounded-lg border cursor-pointer transition-all {{ $selectedProofId === $proof->id ? 'bg-neutral-800 border-purple-500 shadow-md' : 'bg-neutral-900 border-neutral-800 hover:border-neutral-700' }}"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs font-semibold text-neutral-300">
                            {{ $proof->invoice->invoice_number ?? 'INV-UNKNOWN' }}
                        </span>
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-full {{ 
                            $proof->status === 'approved' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 
                            ($proof->status === 'rejected' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20') 
                        }}">
                            {{ str_replace('_', ' ', $proof->status) }}
                        </span>
                    </div>

                    <div class="mt-2 text-sm text-white font-medium">
                        {{ $proof->invoice->user->email ?? 'Guest Customer' }}
                    </div>

                    <div class="mt-1 flex items-center justify-between text-xs text-neutral-400 font-mono">
                        <span>Method: {{ ucfirst(str_replace('_', ' ', $proof->payment_method)) }}</span>
                        <span class="text-white font-bold">
                            {{ number_format(($proof->invoice->total_cents ?? 0) / 100, 2) }} {{ $proof->invoice->currency ?? 'USD' }}
                        </span>
                    </div>

                    <div class="mt-2 text-[11px] text-neutral-500">
                        Uploaded {{ $proof->created_at->diffForHumans() }}
                    </div>
                </div>
            @empty
                <div class="p-8 text-center bg-neutral-900 border border-neutral-800 rounded-lg text-neutral-400">
                    No payment proofs found for this filter.
                </div>
            @endforelse

            <div class="pt-2">
                {{ $proofs->links() }}
            </div>
        </div>

        <!-- Detail & Side-by-Side Review Pane (Right Col) -->
        @if ($selectedProof)
            <div class="lg:col-span-7 bg-neutral-900 border border-neutral-800 rounded-lg p-6 space-y-6">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-4">
                    <div>
                        <h2 class="text-lg font-bold text-white">Review Submission: {{ $selectedProof->invoice->invoice_number }}</h2>
                        <p class="text-xs text-neutral-400">Customer: {{ $selectedProof->invoice->user->email ?? 'N/A' }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($selectedProof->status === 'pending_review')
                            <button 
                                wire:click="approve({{ $selectedProof->id }})"
                                class="px-4 py-1.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded shadow transition-all"
                            >
                                Approve Payment
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Invoice Line Items Summary -->
                <div class="bg-neutral-950 p-4 rounded border border-neutral-800 space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-400">Invoice Items</h3>
                    <div class="divide-y divide-neutral-800 text-xs">
                        @foreach ($selectedProof->invoice->items as $item)
                            <div class="py-2 flex items-center justify-between">
                                <span class="text-neutral-200">{{ $item->description }} (x{{ $item->quantity }})</span>
                                <span class="font-mono text-white font-semibold">
                                    {{ number_format($item->total_price_cents / 100, 2) }} {{ $selectedProof->invoice->currency }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <div class="pt-2 border-t border-neutral-800 flex justify-between font-bold text-sm text-white">
                        <span>Total Due:</span>
                        <span class="text-emerald-400 font-mono">
                            {{ number_format($selectedProof->invoice->total_cents / 100, 2) }} {{ $selectedProof->invoice->currency }}
                        </span>
                    </div>
                </div>

                <!-- Transaction Reference & File Metadata -->
                <div class="grid grid-cols-2 gap-4 text-xs font-mono bg-neutral-950 p-4 rounded border border-neutral-800">
                    <div>
                        <span class="text-neutral-500 block">Method:</span>
                        <span class="text-white">{{ strtoupper(str_replace('_', ' ', $selectedProof->payment_method)) }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-500 block">Transaction Reference:</span>
                        <span class="text-white">{{ $selectedProof->transaction_reference ?: 'None provided' }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-500 block">SHA-256 Checksum:</span>
                        <span class="text-neutral-400 text-[10px] truncate block" title="{{ $selectedProof->sha256_hash }}">
                            {{ $selectedProof->sha256_hash }}
                        </span>
                    </div>
                    <div>
                        <span class="text-neutral-500 block">MIME Type:</span>
                        <span class="text-white">{{ $selectedProof->file_mime }}</span>
                    </div>
                </div>

                <!-- Proof Viewer (Image or PDF) via signed URL -->
                <div class="border border-neutral-800 rounded bg-neutral-950 p-2 text-center min-h-[360px] flex items-center justify-center overflow-hidden">
                    @if ($signedViewUrl)
                        @if (str_starts_with($selectedProof->file_mime, 'image/'))
                            <img src="{{ $signedViewUrl }}" alt="Payment Receipt" class="max-h-[500px] w-auto mx-auto rounded shadow-lg object-contain" />
                        @elseif ($selectedProof->file_mime === 'application/pdf')
                            <iframe src="{{ $signedViewUrl }}" class="w-full h-[500px] rounded border border-neutral-800"></iframe>
                        @else
                            <a href="{{ $signedViewUrl }}" target="_blank" class="text-blue-400 underline text-xs">
                                Download Receipt File ({{ $selectedProof->file_mime }})
                            </a>
                        @endif
                    @else
                        <span class="text-xs text-neutral-500">Receipt preview unavailable.</span>
                    @endif
                </div>

                <!-- Rejection Form (if pending) -->
                @if ($selectedProof->status === 'pending_review')
                    <div class="border-t border-neutral-800 pt-4 space-y-3">
                        <label class="block text-xs font-bold uppercase text-neutral-400">Rejection Reason (if invalid):</label>
                        <div class="flex gap-2">
                            <input 
                                type="text" 
                                wire:model="rejectionReason" 
                                placeholder="e.g. Incomplete slip, blurry reference, incorrect amount..." 
                                class="input input-sm flex-1 bg-neutral-950 border-neutral-700 text-white text-xs rounded"
                            />
                            <button 
                                wire:click="reject({{ $selectedProof->id }})" 
                                class="px-4 py-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-500 rounded shadow transition-all"
                            >
                                Reject Slip
                            </button>
                        </div>
                        @error('rejectionReason')
                            <span class="text-xs text-rose-400">{{ $message }}</span>
                        @enderror
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
