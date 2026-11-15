<form class="application-settings-form flex w-full flex-col gap-5" wire:submit="submit">
    <div class="flex flex-col sm:flex-row items-center gap-4 p-4 rounded-xl border border-neutral-200 dark:border-white/10 bg-neutral-50/50 dark:bg-white/[0.02]">
        <div class="relative size-16 shrink-0 rounded-xl overflow-hidden border border-neutral-200 dark:border-white/10 bg-white dark:bg-white/[0.05] flex items-center justify-center">
            @if ($logo)
                <img src="{{ $logo->temporaryUrl() }}" alt="Logo preview" class="size-full object-cover">
            @else
                <x-reicon name="teams" class="size-7 text-neutral-400 dark:text-fg-faint" />
            @endif
        </div>
        <div class="flex-1 min-w-0">
            <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-200 mb-1">Team Logo (Optional)</label>
            <p class="text-[11px] text-neutral-500 dark:text-fg-dim mb-2">Upload a square image (PNG, JPG, max 2MB) for your team workspace.</p>
            <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp"
                class="block w-full text-xs text-neutral-500 dark:text-fg-dim file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-neutral-200 file:text-neutral-800 dark:file:bg-white/[0.08] dark:file:text-fg hover:file:bg-neutral-300 dark:hover:file:bg-white/[0.12] cursor-pointer" />
            @error('logo') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
        </div>
    </div>

    <x-forms.input id="name" label="Name" placeholder="e.g. Acme Engineering" required />
    <x-forms.input id="description" label="Description" placeholder="e.g. Production and staging environments" />

    <div class="flex items-center justify-between pt-2">
        <span class="text-xs text-neutral-500 dark:text-fg-dim">Default seat limit: 5 members</span>
        <x-forms.button type="submit"
            wire:target="submit,logo"
            defaultClass="button button-highlighted">
            <span wire:loading.remove wire:target="submit">Create team</span>
            <span wire:loading wire:target="submit" class="flex items-center gap-1.5">
                <svg class="animate-spin size-3.5" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Creating...
            </span>
        </x-forms.button>
    </div>
</form>
