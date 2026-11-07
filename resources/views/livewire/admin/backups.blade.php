<div class="max-w-6xl mx-auto p-6 space-y-6">
    <div class="pb-4 border-b border-neutral-800">
        <h1 class="text-2xl font-bold text-white tracking-tight">Backups</h1>
        <p class="text-xs text-neutral-400 mt-1">All customer database backups live in one platform storage (Cloudflare R2). Each team's files sit under their own folder.</p>
    </div>

    @if ($error)<div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-300 rounded-xl text-xs font-semibold" role="alert">{{ $error }}</div>@endif

    <section class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-3" aria-labelledby="st-h">
        <h2 id="st-h" class="text-sm font-bold uppercase tracking-wider text-neutral-200">Storage</h2>
        @if ($current)
            <p class="text-xs text-emerald-300">In use: <span class="font-mono">{{ $current->name }}</span> (bucket {{ $current->bucket }})</p>
        @else
            <p class="text-xs text-amber-300">No storage chosen. Customers cannot turn backups on until you pick one.</p>
        @endif
        @if ($isOwner)
            <form wire:submit.prevent="chooseStorage" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="bk-storage" class="block text-xs font-bold text-neutral-300 mb-1">Platform storage</label>
                    <select id="bk-storage" wire:model="storageUuid" class="select select-sm bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                        <option value="">Choose...</option>
                        @foreach ($candidates as $c)<option value="{{ $c->uuid }}">{{ $c->name }} ({{ $c->bucket }})</option>@endforeach
                    </select>
                </div>
                <button type="submit" class="px-3 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs">Use this storage</button>
                @if ($current)<button type="button" wire:click="testStorage" class="px-3 py-2 border border-neutral-600 text-neutral-200 hover:bg-neutral-800 rounded-lg text-xs">Test connection</button>@endif
            </form>
            <p class="text-[11px] text-neutral-500">Add your R2 bucket under Storages first (endpoint https://&lt;account&gt;.r2.cloudflarestorage.com, region auto) in the platform team. Clients never see its keys.</p>
        @endif
    </section>

    <section class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-2" aria-labelledby="us-h">
        <h2 id="us-h" class="text-sm font-bold uppercase tracking-wider text-neutral-200">Space used per team</h2>
        <table class="w-full text-xs"><caption class="sr-only">Backup space per team</caption>
            <thead><tr class="text-left text-neutral-500"><th scope="col" class="py-1">Team</th><th scope="col">Used</th><th scope="col">Limit (GB)</th>@if ($isOwner)<th scope="col">Change limit</th>@endif</tr></thead>
            <tbody class="font-mono">
                @forelse ($teams as $row)
                    <tr class="border-t border-neutral-800" wire:key="t-{{ $row['team']->id }}">
                        <td class="py-1.5 text-neutral-200">{{ $row['team']->name }}</td>
                        <td class="{{ $row['limit'] > 0 && $row['used'] >= $row['limit'] ? 'text-rose-400' : 'text-neutral-300' }}">{{ number_format($row['used'], 2) }} GB</td>
                        <td class="text-neutral-400">{{ rtrim(rtrim(number_format($row['limit'], 2), '0'), '.') }}</td>
                        @if ($isOwner)
                            <td>
                                <form wire:submit.prevent="setQuota({{ $row['team']->id }})" class="flex gap-2">
                                    <label class="sr-only" for="q-{{ $row['team']->id }}">New limit in GB for {{ $row['team']->name }}</label>
                                    <input id="q-{{ $row['team']->id }}" type="text" wire:model="quota.{{ $row['team']->id }}" placeholder="default" class="input input-xs w-20 bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                                    <button type="submit" class="text-purple-400 hover:text-purple-300">Save</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-3 text-neutral-500">No customer has turned backups on yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-2" aria-labelledby="fl-h">
            <h2 id="fl-h" class="text-sm font-bold uppercase tracking-wider text-neutral-200">Recent failures</h2>
            @forelse ($failures as $f)
                <div class="text-xs border-t border-neutral-800 pt-2" wire:key="f-{{ $f->id }}">
                    <span class="text-neutral-300">{{ $f->scheduledDatabaseBackup?->team?->name }}</span>
                    <span class="text-neutral-500 font-mono ml-2">{{ $f->created_at->format('Y-m-d H:i') }}</span>
                    <div class="text-rose-300">{{ \Illuminate\Support\Str::limit((string) $f->message, 140) }}</div>
                </div>
            @empty
                <p class="text-xs text-neutral-500">No failures.</p>
            @endforelse
        </section>

        <section class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-2" aria-labelledby="rc-h">
            <h2 id="rc-h" class="text-sm font-bold uppercase tracking-wider text-neutral-200">Recent restores</h2>
            @forelse ($restores as $r)
                <div class="text-xs border-t border-neutral-800 pt-2" wire:key="r-{{ $r->id }}">
                    <span class="text-neutral-300">{{ $r->team?->name }}</span>
                    <span class="font-mono ml-2 {{ $r->status === 'failed' ? 'text-rose-400' : 'text-emerald-300' }}">{{ $r->status }}</span>
                    <div class="text-neutral-400">{{ $r->message }}</div>
                </div>
            @empty
                <p class="text-xs text-neutral-500">No restores yet.</p>
            @endforelse
        </section>
    </div>

    <section class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-2" aria-labelledby="rb-h">
        <h2 id="rb-h" class="text-sm font-bold uppercase tracking-wider text-neutral-200">Latest backups (restore for a customer)</h2>
        @forelse ($recent as $e)
            <div class="text-xs border-t border-neutral-800 pt-2 flex flex-wrap items-center justify-between gap-2" wire:key="b-{{ $e->id }}">
                <span><span class="text-neutral-200">{{ $e->scheduledDatabaseBackup?->team?->name }}</span> <span class="text-neutral-500 font-mono ml-2">{{ $e->created_at->format('Y-m-d H:i') }} &middot; {{ $e->size ? number_format($e->size / 1048576, 1).' MB' : '' }}</span></span>
                <button type="button" wire:click="restore('{{ $e->uuid }}')" wire:confirm="Restore this backup into the customer's database? A safety backup is taken first." class="text-amber-300 hover:text-amber-200">Restore</button>
            </div>
        @empty
            <p class="text-xs text-neutral-500">No backups yet.</p>
        @endforelse
    </section>
</div>
