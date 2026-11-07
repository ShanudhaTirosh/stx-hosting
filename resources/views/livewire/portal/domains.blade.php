<div class="max-w-4xl mx-auto p-6 space-y-6">
    <div class="pb-4 border-b border-neutral-800">
        <h1 class="text-2xl font-bold text-white tracking-tight">Domains</h1>
        <p class="text-xs text-neutral-400 mt-1">
            Every app already gets a free address@if ($appsDomain) like <span class="font-mono text-neutral-200">your-app.{{ $appsDomain }}</span>@endif.
            To use your own domain, add it here and prove you own it.
        </p>
    </div>

    <form wire:submit.prevent="add" class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[16rem]">
            <label for="new-domain" class="block text-xs font-bold text-neutral-300 mb-1">Your domain</label>
            <input id="new-domain" type="text" wire:model="newDomain" placeholder="example.com" autocomplete="off" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
            @error('newDomain') <span class="text-rose-400 text-xs block mt-1" role="alert">{{ $message }}</span> @enderror
        </div>
        <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs">Add domain</button>
    </form>

    <div class="space-y-4">
        @forelse ($domains as $domain)
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-3" wire:key="domain-{{ $domain->id }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="font-mono text-sm text-white font-bold">{{ $domain->domain }}</div>
                    <div class="flex items-center gap-3 text-xs">
                        @if ($domain->isVerified())
                            <span class="px-2 py-0.5 rounded bg-emerald-950/60 text-emerald-300 font-mono">Verified</span>
                        @else
                            <span class="px-2 py-0.5 rounded bg-amber-950/60 text-amber-300 font-mono">Waiting for DNS</span>
                            <button type="button" wire:click="verify('{{ $domain->uuid }}')" class="text-purple-400 hover:text-purple-300 font-semibold">Check now</button>
                        @endif
                        <button type="button" wire:click="remove('{{ $domain->uuid }}')" wire:confirm="Remove this domain?" class="text-rose-400 hover:text-rose-300">Remove</button>
                    </div>
                </div>

                @unless ($domain->isVerified())
                    <div class="bg-neutral-950 rounded p-3 text-xs space-y-1 font-mono">
                        <div class="text-neutral-400 font-sans">1. Prove you own it. Add this TXT record at your DNS provider:</div>
                        <div><span class="text-neutral-500">Name</span> <span class="text-emerald-400 select-all">{{ $domain->txtHost() }}</span></div>
                        <div><span class="text-neutral-500">Value</span> <span class="text-emerald-400 select-all break-all">{{ $domain->txtValue() }}</span></div>
                    </div>
                @else
                    <div class="bg-neutral-950 rounded p-3 text-xs space-y-1 font-mono">
                        <div class="text-neutral-400 font-sans">
                            2. Point the domain at us so HTTPS works. Add an A record to
                            @if ($ingressIp)<span class="text-emerald-400 select-all">{{ $ingressIp }}</span>@else the address support gives you@endif.
                        </div>
                        @if (($routing[$domain->id] ?? null) === true)
                            <div class="text-emerald-300 font-sans">This domain already points at us.</div>
                        @elseif (($routing[$domain->id] ?? null) === false)
                            <div class="text-amber-300 font-sans">It does not point at us yet. Until it does, your app will not load on it.</div>
                        @endif
                        <div class="text-neutral-400 font-sans">Then set it as the domain of your app. We issue the certificate automatically.</div>
                    </div>
                @endunless
            </div>
        @empty
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 text-xs text-neutral-300">No custom domains yet.</div>
        @endforelse
    </div>
</div>
