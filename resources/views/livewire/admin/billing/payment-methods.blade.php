<div class="max-w-4xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Payment methods</h1>
            <p class="text-xs text-neutral-400">What clients see on their invoice when they pay. Only the owner can change this.</p>
        </div>
        <button type="button" wire:click="create" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs">Add method</button>
    </div>

    @if ($showForm)
        <form wire:submit.prevent="save" class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-200">{{ $editingId ? 'Edit method' : 'New method' }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label for="pm-label" class="block text-xs font-bold text-neutral-300 mb-1">Name shown to clients</label>
                    <input id="pm-label" type="text" wire:model="label" placeholder="e.g. Commercial Bank transfer" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                    @error('label') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="pm-type" class="block text-xs font-bold text-neutral-300 mb-1">Type</label>
                    <select id="pm-type" wire:model="type" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                        @foreach ($types as $value => $name)
                            <option value="{{ $value }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="pm-currency" class="block text-xs font-bold text-neutral-300 mb-1">Currency</label>
                    <select id="pm-currency" wire:model="currency" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                        <option value="ANY">Any</option>
                        <option value="USD">USD</option>
                        <option value="LKR">LKR</option>
                    </select>
                </div>
                <div>
                    <label for="pm-sort" class="block text-xs font-bold text-neutral-300 mb-1">Order</label>
                    <input id="pm-sort" type="number" min="0" wire:model="sortOrder" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-xs text-neutral-300">
                        <input type="checkbox" wire:model="isActive" class="checkbox checkbox-sm"> Visible to clients
                    </label>
                </div>
            </div>

            <div class="space-y-2">
                <span class="block text-xs font-bold text-neutral-300">Details (account name, number, wallet address...)</span>
                @foreach ($details as $i => $row)
                    <div class="flex gap-2" wire:key="detail-{{ $i }}">
                        <input type="text" wire:model="details.{{ $i }}.label" aria-label="Detail label" placeholder="Label" class="input input-sm w-1/3 bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                        <input type="text" wire:model="details.{{ $i }}.value" aria-label="Detail value" placeholder="Value" class="input input-sm flex-1 bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        <button type="button" wire:click="removeRow({{ $i }})" class="text-xs text-rose-400 hover:text-rose-300 px-2" aria-label="Remove row">Remove</button>
                    </div>
                @endforeach
                <button type="button" wire:click="addRow" class="text-xs text-purple-400 hover:text-purple-300 font-semibold">Add row</button>
            </div>

            <div>
                <label for="pm-instr" class="block text-xs font-bold text-neutral-300 mb-1">Instructions</label>
                <textarea id="pm-instr" wire:model="instructions" rows="3" placeholder="Use the invoice number as the payment reference." class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs"></textarea>
                @error('instructions') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-neutral-800">
                <button type="button" wire:click="cancel" class="px-4 py-2 text-xs text-neutral-300 hover:text-white">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs">Save</button>
            </div>
        </form>
    @endif

    <div class="space-y-3">
        @forelse ($methods as $method)
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 flex flex-wrap items-center justify-between gap-3" wire:key="method-{{ $method->id }}">
                <div>
                    <div class="text-sm font-bold text-white">{{ $method->label }}</div>
                    <div class="text-[11px] text-neutral-400 font-mono">{{ $method->typeLabel() }} &middot; {{ $method->currency }} &middot; {{ count($method->details ?? []) }} detail rows</div>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <span class="px-2 py-0.5 rounded font-mono {{ $method->is_active ? 'bg-emerald-950/60 text-emerald-300' : 'bg-neutral-800 text-neutral-400' }}">{{ $method->is_active ? 'Visible' : 'Hidden' }}</span>
                    <button type="button" wire:click="toggle({{ $method->id }})" class="text-neutral-300 hover:text-white">{{ $method->is_active ? 'Hide' : 'Show' }}</button>
                    <button type="button" wire:click="edit({{ $method->id }})" class="text-purple-400 hover:text-purple-300">Edit</button>
                    <button type="button" wire:click="delete({{ $method->id }})" wire:confirm="Delete this payment method?" class="text-rose-400 hover:text-rose-300">Delete</button>
                </div>
            </div>
        @empty
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 text-xs text-neutral-300">
                No payment methods yet. Clients will see a "contact support" message at checkout until you add one.
            </div>
        @endforelse
    </div>
</div>
