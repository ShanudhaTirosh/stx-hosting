<div class="max-w-6xl mx-auto p-6 space-y-5">
    <div class="pb-4 border-b border-neutral-800">
        <h1 class="text-2xl font-bold text-white tracking-tight">Audit log</h1>
        <p class="text-xs text-neutral-400 mt-1">Who did what, newest first. Entries cannot be edited or deleted. Changes to apps, servers and databases are in the team audit log under Team.</p>
    </div>

    <div class="flex flex-wrap gap-3 items-end">
        <div>
            <label for="al-area" class="block text-xs font-bold text-neutral-300 mb-1">Area</label>
            <select id="al-area" wire:model.live="area" class="select select-sm bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                <option value="">Everything</option>
                @foreach ($areas as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="al-search" class="block text-xs font-bold text-neutral-300 mb-1">Search (action, name or email)</label>
            <input id="al-search" type="search" wire:model.live.debounce.400ms="search" class="input input-sm bg-neutral-950 border-neutral-700 text-white rounded text-xs">
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <caption class="sr-only">Audit log entries</caption>
            <thead><tr class="text-left text-neutral-500"><th scope="col" class="py-1">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Details</th><th scope="col">From</th></tr></thead>
            <tbody class="font-mono align-top">
                @forelse ($entries as $e)
                    <tr class="border-t border-neutral-800" wire:key="al-{{ $e->id }}">
                        <td class="py-1.5 text-neutral-400 whitespace-nowrap">{{ $e->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="text-neutral-200">{{ $e->user?->email ?? 'system' }}@if ($e->team)<div class="text-neutral-500">{{ $e->team->name }}</div>@endif</td>
                        <td class="text-purple-300">{{ $e->action }}</td>
                        <td class="text-neutral-400 break-all max-w-md">{{ \Illuminate\Support\Str::limit(json_encode($e->payload ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 220) }}</td>
                        <td class="text-neutral-500">{{ $e->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-neutral-500">Nothing logged yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $entries->links() }}
</div>
