<div class="max-w-5xl mx-auto p-6 space-y-6">
    <div class="pb-4 border-b border-neutral-800 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Backups</h1>
            <p class="text-xs text-neutral-400 mt-1">Database backups are stored for you in our cloud storage. Before any restore we take a fresh backup, so you can always go back.</p>
        </div>
        <div class="text-xs font-mono text-neutral-300" role="status">
            Space used: {{ number_format($usedGb, 2) }} of {{ rtrim(rtrim(number_format($limitGb, 2), '0'), '.') }} GB
            <div class="mt-1 h-1.5 w-48 bg-neutral-800 rounded"><div class="h-1.5 rounded {{ $limitGb > 0 && $usedGb / $limitGb >= 0.9 ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ $limitGb > 0 ? min(100, round($usedGb / $limitGb * 100)) : 100 }}%"></div></div>
        </div>
    </div>

    @if ($error)
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-300 rounded-xl text-xs font-semibold" role="alert">{{ $error }}</div>
    @endif

    @unless ($available)
        <div class="p-4 bg-amber-500/10 border border-amber-500/20 text-amber-200 rounded-xl text-xs">Backups are not switched on for the platform yet. Please contact support.</div>
    @endunless

    @forelse ($rows as $row)
        @php($db = $row['db'])
        @php($config = $row['config'])
        <section class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4" aria-labelledby="db-{{ $db->uuid }}" wire:key="db-{{ $db->uuid }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="db-{{ $db->uuid }}" class="text-sm font-bold text-white">{{ $db->name }}</h2>
                    <p class="text-[11px] text-neutral-500 font-mono">{{ class_basename($db) }} &middot; {{ str($db->status)->before(':') }}</p>
                </div>
                <div class="text-xs">
                    @if ($config?->enabled)
                        <span class="px-2 py-0.5 rounded bg-emerald-950/60 text-emerald-300 font-mono">On &middot; keeps {{ $config->database_backup_retention_amount_s3 }}</span>
                    @else
                        <span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-400 font-mono">Off</span>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="p-{{ $db->uuid }}" class="block text-xs font-bold text-neutral-300 mb-1">When</label>
                    <select id="p-{{ $db->uuid }}" wire:model="settings.{{ $db->uuid }}.preset" class="select select-sm bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                        @foreach ($presets as $key => $p)<option value="{{ $key }}">{{ $p['label'] }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="k-{{ $db->uuid }}" class="block text-xs font-bold text-neutral-300 mb-1">Keep the last</label>
                    <select id="k-{{ $db->uuid }}" wire:model="settings.{{ $db->uuid }}.keep" class="select select-sm bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                        @foreach ($keepOptions as $n)<option value="{{ $n }}">{{ $n }} backups</option>@endforeach
                    </select>
                </div>
                <button type="button" wire:click="enable('{{ $db->uuid }}')" @disabled(! $available) class="px-3 py-2 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white font-bold rounded-lg text-xs">{{ $config?->enabled ? 'Update' : 'Turn on' }}</button>
                @if ($config?->enabled)
                    <button type="button" wire:click="runNow('{{ $db->uuid }}')" class="px-3 py-2 border border-neutral-600 text-neutral-200 hover:bg-neutral-800 rounded-lg text-xs">Back up now</button>
                    <button type="button" wire:click="disable('{{ $db->uuid }}')" wire:confirm="Turn backups off? Existing backups are kept." class="text-xs text-rose-400 hover:text-rose-300">Turn off</button>
                @endif
            </div>

            @if ($row['executions']->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <caption class="sr-only">Backups of {{ $db->name }}</caption>
                        <thead><tr class="text-left text-neutral-500"><th scope="col" class="py-1">Date</th><th scope="col">Result</th><th scope="col">Size</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody class="font-mono">
                            @foreach ($row['executions'] as $e)
                                <tr class="border-t border-neutral-800 align-top" wire:key="ex-{{ $e->uuid }}">
                                    <td class="py-1.5 text-neutral-300">{{ $e->created_at->format('Y-m-d H:i') }}</td>
                                    <td class="{{ $e->status === 'success' ? 'text-emerald-300' : ($e->status === 'failed' ? 'text-rose-400' : 'text-neutral-400') }}">{{ $e->status }}@if ($e->status === 'failed' && $e->message)<div class="text-[10px] text-neutral-500 font-sans max-w-xs">{{ \Illuminate\Support\Str::limit($e->message, 120) }}</div>@endif</td>
                                    <td class="text-neutral-400">{{ $e->size ? number_format($e->size / 1048576, 1).' MB' : '' }}</td>
                                    <td class="text-right">
                                        @if ($e->status === 'success' && $e->s3_uploaded)
                                            <button type="button" wire:click="download('{{ $e->uuid }}')" class="text-purple-400 hover:text-purple-300">Download</button>
                                            <details class="inline-block ml-3 text-left">
                                                <summary class="inline cursor-pointer text-amber-300 hover:text-amber-200">Restore</summary>
                                                <div class="mt-2 p-3 bg-neutral-950 border border-neutral-800 rounded space-y-2 font-sans">
                                                    <p class="text-[11px] text-neutral-400">Replaces the current data with this backup. We take a safety backup first.</p>
                                                    <label for="c-{{ $e->uuid }}" class="block text-[11px] font-bold text-neutral-300">Type RESTORE to confirm</label>
                                                    <input id="c-{{ $e->uuid }}" type="text" wire:model="confirm.{{ $e->uuid }}" autocomplete="off" class="input input-sm bg-neutral-900 border-neutral-700 text-white rounded text-xs">
                                                    @error('confirm.'.$e->uuid)<span class="text-rose-400 text-[11px] block" role="alert">{{ $message }}</span>@enderror
                                                    <button type="button" wire:click="restore('{{ $e->uuid }}')" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded text-xs">Restore this backup</button>
                                                </div>
                                            </details>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @empty
        <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-6 text-xs text-neutral-300">You have no databases to back up yet. Backups cover PostgreSQL, MySQL, MariaDB, MongoDB and ClickHouse databases. App volume backups are managed on the app's own Backups page.</div>
    @endforelse

    @if ($restores->isNotEmpty())
        <section aria-labelledby="rs-h" class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-2">
            <h2 id="rs-h" class="text-sm font-bold uppercase tracking-wider text-neutral-200">Recent restores</h2>
            @foreach ($restores as $r)
                <div class="text-xs flex flex-wrap justify-between gap-2" wire:key="rs-{{ $r->id }}">
                    <span class="text-neutral-300">{{ $r->created_at->format('Y-m-d H:i') }}</span>
                    <span class="{{ $r->status === 'failed' ? 'text-rose-400' : ($r->status === 'started' ? 'text-emerald-300' : 'text-amber-300') }} font-mono">{{ $r->status }}</span>
                    <span class="text-neutral-400 w-full sm:w-auto">{{ $r->message }}</span>
                </div>
            @endforeach
        </section>
    @endif
</div>
