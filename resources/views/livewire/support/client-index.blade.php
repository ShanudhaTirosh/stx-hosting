<div class="max-w-3xl mx-auto p-6 space-y-6">
    <div class="pb-4 border-b border-neutral-800">
        <h1 class="text-2xl font-bold text-white tracking-tight">Support</h1>
        <p class="text-xs text-neutral-400 mt-1">Talk to our team. Your whole team can see these conversations. We email you if we reply while you are away.</p>
    </div>

    <form wire:submit.prevent="start" class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-3">
        <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-200">New conversation</h2>
        <div>
            <label for="sup-subject" class="block text-xs font-bold text-neutral-300 mb-1">Subject</label>
            <input id="sup-subject" type="text" wire:model="subject" maxlength="150" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-sm">
            @error('subject') <span class="text-rose-400 text-xs" role="alert">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="sup-message" class="block text-xs font-bold text-neutral-300 mb-1">What do you need help with?</label>
            <textarea id="sup-message" wire:model="message" rows="4" maxlength="4000" class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-sm"></textarea>
            @error('message') <span class="text-rose-400 text-xs" role="alert">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="sup-files" class="block text-xs font-bold text-neutral-300 mb-1">Attach files (optional)</label>
            <input id="sup-files" type="file" wire:model="files" multiple class="file-input file-input-sm bg-neutral-950 border-neutral-700 text-xs">
            @error('files') <span class="text-rose-400 text-xs block" role="alert">{{ $message }}</span> @enderror
            @error('files.*') <span class="text-rose-400 text-xs block" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="flex justify-end"><button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs">Start conversation</button></div>
    </form>

    <ul class="space-y-2" aria-label="Your conversations">
        @forelse ($threads as $thread)
            <li>
                <a href="{{ route('portal.support.thread', $thread->uuid) }}" class="block bg-neutral-900 hover:bg-neutral-800 border border-neutral-800 rounded-xl p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-white">{{ $thread->subject }}</span>
                        <span class="text-[11px] font-mono {{ $thread->isOpen() ? 'text-emerald-300' : 'text-neutral-500' }}">{{ $thread->isOpen() ? 'Open' : 'Closed' }}</span>
                    </div>
                    <div class="text-[11px] text-neutral-500 mt-1">
                        Last message {{ $thread->last_message_at?->diffForHumans() }}
                        @if ($thread->last_sender_is_staff && (! $thread->client_read_at || $thread->client_read_at->lt($thread->last_message_at)))
                            <span class="ml-2 px-1.5 py-0.5 rounded bg-purple-600 text-white text-[10px]">New reply</span>
                        @endif
                    </div>
                </a>
            </li>
        @empty
            <li class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 text-xs text-neutral-400">No conversations yet.</li>
        @endforelse
    </ul>
</div>
