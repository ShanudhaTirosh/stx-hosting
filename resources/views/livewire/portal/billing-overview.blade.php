<div class="max-w-6xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Billing & Invoices</h1>
            <p class="text-sm text-neutral-400">View payment history, download invoice records, and check verification status.</p>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden shadow-lg">
        <table class="w-full text-left text-xs text-neutral-300">
            <thead class="bg-neutral-950 text-neutral-400 uppercase text-[10px] tracking-wider border-b border-neutral-800 font-mono">
                <tr>
                    <th class="p-4">Invoice #</th>
                    <th class="p-4">Created Date</th>
                    <th class="p-4">Due Date</th>
                    <th class="p-4">Amount</th>
                    <th class="p-4">Payment Status</th>
                    <th class="p-4 text-right">Receipt Proof</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-800">
                @forelse ($invoices as $invoice)
                    <tr class="hover:bg-neutral-800/50 transition-all font-mono">
                        <td class="p-4 font-bold text-white">
                            <a href="{{ route('portal.invoices.show', $invoice->uuid) }}" target="_blank" class="text-purple-400 hover:text-purple-300 hover:underline flex items-center gap-1.5">
                                {{ $invoice->invoice_number }}
                                <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        </td>
                        <td class="p-4 text-neutral-400">
                            {{ $invoice->created_at->format('M d, Y') }}
                        </td>
                        <td class="p-4 text-neutral-400">
                            {{ $invoice->due_at ? $invoice->due_at->format('M d, Y') : 'N/A' }}
                        </td>
                        <td class="p-4 font-bold text-white">
                            {{ number_format($invoice->total_cents / 100, 2) }} {{ $invoice->currency }}
                        </td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-full {{ 
                                $invoice->status === 'paid' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 
                                ($invoice->status === 'payment_pending' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20') 
                            }}">
                                {{ str_replace('_', ' ', $invoice->status) }}
                            </span>
                        </td>
                        <td class="p-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('portal.invoices.show', $invoice->uuid) }}" target="_blank" class="px-2.5 py-1 bg-neutral-800 hover:bg-neutral-700 text-white rounded text-[10px] font-sans font-bold flex items-center gap-1 transition-all">
                                    <svg class="w-3 h-3 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    View / Print
                                </a>
                                @if ($invoice->proofs->count() > 0)
                                    <span class="text-neutral-400 text-[11px] font-mono">Submitted ({{ $invoice->proofs->count() }})</span>
                                @elseif (in_array($invoice->status, ['unpaid', 'payment_pending']))
                                    <a href="{{ route('portal.checkout') }}?invoice={{ $invoice->uuid }}" class="px-2.5 py-1 bg-purple-600 hover:bg-purple-500 text-white rounded text-[10px] font-sans font-bold">
                                        Upload Slip
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-neutral-500">
                            No invoices generated for your account yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

