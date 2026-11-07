<div class="max-w-6xl mx-auto p-6 space-y-6">
    <div class="pb-4 border-b border-neutral-800">
        <h1 class="text-2xl font-bold text-white tracking-tight">Emails</h1>
        <p class="text-xs text-neutral-400 mt-1">Edit the wording of every email customers and staff receive. The preview uses sample values.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <nav class="lg:col-span-1 space-y-1" aria-label="Emails">
            @foreach ($catalog as $k => $item)
                <button type="button" wire:click="select('{{ $k }}')" @if ($k === $key) aria-current="true" @endif
                    class="w-full text-left px-3 py-2 rounded text-xs {{ $k === $key ? 'bg-purple-600 text-white font-bold' : 'bg-neutral-900 text-neutral-300 hover:bg-neutral-800' }}">
                    {{ $item['label'] }}
                    @if (in_array($k, $overridden, true))<span class="ml-1 text-[10px] opacity-70">(edited)</span>@endif
                </button>
            @endforeach
        </nav>

        <div class="lg:col-span-3 grid grid-cols-1 xl:grid-cols-2 gap-6">
            <form wire:submit.prevent="save" class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-3">
                <div>
                    <label for="et-subject" class="block text-xs font-bold text-neutral-300 mb-1">Subject</label>
                    <input id="et-subject" type="text" wire:model.live.debounce.400ms="subject" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                    @error('subject') <span class="text-rose-400 text-xs" role="alert">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="et-heading" class="block text-xs font-bold text-neutral-300 mb-1">Heading</label>
                    <input id="et-heading" type="text" wire:model.live.debounce.400ms="heading" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                    @error('heading') <span class="text-rose-400 text-xs" role="alert">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="et-body" class="block text-xs font-bold text-neutral-300 mb-1">Message (markdown)</label>
                    <textarea id="et-body" wire:model.live.debounce.400ms="body" rows="12" class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono"></textarea>
                    @error('body') <span class="text-rose-400 text-xs" role="alert">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="et-cta" class="block text-xs font-bold text-neutral-300 mb-1">Button text</label>
                        <input id="et-cta" type="text" wire:model.live.debounce.400ms="ctaText" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                    </div>
                    <div>
                        <label for="et-path" class="block text-xs font-bold text-neutral-300 mb-1">Button link (on this site)</label>
                        <input id="et-path" type="text" wire:model.live.debounce.400ms="ctaPath" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                        @error('ctaPath') <span class="text-rose-400 text-xs" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="text-[11px] text-neutral-400">
                    <span class="font-bold text-neutral-300">Variables you can use:</span>
                    @foreach ($variables as $name => $sample)<code class="ml-1 text-emerald-400">{{ '{'.$name.'}' }}</code>@endforeach
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-neutral-800">
                    <div class="flex gap-3 text-xs">
                        <button type="button" wire:click="sendTest" class="text-purple-400 hover:text-purple-300">Send me a test</button>
                        <button type="button" wire:click="resetToDefault" wire:confirm="Go back to the built-in wording?" class="text-neutral-300 hover:text-white">Reset</button>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs">Save</button>
                </div>
            </form>

            <section class="bg-white text-neutral-900 rounded-xl p-5 overflow-auto" aria-label="Preview">
                <div class="text-[11px] text-neutral-500">Subject</div>
                <div class="font-semibold mb-3">{{ $preview['subject'] }}</div>
                <h2 class="text-lg font-bold mb-2">{{ $preview['heading'] }}</h2>
                <div class="text-sm leading-relaxed [&_a]:underline [&_ul]:list-disc [&_ul]:pl-5 [&_blockquote]:border-l-4 [&_blockquote]:pl-3 [&_blockquote]:text-neutral-600">{!! $preview['html'] !!}</div>
                @if ($preview['cta_text'] && $preview['cta_url'])
                    <span class="inline-block mt-4 px-4 py-2 bg-neutral-900 text-white text-sm rounded">{{ $preview['cta_text'] }}</span>
                @endif
            </section>
        </div>
    </div>
</div>
