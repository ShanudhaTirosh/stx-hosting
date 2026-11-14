<div class="max-w-5xl mx-auto p-6 space-y-6">
    <div class="pb-4 border-b border-neutral-800 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">{{ $service->name }}</h1>
            <p class="text-xs text-neutral-400 font-mono mt-1">{{ $service->ip_address }} &middot; {{ $service->os_image }} &middot; {{ $service->hostname }}</p>
        </div>
        <a href="{{ route('portal.service.detail', ['uuid' => $service->uuid]) }}" class="text-xs text-purple-400 hover:text-purple-300 font-semibold">Login details</a>
    </div>

    @if ($error)
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-300 rounded-xl text-xs font-semibold" role="alert">{{ $error }}</div>
    @endif

    @unless ($hasAccess)
        <section class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-3" aria-labelledby="access-h">
            <h2 id="access-h" class="text-sm font-bold uppercase tracking-wider text-neutral-200">Turn on panel access</h2>
            <p class="text-xs text-neutral-400 leading-relaxed">
                This installs a key on your server so we can show usage, browse files and open the terminal. After that you can change your root password whenever you like.
                Leave the password empty to use the one we delivered. If you changed it, type the current one. We do not store it.
            </p>
            <form wire:submit.prevent="enablePanelAccess" class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[16rem]">
                    <label for="root-pw" class="block text-xs font-bold text-neutral-300 mb-1">Root password (optional)</label>
                    <input id="root-pw" type="password" wire:model="rootPassword" autocomplete="off" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                </div>
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 disabled:opacity-60 text-white font-bold rounded-lg text-xs">
                    <span wire:loading.remove wire:target="enablePanelAccess">Enable panel access</span>
                    <span wire:loading wire:target="enablePanelAccess">Connecting...</span>
                </button>
            </form>
        </section>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <section class="lg:col-span-2 bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4" aria-labelledby="usage-h">
                <h2 id="usage-h" class="text-sm font-bold uppercase tracking-wider text-neutral-200">Usage</h2>
                @if ($latest)
                    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-mono">
                        <div><dt class="text-neutral-500 uppercase text-[10px]">CPU</dt><dd class="text-white text-lg font-bold">{{ $latest->cpu_percent !== null ? number_format($latest->cpu_percent, 0).'%' : 'n/a' }}</dd></div>
                        <div><dt class="text-neutral-500 uppercase text-[10px]">Memory</dt><dd class="text-white text-lg font-bold">{{ $latest->mem_used_mb }} / {{ $latest->mem_total_mb }} MB</dd></div>
                        <div><dt class="text-neutral-500 uppercase text-[10px]">Disk</dt><dd class="text-white text-lg font-bold">{{ number_format($latest->disk_used_gb, 1) }} / {{ $latest->disk_total_gb }} GB</dd></div>
                        <div><dt class="text-neutral-500 uppercase text-[10px]">Traffic this month</dt><dd class="text-white text-lg font-bold">{{ number_format($trafficGb, 1) }}@if ($trafficLimitGb) / {{ $trafficLimitGb }}@endif GB</dd></div>
                    </dl>
                    @if ($cpuLine)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-[10px] text-neutral-500 font-mono">
                            <figure><svg viewBox="0 0 240 40" class="w-full h-10" role="img" aria-label="CPU use over the last hour"><polyline fill="none" stroke="#a78bfa" stroke-width="1.5" points="{{ $cpuLine }}"/></svg><figcaption>CPU, last hour</figcaption></figure>
                            <figure><svg viewBox="0 0 240 40" class="w-full h-10" role="img" aria-label="Memory use over the last hour"><polyline fill="none" stroke="#34d399" stroke-width="1.5" points="{{ $memLine }}"/></svg><figcaption>Memory, last hour</figcaption></figure>
                        </div>
                    @endif
                    <p class="text-[11px] text-neutral-500">Updated {{ $latest->collected_at->diffForHumans() }}. Uptime {{ gmdate('z', $latest->uptime_seconds) }} days {{ gmdate('H:i', $latest->uptime_seconds) }}.</p>
                @else
                    <p class="text-xs text-neutral-400">No usage data yet. The first reading arrives within a minute.</p>
                @endif
            </section>

            <section class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-3" aria-labelledby="power-h">
                <h2 id="power-h" class="text-sm font-bold uppercase tracking-wider text-neutral-200">Restart</h2>
                <p class="text-xs text-neutral-400 leading-relaxed">Reboots the server. Running processes stop. There is no power off button; contact us if you need the server stopped.</p>
                <form wire:submit.prevent="restart" class="space-y-2">
                    <label for="restart-confirm" class="block text-xs font-bold text-neutral-300">Type RESTART to confirm</label>
                    <input id="restart-confirm" type="text" wire:model="restartConfirm" autocomplete="off" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                    @error('restartConfirm') <span class="text-rose-400 text-xs block" role="alert">{{ $message }}</span> @enderror
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-lg text-xs">Restart server</button>
                </form>
            </section>
        </div>

        <section class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-3" aria-labelledby="term-h">
            <div class="flex items-center justify-between">
                <h2 id="term-h" class="text-sm font-bold uppercase tracking-wider text-neutral-200">Terminal</h2>
                <button type="button" wire:click="$dispatchTo('portal.vps-terminal', 'start-vps-terminal')" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs">Open terminal</button>
            </div>
            <livewire:portal.vps-terminal :service-uuid="$service->uuid" :key="'term-'.$service->uuid" />
        </section>

        <section class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4" aria-labelledby="files-h">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-neutral-800">
                <div>
                    <h2 id="files-h" class="text-sm font-bold uppercase tracking-wider text-neutral-200">Files (SFTP over SSH)</h2>
                    <p class="text-[11px] text-neutral-400">Encrypted file management over SSH. (Plain FTP is disabled for data security).</p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($filesLoaded)
                        <button type="button" wire:click="openNew('file')" class="px-2.5 py-1 bg-neutral-800 hover:bg-neutral-700 text-neutral-200 font-semibold text-xs rounded border border-neutral-700">+ New File</button>
                        <button type="button" wire:click="openNew('dir')" class="px-2.5 py-1 bg-neutral-800 hover:bg-neutral-700 text-neutral-200 font-semibold text-xs rounded border border-neutral-700">+ New Folder</button>
                    @endif
                    <button type="button" wire:click="loadFiles" class="px-3 py-1 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded shadow">{{ $filesLoaded ? 'Refresh' : 'Browse files' }}</button>
                </div>
            </div>

            @if ($filesLoaded)
                <div class="flex items-center gap-3 text-xs font-mono text-neutral-300">
                    <button type="button" wire:click="up" class="text-purple-400 hover:text-purple-300 font-bold">&larr; Up</button>
                    <span class="break-all text-neutral-400">{{ $path }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <caption class="sr-only">Contents of {{ $path }}</caption>
                        <thead><tr class="text-left text-neutral-500 font-mono text-[11px]"><th scope="col" class="py-1">Name</th><th scope="col">Size</th><th scope="col">Changed</th><th scope="col" class="text-right">Actions</th></tr></thead>
                        <tbody class="font-mono">
                            @forelse ($entries as $entry)
                                <tr class="border-t border-neutral-800 hover:bg-neutral-800/40" wire:key="e-{{ $loop->index }}-{{ $entry['name'] }}">
                                    <td class="py-1.5">
                                        @if ($entry['type'] === 'dir')
                                            <button type="button" wire:click="open(@js($entry['name']))" class="text-purple-300 hover:text-purple-200 font-bold flex items-center gap-1.5">
                                                <span>📁</span> {{ $entry['name'] }}/
                                            </button>
                                        @else
                                            <span class="text-neutral-200 flex items-center gap-1.5">
                                                <span>📄</span> {{ $entry['name'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-neutral-400">{{ $entry['type'] === 'dir' ? 'Folder' : number_format($entry['size'] / 1024, 1).' KB' }}</td>
                                    <td class="text-neutral-500">{{ $entry['modified'] ? date('Y-m-d H:i', $entry['modified']) : '' }}</td>
                                    <td class="text-right space-x-2 font-sans text-xs">
                                        @if ($entry['type'] === 'file')
                                            <button type="button" wire:click="editFile(@js($entry['name']))" class="text-purple-400 hover:text-purple-300 font-semibold">Edit</button>
                                            <button type="button" wire:click="download(@js($entry['name']))" class="text-indigo-400 hover:text-indigo-300">Download</button>
                                        @endif
                                        <button type="button" wire:click="openRename(@js($entry['name']))" class="text-neutral-400 hover:text-neutral-300">Rename</button>
                                        <button type="button" wire:click="deleteEntry(@js($entry['name']))" wire:confirm="Delete {{ $entry['name'] }}? This cannot be undone." class="text-rose-400 hover:text-rose-300">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-4 text-center text-neutral-500">This folder is empty.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <form wire:submit.prevent="uploadFile" class="flex flex-wrap items-end gap-3 pt-3 border-t border-neutral-800">
                    <div>
                        <label for="vps-upload" class="block text-xs font-bold text-neutral-300 mb-1">Upload file to this folder (up to 10 MB)</label>
                        <input id="vps-upload" type="file" wire:model="upload" class="file-input file-input-sm bg-neutral-950 border-neutral-700 text-xs">
                        @error('upload') <span class="text-rose-400 text-xs block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                    <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs">Upload via SFTP</button>
                </form>
            @else
                <p class="text-xs text-neutral-400">Browse, edit, rename, download, upload and delete files on your server over secure SFTP. For large batch transfers use an external SFTP client such as FileZilla with your login details.</p>
            @endif
        </section>
    @endunless

    <!-- File Editor Modal -->
    @if ($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="bg-neutral-900 border border-neutral-700 rounded-xl max-w-4xl w-full p-6 space-y-4 shadow-2xl flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <div>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Editing: {{ $editingFileName }}</h3>
                        <p class="text-xs text-neutral-500 font-mono">{{ $path }}/{{ $editingFileName }}</p>
                    </div>
                    <button wire:click="$set('showEditModal', false)" class="text-neutral-400 hover:text-white">&times;</button>
                </div>

                <div class="flex-1 min-h-[300px]">
                    <textarea 
                        wire:model="fileContent" 
                        rows="18" 
                        class="w-full h-full bg-neutral-950 border border-neutral-700 text-purple-200 font-mono text-xs p-3 rounded-lg focus:outline-none focus:border-purple-500 leading-relaxed resize-y"
                    ></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-neutral-800">
                    <button wire:click="$set('showEditModal', false)" class="px-3.5 py-1.5 bg-neutral-800 text-neutral-300 rounded text-xs">Cancel</button>
                    <button wire:click="saveFileContent" class="px-4 py-1.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded text-xs">Save File over SFTP</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Rename Modal -->
    @if ($showRenameModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="bg-neutral-900 border border-neutral-700 rounded-xl max-w-sm w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Rename Entry</h3>
                    <button wire:click="$set('showRenameModal', false)" class="text-neutral-400 hover:text-white">&times;</button>
                </div>

                <div>
                    <label class="block text-xs font-bold text-neutral-300 mb-1">New Name</label>
                    <input type="text" wire:model="renameNewName" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white text-xs font-mono rounded" />
                    @error('renameNewName') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-neutral-800">
                    <button wire:click="$set('showRenameModal', false)" class="px-3 py-1.5 bg-neutral-800 text-neutral-300 rounded text-xs">Cancel</button>
                    <button wire:click="renameEntry" class="px-3.5 py-1.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded text-xs">Rename</button>
                </div>
            </div>
        </div>
    @endif

    <!-- New Entry Modal -->
    @if ($showNewModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="bg-neutral-900 border border-neutral-700 rounded-xl max-w-sm w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Create {{ ucfirst($newEntryType) }}</h3>
                    <button wire:click="$set('showNewModal', false)" class="text-neutral-400 hover:text-white">&times;</button>
                </div>

                <div>
                    <label class="block text-xs font-bold text-neutral-300 mb-1">{{ ucfirst($newEntryType) }} Name</label>
                    <input type="text" wire:model="newEntryName" placeholder="e.g. app.env or configs" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white text-xs font-mono rounded" />
                    @error('newEntryName') <span class="text-rose-400 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-neutral-800">
                    <button wire:click="$set('showNewModal', false)" class="px-3 py-1.5 bg-neutral-800 text-neutral-300 rounded text-xs">Cancel</button>
                    <button wire:click="createEntry" class="px-3.5 py-1.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded text-xs">Create</button>
                </div>
            </div>
        </div>
    @endif
</div>
