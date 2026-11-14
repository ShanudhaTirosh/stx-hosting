<div class="max-w-4xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">AI Slip-Verification Settings</h1>
            <p class="text-xs text-neutral-400">Configure PayVerify API connection, auto-approval thresholds, and fraud tolerances.</p>
        </div>
        <a href="{{ route('admin.billing.methods') }}" class="text-xs text-purple-400 hover:text-purple-300 font-semibold">&larr; Back to Payment Methods</a>
    </div>

    <form wire:submit.prevent="save" class="space-y-6">
        <!-- API Connection Parameters -->
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4 shadow-xl">
            <h2 class="text-xs font-bold uppercase tracking-wider text-purple-400 font-mono">1. PayVerify API Connection</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="pv-url" class="block text-xs font-bold text-neutral-300 mb-1">PayVerify API Base URL *</label>
                    <input id="pv-url" type="text" wire:model="payverifyUrl" placeholder="http://localhost:8000/api/v1/external" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                    @error('payverifyUrl') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="pv-key" class="block text-xs font-bold text-neutral-300 mb-1">API Key (Secure Key) *</label>
                    <input id="pv-key" type="password" wire:model="payverifyApiKey" placeholder="Enter secure external API key" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                    @error('payverifyApiKey') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="pt-2 flex items-center gap-3">
                <button type="button" wire:click="testConnection" wire:loading.attr="disabled" class="px-4 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-semibold rounded-lg border border-neutral-700 transition-all">
                    <span wire:loading.remove wire:target="testConnection">&#9881; Test API Connection</span>
                    <span wire:loading wire:target="testConnection">Pinging PayVerify...</span>
                </button>

                @if ($healthStatus)
                    <span class="text-xs text-emerald-400 font-mono font-bold flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Online &middot; DB Latency: {{ $healthStatus['database']['latency_ms'] ?? '0' }}ms &middot; AI: {{ $healthStatus['ai_vision']['model'] ?? 'Ready' }}
                    </span>
                @elseif ($testError)
                    <span class="text-xs text-rose-400 font-mono">{{ $testError }}</span>
                @endif
            </div>
        </div>

        <!-- Decision Thresholds & Automation -->
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4 shadow-xl">
            <h2 class="text-xs font-bold uppercase tracking-wider text-purple-400 font-mono">2. Confidence Decision Rules & Automations</h2>

            <div class="p-4 bg-neutral-950 rounded-xl border border-neutral-800 space-y-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="autoApproveEnabled" class="checkbox checkbox-sm checkbox-primary mt-0.5">
                    <div>
                        <span class="text-xs font-bold text-white block">Enable High-Confidence Auto-Approval</span>
                        <span class="text-[11px] text-neutral-400 block mt-0.5">
                            When verified or when AI confidence meets or exceeds the threshold with zero discrepancies, automatically mark invoice as paid and deliver hosting services without staff intervention.
                        </span>
                    </div>
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="thresh-auto" class="block text-xs font-bold text-neutral-300 mb-1">
                        Auto-Approve Threshold: <strong class="text-emerald-400 font-mono">{{ round($autoApproveThreshold * 100) }}%</strong>
                    </label>
                    <input id="thresh-auto" type="range" min="0.70" max="0.99" step="0.01" wire:model.live="autoApproveThreshold" class="range range-xs range-primary w-full">
                    <span class="text-[10px] text-neutral-500 font-mono block mt-1">Recommended: 90% (0.90) or higher. Slips with this score or higher auto-approve if no discrepancies.</span>
                </div>

                <div>
                    <label for="thresh-manual" class="block text-xs font-bold text-neutral-300 mb-1">
                        Manual Review Threshold: <strong class="text-amber-400 font-mono">{{ round($manualReviewThreshold * 100) }}%</strong>
                    </label>
                    <input id="thresh-manual" type="range" min="0.30" max="0.80" step="0.01" wire:model.live="manualReviewThreshold" class="range range-xs range-warning w-full">
                    <span class="text-[10px] text-neutral-500 font-mono block mt-1">Scores below this are immediately rejected with advice to upload a clearer photo.</span>
                </div>
            </div>

            <div>
                <label for="skew-days" class="block text-xs font-bold text-neutral-300 mb-1">Maximum Receipt Age Tolerance (Days)</label>
                <input id="skew-days" type="number" min="1" max="30" wire:model="maxDateSkewDays" class="input input-sm w-48 bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                <span class="text-[10px] text-neutral-500 font-mono block mt-1">Slips older than this number of days before order placement are flagged for staff review.</span>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs shadow-lg shadow-purple-600/30 transition-all">
                Save Verification Settings
            </button>
        </div>
    </form>
</div>

