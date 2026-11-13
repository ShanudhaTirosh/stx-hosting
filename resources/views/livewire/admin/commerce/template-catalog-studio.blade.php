<div class="p-6 space-y-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-neutral-800">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Template Catalog Studio</h1>
            <p class="text-xs text-neutral-400 mt-1">
                Curate which application templates customers can deploy. Control resource limits, categories, and visibility.
            </p>
        </div>

        <button type="button" wire:click="openNewModal" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs flex items-center gap-2 shadow transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add New Template
        </button>
    </div>

    <!-- Filters & Search -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <span class="text-xs text-neutral-400 font-medium">Category:</span>
            <select wire:model.live="selectedCategory" class="select select-sm bg-neutral-900 border-neutral-800 text-white text-xs rounded-lg">
                <option value="all">All Categories</option>
                <option value="cms">CMS & Sites</option>
                <option value="blog">Publishing & Blogs</option>
                <option value="automation">Automation</option>
                <option value="cloud">Storage & Cloud</option>
                <option value="analytics">Analytics</option>
                <option value="monitoring">Monitoring</option>
            </select>
        </div>

        <div class="w-72">
            <input type="text" wire:model.live.debounce.250ms="search" placeholder="Search templates..." class="input input-sm w-full bg-neutral-900 border-neutral-800 text-white text-xs rounded-lg focus:border-purple-500">
        </div>
    </div>

    <!-- Templates Table -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden shadow-sm">
        <table class="w-full text-left text-xs">
            <thead class="bg-neutral-950 text-neutral-400 font-semibold border-b border-neutral-800 uppercase tracking-wider text-[10px]">
                <tr>
                    <th class="py-3 px-4">Template</th>
                    <th class="py-3 px-4">Category</th>
                    <th class="py-3 px-4">Port</th>
                    <th class="py-3 px-4">Min Specs</th>
                    <th class="py-3 px-4 text-center">Featured</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-800/60 font-mono">
                @forelse ($templates as $tmpl)
                    <tr class="hover:bg-neutral-850 transition" wire:key="tmpl-{{ $tmpl->id }}">
                        <td class="py-3.5 px-4 font-sans">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-neutral-800 border border-neutral-700/60 flex items-center justify-center p-1.5 flex-shrink-0">
                                    @if ($tmpl->icon_url)
                                        <img src="{{ $tmpl->icon_url }}" alt="" class="w-full h-full object-contain">
                                    @else
                                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-bold text-white text-sm">{{ $tmpl->display_name ?: $tmpl->template_name }}</div>
                                    <div class="text-[11px] text-neutral-500 font-mono">{{ $tmpl->template_name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-4 font-sans">
                            <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-neutral-800 text-neutral-300">
                                {{ $tmpl->category ?: 'app' }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-neutral-300">
                            :{{ $tmpl->default_port ?: 80 }}
                        </td>
                        <td class="py-3.5 px-4 text-neutral-300 text-[11px]">
                            {{ $tmpl->min_ram_mb }}MB RAM / {{ $tmpl->min_vcpu }} vCPU
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            <button type="button" wire:click="toggleFeatured({{ $tmpl->id }})" class="p-1 rounded hover:bg-neutral-800 transition">
                                @if ($tmpl->is_featured)
                                    <span class="text-amber-400 font-sans text-xs font-bold">★ Popular</span>
                                @else
                                    <span class="text-neutral-600 font-sans text-xs">☆ Normal</span>
                                @endif
                            </button>
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            <button type="button" wire:click="toggleEnabled({{ $tmpl->id }})" class="px-2.5 py-1 rounded-full text-[11px] font-sans font-semibold transition {{ $tmpl->is_globally_enabled ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-800/60' : 'bg-rose-950/80 text-rose-300 border border-rose-800/60' }}">
                                {{ $tmpl->is_globally_enabled ? 'Enabled' : 'Disabled' }}
                            </button>
                        </td>
                        <td class="py-3.5 px-4 text-right font-sans">
                            <button type="button" wire:click="edit({{ $tmpl->id }})" class="text-purple-400 hover:text-purple-300 font-semibold text-xs px-2 py-1 rounded hover:bg-neutral-800">
                                Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-neutral-500 font-sans text-xs">
                            No templates found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Edit / Add Modal -->
    @if ($showEditModal)
        <div class="fixed inset-0 z-50 bg-black/75 flex items-center justify-center p-4">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-xl w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-800">
                    <h3 class="text-base font-bold text-white">
                        {{ $editingTemplateId ? 'Edit Template: ' . $template_name : 'Add New Template' }}
                    </h3>
                    <button type="button" wire:click="$set('showEditModal', false)" class="text-neutral-500 hover:text-white">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    @unless ($editingTemplateId)
                        <div>
                            <label class="block font-semibold text-neutral-300 mb-1">Template Identifier (Slug)</label>
                            <input type="text" wire:model="template_name" placeholder="e.g. strapi or rust-api" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded font-mono">
                            @error('template_name') <span class="text-rose-400 mt-0.5 block">{{ $message }}</span> @enderror
                        </div>
                    @endunless

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-neutral-300 mb-1">Display Title</label>
                            <input type="text" wire:model="display_name" placeholder="e.g. WordPress CMS" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded">
                            @error('display_name') <span class="text-rose-400 mt-0.5 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-semibold text-neutral-300 mb-1">Category</label>
                            <select wire:model="category" class="select select-sm w-full bg-neutral-950 border-neutral-700 text-white rounded">
                                <option value="cms">CMS & Sites</option>
                                <option value="blog">Publishing & Blogs</option>
                                <option value="automation">Automation</option>
                                <option value="cloud">Storage & Cloud</option>
                                <option value="analytics">Analytics</option>
                                <option value="monitoring">Monitoring</option>
                                <option value="database">Database</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-neutral-300 mb-1">Short Description</label>
                        <textarea wire:model="short_description" rows="2" placeholder="Brief explanation of what this app does..." class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded"></textarea>
                    </div>

                    <div class="grid grid-cols-3 gap-3 font-mono">
                        <div>
                            <label class="block font-sans font-semibold text-neutral-300 mb-1">Internal Port</label>
                            <input type="number" wire:model="default_port" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded">
                        </div>
                        <div>
                            <label class="block font-sans font-semibold text-neutral-300 mb-1">Min RAM (MB)</label>
                            <input type="number" wire:model="min_ram_mb" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded">
                        </div>
                        <div>
                            <label class="block font-sans font-semibold text-neutral-300 mb-1">Min vCPU</label>
                            <input type="number" step="0.1" wire:model="min_vcpu" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded">
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-neutral-300 mb-1">Icon URL</label>
                        <input type="text" wire:model="icon_url" placeholder="/images/logos/app.svg or https://..." class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded font-mono">
                    </div>

                    <div class="flex items-center gap-6 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_globally_enabled" class="checkbox checkbox-sm checkbox-primary">
                            <span class="font-semibold text-neutral-300">Enabled for Customers</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_featured" class="checkbox checkbox-sm checkbox-warning">
                            <span class="font-semibold text-neutral-300">Featured / Popular</span>
                        </label>
                    </div>
                </div>

                <div class="pt-4 flex justify-end gap-2 border-t border-neutral-800">
                    <button type="button" wire:click="$set('showEditModal', false)" class="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 rounded-lg text-xs">
                        Cancel
                    </button>
                    <button type="button" wire:click="save" class="px-5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs shadow">
                        Save Template
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

