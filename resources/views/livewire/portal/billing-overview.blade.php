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
                            {{ $invoice->invoice_number }}
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
                            @if ($invoice->proofs->count() > 0)
                                <span class="text-neutral-400 text-[11px]">Submitted ({{ $invoice->proofs->count() }})</span>
                            @elseif ($invoice->status === 'unpaid')
                                <a href="http://localhost:3000/pricing" target="_blank" class="px-2 py-1 bg-purple-600 hover:bg-purple-500 text-white rounded text-[10px] font-sans font-bold">
                                    Upload Receipt
                                </a>
                            @else
                                <span class="text-neutral-500 text-[11px]">-</span>
                            @endif
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
