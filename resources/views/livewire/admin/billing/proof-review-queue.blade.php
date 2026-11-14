<div class="p-6 space-y-6 max-w-7xl mx-auto">
    <!-- Header & Metrics -->
    <div class="space-y-4 pb-4 border-b border-neutral-800">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Payment Verification Workbench</h1>
                <p class="text-xs text-neutral-400">AI-powered slip extraction, perceptual forensics, and manual review queue.</p>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-neutral-400 uppercase font-mono">Filter:</span>
                <select wire:model.live="filterStatus" class="select select-sm bg-neutral-900 border-neutral-700 text-white rounded text-xs">
                    <option value="pending_review">Pending Review ({{ $pendingCount }})</option>
                    <option value="verifying">AI Verifying ({{ $verifyingCount }})</option>
                    <option value="auto_verified">Auto-Verified ({{ $autoVerifiedCount }})</option>
                    <option value="approved">Staff Approved</option>
                    <option value="rejected">Rejected ({{ $rejectedCount }})</option>
                    <option value="all">All Submissions</option>
                </select>
            </div>
        </div>

        <!-- Telemetry Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs font-mono">
            <div class="bg-neutral-900 p-3 rounded-xl border border-neutral-800">
                <span class="text-[10px] text-amber-400 block uppercase">Needs Review</span>
                <span class="text-xl font-bold text-white">{{ $pendingCount }}</span>
            </div>
            <div class="bg-neutral-900 p-3 rounded-xl border border-neutral-800">
                <span class="text-[10px] text-purple-400 block uppercase">AI In-Flight</span>
                <span class="text-xl font-bold text-white">{{ $verifyingCount }}</span>
            </div>
            <div class="bg-neutral-900 p-3 rounded-xl border border-neutral-800">
                <span class="text-[10px] text-emerald-400 block uppercase">Auto-Verified</span>
                <span class="text-xl font-bold text-emerald-400">{{ $autoVerifiedCount }}</span>
            </div>
            <div class="bg-neutral-900 p-3 rounded-xl border border-neutral-800">
                <span class="text-[10px] text-rose-400 block uppercase">Rejected / Flagged</span>
                <span class="text-xl font-bold text-rose-400">{{ $rejectedCount }}</span>
            </div>
        </div>
    </div>

    <!-- Main Workbench Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Submissions List (Left Col) -->
        <div class="{{ $selectedProof ? 'lg:col-span-4' : 'lg:col-span-12' }} space-y-3">
            @forelse ($proofs as $proof)
                <div 
                    wire:click="selectProof({{ $proof->id }})"
                    class="p-4 rounded-xl border cursor-pointer transition-all space-y-2 {{ $selectedProofId === $proof->id ? 'bg-neutral-800 border-purple-500 shadow-lg' : 'bg-neutral-900 border-neutral-800 hover:border-neutral-700' }}"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs font-bold text-white">
                            {{ $proof->invoice->invoice_number ?? 'INV-UNKNOWN' }}
                        </span>
                        
                        <!-- AI Confidence Badge -->
                        @if ($proof->confidence_score !== null)
                            <span class="px-2 py-0.5 text-[10px] font-mono font-bold rounded-full {{ 
                                $proof->confidenceLevel() === 'high' ? 'bg-emerald-950/60 text-emerald-300 border border-emerald-500/30' :
                                ($proof->confidenceLevel() === 'medium' ? 'bg-amber-950/60 text-amber-300 border border-amber-500/30' : 'bg-rose-950/60 text-rose-300 border border-rose-500/30')
                            }}">
                                AI: {{ $proof->confidencePercent() }}%
                            </span>
                        @else
                            <span class="px-2 py-0.5 text-[10px] font-mono uppercase rounded-full bg-neutral-800 text-neutral-400 border border-neutral-700">
                                {{ str_replace('_', ' ', $proof->status) }}
                            </span>
                        @endif
                    </div>

                    <div class="text-xs text-neutral-300 truncate font-sans">
                        {{ $proof->invoice->user->email ?? 'Guest Customer' }}
                    </div>

                    <div class="flex items-center justify-between text-xs font-mono pt-1 border-t border-neutral-800/80">
                        <span class="text-neutral-400">{{ ucfirst(str_replace('_', ' ', $proof->payment_method)) }}</span>
                        <span class="text-emerald-400 font-bold">
                            {{ number_format(($proof->invoice->total_cents ?? 0) / 100, 2) }} {{ $proof->invoice->currency ?? 'USD' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-[10px] text-neutral-500 font-mono">
                        <span>Attempt #{{ $proof->attempt_no }}</span>
                        <span>{{ $proof->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center bg-neutral-900 border border-neutral-800 rounded-xl text-neutral-400 text-xs">
                    No payment proofs matching the selected filter.
                </div>
            @endforelse

            <div class="pt-2">
                {{ $proofs->links() }}
            </div>
        </div>

        <!-- Detail & Side-by-Side Review Pane (Right Col) -->
        @if ($selectedProof)
            <div class="lg:col-span-8 bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-6 shadow-xl">
                <!-- Submission Header -->
                <div class="flex flex-wrap items-center justify-between border-b border-neutral-800 pb-4 gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold text-white">{{ $selectedProof->invoice->invoice_number }}</h2>
                            <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase rounded-full {{ 
                                $selectedProof->isApproved() ? 'bg-emerald-950/60 text-emerald-300 border border-emerald-500/30' :
                                ($selectedProof->isRejected() ? 'bg-rose-950/60 text-rose-300 border border-rose-500/30' : 'bg-amber-950/60 text-amber-300 border border-amber-500/30')
                            }}">
                                {{ str_replace('_', ' ', $selectedProof->status) }}
                            </span>
                        </div>
                        <p class="text-xs text-neutral-400 font-mono mt-0.5">
                            Customer: {{ $selectedProof->invoice->user->email ?? 'N/A' }} &middot; Ref: {{ $selectedProof->transaction_reference ?: ($selectedProof->invoice->payment_reference ?: 'None') }}
                        </p>
                    </div>

                    <!-- Quick Action Buttons -->
                    @if ($selectedProof->status === 'pending_review' || $selectedProof->status === 'verifying')
                        <div class="flex items-center gap-2">
                            <button 
                                wire:click="approve({{ $selectedProof->id }})"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 rounded-lg shadow-lg shadow-emerald-600/20 transition-all"
                            >
                                <span wire:loading.remove wire:target="approve">&#10003; Approve Payment</span>
                                <span wire:loading wire:target="approve">Approving...</span>
                            </button>
                        </div>
                    @endif
                </div>

                <!-- AI Extraction & Verification Insights Card -->
                <div class="bg-neutral-950 p-4 rounded-xl border border-neutral-800 space-y-3">
                    <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-purple-400 font-mono">PayVerify AI Analysis</span>
                            @if ($selectedProof->confidence_score !== null)
                                <span class="text-[10px] font-mono px-2 py-0.5 rounded font-bold {{ 
                                    $selectedProof->confidenceLevel() === 'high' ? 'bg-emerald-950 text-emerald-300 border border-emerald-500/30' :
                                    ($selectedProof->confidenceLevel() === 'medium' ? 'bg-amber-950 text-amber-300 border border-amber-500/30' : 'bg-rose-950 text-rose-300 border border-rose-500/30')
                                }}">
                                    Confidence: {{ $selectedProof->confidencePercent() }}%
                                </span>
                            @endif
                        </div>
                        @if ($selectedProof->verification_basis)
                            <span class="text-[10px] text-neutral-400 font-mono">Basis: {{ $selectedProof->verification_basis }}</span>
                        @endif
                    </div>

                    <!-- Side-by-Side Comparison Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs font-mono">
                        <div class="bg-neutral-900/80 p-3 rounded-lg border border-neutral-800">
                            <span class="text-[10px] text-neutral-500 block uppercase">Expected Total Due</span>
                            <span class="text-sm font-bold text-white">
                                {{ number_format($selectedProof->invoice->total_cents / 100, 2) }} {{ $selectedProof->invoice->currency }}
                            </span>
                        </div>
                        <div class="bg-neutral-900/80 p-3 rounded-lg border border-neutral-800">
                            <span class="text-[10px] text-neutral-500 block uppercase">AI-Extracted Amount</span>
                            @if ($selectedProof->extracted_amount_cents)
                                <span class="text-sm font-bold {{ $selectedProof->extracted_amount_cents === $selectedProof->invoice->total_cents ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ number_format($selectedProof->extracted_amount_cents / 100, 2) }} {{ $selectedProof->invoice->currency }}
                                    @if ($selectedProof->extracted_amount_cents === $selectedProof->invoice->total_cents)
                                        <span class="text-[10px] text-emerald-400 font-normal">(&#10003; Exact Match)</span>
                                    @else
                                        <span class="text-[10px] text-rose-400 font-normal">(&#9888; Mismatch)</span>
                                    @endif
                                </span>
                            @else
                                <span class="text-neutral-500 italic">Could not extract reliably</span>
                            @endif
                        </div>

                        <div class="bg-neutral-900/80 p-3 rounded-lg border border-neutral-800">
                            <span class="text-[10px] text-neutral-500 block uppercase">Expected Payment Reference</span>
                            <span class="text-neutral-200 font-bold select-all">
                                {{ $selectedProof->invoice->payment_reference ?: 'None configured' }}
                            </span>
                        </div>
                        <div class="bg-neutral-900/80 p-3 rounded-lg border border-neutral-800">
                            <span class="text-[10px] text-neutral-500 block uppercase">AI-Extracted Reference / STAN</span>
                            <span class="text-neutral-200 select-all font-bold">
                                {{ $selectedProof->extracted_reference ?: 'None detected' }}
                            </span>
                        </div>
                    </div>

                    <!-- Discrepancies Alerts -->
                    @if ($selectedProof->hasDiscrepancies())
                        <div class="p-3 bg-rose-500/10 border border-rose-500/20 rounded-lg text-xs space-y-1">
                            <span class="font-bold text-rose-400 uppercase tracking-wider block font-mono text-[10px]">&#9888; Discrepancies Detected</span>
                            <ul class="list-disc list-inside text-rose-300 text-xs font-mono">
                                @foreach ($selectedProof->discrepancies as $disc)
                                    <li>{{ str_replace('_', ' ', (string) $disc) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <!-- Proof File Viewer with Signed URL -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs text-neutral-400 font-mono">
                        <span>Submitted Slip File (Private Signed Access)</span>
                        @if ($signedViewUrl)
                            <a href="{{ $signedViewUrl }}" target="_blank" class="text-purple-400 hover:text-purple-300 underline font-sans font-semibold">
                                Open in New Tab &rarr;
                            </a>
                        @endif
                    </div>

                    <div class="border border-neutral-800 rounded-xl bg-neutral-950 p-2 text-center min-h-[380px] flex items-center justify-center overflow-hidden">
                        @if ($signedViewUrl)
                            @if (str_starts_with($selectedProof->file_mime, 'image/'))
                                <img src="{{ $signedViewUrl }}" alt="Payment Receipt Slip" class="max-h-[500px] w-auto mx-auto rounded shadow-lg object-contain transition-transform hover:scale-105" />
                            @elseif ($selectedProof->file_mime === 'application/pdf')
                                <iframe src="{{ $signedViewUrl }}" class="w-full h-[500px] rounded border border-neutral-800"></iframe>
                            @else
                                <a href="{{ $signedViewUrl }}" target="_blank" class="text-purple-400 underline text-xs">
                                    Download Slip File ({{ $selectedProof->file_mime }})
                                </a>
                            @endif
                        @else
                            <span class="text-xs text-neutral-500">Slip preview unavailable.</span>
                        @endif
                    </div>
                </div>

                <!-- Staff Action Forms (Reject & Request New Slip) -->
                @if ($selectedProof->status === 'pending_review' || $selectedProof->status === 'verifying')
                    <div class="border-t border-neutral-800 pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Reject Box -->
                        <div class="bg-neutral-950 p-4 rounded-xl border border-neutral-800 space-y-2">
                            <label class="block text-xs font-bold uppercase text-rose-400 font-mono">Reject Slip Permanently</label>
                            <input 
                                type="text" 
                                wire:model="rejectionReason" 
                                placeholder="e.g. Unrecognized deposit, invalid receipt..." 
                                class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white text-xs rounded"
                            />
                            @error('rejectionReason') <span class="text-xs text-rose-400 block">{{ $message }}</span> @enderror
                            <button 
                                wire:click="reject({{ $selectedProof->id }})" 
                                wire:loading.attr="disabled"
                                class="w-full py-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-500 disabled:opacity-50 rounded-lg shadow transition-all"
                            >
                                Reject Payment
                            </button>
                        </div>

                        <!-- Request Resubmission Box -->
                        <div class="bg-neutral-950 p-4 rounded-xl border border-neutral-800 space-y-2">
                            <label class="block text-xs font-bold uppercase text-amber-400 font-mono">Request Better Slip (Keep Order Open)</label>
                            <input 
                                type="text" 
                                wire:model="resubmitReason" 
                                placeholder="e.g. Slip is blurry; please upload clear PDF advice..." 
                                class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white text-xs rounded"
                            />
                            @error('resubmitReason') <span class="text-xs text-rose-400 block">{{ $message }}</span> @enderror
                            <button 
                                wire:click="requestResubmission({{ $selectedProof->id }})" 
                                wire:loading.attr="disabled"
                                class="w-full py-1.5 text-xs font-bold text-neutral-950 bg-amber-400 hover:bg-amber-300 disabled:opacity-50 rounded-lg shadow transition-all"
                            >
                                Request New Slip
                            </button>
                        </div>
                    </div>
                @endif

                <!-- Administrative Refund Section -->
                @if ($selectedProof->isApproved() && in_array($selectedProof->invoice->status, ['paid', 'partially_refunded']))
                    <div class="border-t border-neutral-800 pt-4 space-y-3 bg-neutral-950 p-4 rounded-xl border border-neutral-800">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold uppercase tracking-wider text-amber-400 font-mono">Process Refund / Adjustment</label>
                            <span class="text-xs text-neutral-400 font-mono">Invoice Status: <strong class="text-emerald-400">{{ ucfirst($selectedProof->invoice->status) }}</strong></span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11px] text-neutral-400 block mb-1">Reason for Refund:</label>
                                <input 
                                    type="text" 
                                    wire:model="refundReason" 
                                    placeholder="e.g. Mutual cancellation or duplicate charge" 
                                    class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white text-xs rounded"
                                />
                                @error('refundReason') <span class="text-xs text-rose-400 block mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="text-[11px] text-neutral-400 block mb-1">Amount (blank for full {{ number_format($selectedProof->invoice->total_cents / 100, 2) }}):</label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    wire:model="refundAmount" 
                                    placeholder="{{ number_format($selectedProof->invoice->total_cents / 100, 2) }}" 
                                    class="input input-sm w-full bg-neutral-900 border-neutral-700 text-white text-xs rounded font-mono"
                                />
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-2">
                            <label class="flex items-center gap-2 text-xs text-neutral-300 cursor-pointer">
                                <input type="checkbox" wire:model="refundRevokeEntitlements" class="checkbox checkbox-xs checkbox-primary"> Revoke active entitlements
                            </label>
                            <button 
                                wire:click="refundInvoice({{ $selectedProof->invoice->id }})" 
                                wire:confirm="Are you sure you want to process this refund?"
                                class="px-4 py-1.5 bg-amber-500 hover:bg-amber-400 text-neutral-950 font-bold rounded-lg text-xs"
                            >
                                Process Refund
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
