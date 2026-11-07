<div class="max-w-4xl mx-auto p-6 space-y-6">
    <div class="pb-4 border-b border-neutral-800">
        <h1 class="text-2xl font-bold text-white tracking-tight">Staff alerts</h1>
        <p class="text-xs text-neutral-400 mt-1">Choose which Discord channel hears about what. Create a webhook in the channel's settings (Integrations, Webhooks) and paste its URL.</p>
    </div>

    <form wire:submit.prevent="add" class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
        <div class="sm:col-span-2">
            <label for="dr-event" class="block text-xs font-bold text-neutral-300 mb-1">Event</label>
            <select id="dr-event" wire:model="event" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                @foreach ($events as $key => $name)
                    <option value="{{ $key }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label for="dr-label" class="block text-xs font-bold text-neutral-300 mb-1">Name (optional)</label>
            <input id="dr-label" type="text" wire:model="label" placeholder="#orders" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
        </div>
        <div class="sm:col-span-6">
            <label for="dr-url" class="block text-xs font-bold text-neutral-300 mb-1">Webhook URL</label>
            <input id="dr-url" type="url" wire:model="webhookUrl" autocomplete="off" placeholder="https://discord.com/api/webhooks/..." class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
            @error('webhookUrl') <span class="text-rose-400 text-xs block mt-1" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="sm:col-span-6 flex justify-end">
            <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs">Add channel</button>
        </div>
    </form>

    <div class="space-y-4">
        @foreach ($events as $key => $name)
            <section class="bg-neutral-900 border border-neutral-800 rounded-xl p-4" aria-labelledby="ev-{{ $loop->index }}">
                <h2 id="ev-{{ $loop->index }}" class="text-sm font-bold text-white">{{ $name }}</h2>
                @forelse ($routes->get($key, collect()) as $route)
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs" wire:key="route-{{ $route->id }}">
                        <div>
                            <span class="text-neutral-200">{{ $route->label ?: 'Discord channel' }}</span>
                            <span class="font-mono text-neutral-500 ml-2">{{ $route->maskedUrl() }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="px-2 py-0.5 rounded font-mono {{ $route->is_enabled ? 'bg-emerald-950/60 text-emerald-300' : 'bg-neutral-800 text-neutral-400' }}">{{ $route->is_enabled ? 'On' : 'Off' }}</span>
                            <button type="button" wire:click="test({{ $route->id }})" class="text-purple-400 hover:text-purple-300">Test</button>
                            <button type="button" wire:click="toggle({{ $route->id }})" class="text-neutral-300 hover:text-white">{{ $route->is_enabled ? 'Turn off' : 'Turn on' }}</button>
                            <button type="button" wire:click="delete({{ $route->id }})" wire:confirm="Remove this channel?" class="text-rose-400 hover:text-rose-300">Remove</button>
                        </div>
                    </div>
                @empty
                    <p class="mt-1 text-xs text-neutral-500">No channel yet. Nobody is alerted about this.</p>
                @endforelse
            </section>
        @endforeach
    </div>
</div>
