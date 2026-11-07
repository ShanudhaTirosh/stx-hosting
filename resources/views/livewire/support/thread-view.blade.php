<div class="max-w-3xl mx-auto p-6 space-y-5" wire:poll.10s>
    <div class="flex flex-wrap items-start justify-between gap-3 pb-4 border-b border-neutral-800">
        <div>
            <a href="{{ $backUrl }}" class="text-xs text-purple-400 hover:text-purple-300 font-semibold">&larr; All conversations</a>
            <h1 class="text-xl font-bold text-white tracking-tight mt-1">{{ $thread->subject }}</h1>
            <p class="text-[11px] text-neutral-500 font-mono mt-1">
                {{ $thread->team?->name }} &middot; opened by {{ $thread->opener?->name }}
                @if ($isStaff) &middot; assigned to {{ $thread->assignee?->name ?? 'nobody' }}@endif
                &middot; {{ $thread->isOpen() ? 'open' : 'closed' }}
            </p>
        </div>
        @if ($canManage)
            <div class="flex gap-3 text-xs">
                <button type="button" wire:click="assignToMe" class="text-purple-400 hover:text-purple-300">Assign to me</button>
                @if ($thread->isOpen())
                    <button type="button" wire:click="close" class="text-neutral-300 hover:text-white">Close</button>
                @else
                    <button type="button" wire:click="reopen" class="text-neutral-300 hover:text-white">Reopen</button>
                @endif
            </div>
        @endif
    </div>

    <ol class="space-y-3" aria-label="Messages">
        @foreach ($messages as $m)
            <li class="rounded-xl border p-4 {{ $m->is_staff ? 'bg-purple-950/30 border-purple-500/20' : 'bg-neutral-900 border-neutral-800' }}" wire:key="m-{{ $m->id }}">
                <div class="flex items-center justify-between text-[11px] text-neutral-400 mb-1">
                    <span class="font-semibold {{ $m->is_staff ? 'text-purple-300' : 'text-neutral-200' }}">{{ $m->author?->name ?? 'Unknown' }}@if ($m->is_staff) <span class="ml-1 px-1.5 py-0.5 rounded bg-purple-600 text-white text-[10px]">Staff</span>@endif</span>
                    <time datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->diffForHumans() }}</time>
                </div>
                @if ($m->body !== '')
                    <div class="text-sm text-neutral-100 whitespace-pre-line break-words">{{ $m->body }}</div>
                @endif
                @if ($m->attachments->isNotEmpty())
                    <ul class="mt-2 flex flex-wrap gap-2">
                        @foreach ($m->attachments as $a)
                            <li>
                                <a href="{{ route('support.attachment', $a->uuid) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-2 py-1 rounded bg-neutral-800 text-xs text-purple-300 hover:text-purple-200">
                                    {{ $a->original_name }} <span class="text-neutral-500">({{ number_format($a->size_bytes / 1024, 0) }} KB)</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ol>

    @if (! $thread->isOpen() && $isStaff)
        <p class="text-xs text-neutral-400">This conversation is closed. Reopen it to reply.</p>
    @else
        <form wire:submit.prevent="send" class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-3">
            <div>
                <label for="chat-reply" class="block text-xs font-bold text-neutral-300 mb-1">{{ $isStaff ? 'Reply to the customer' : 'Your message' }}</label>
                <textarea id="chat-reply" wire:model="reply" rows="3" maxlength="4000" class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-sm"></textarea>
                @error('reply') <span class="text-rose-400 text-xs block mt-1" role="alert">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="chat-files" class="block text-xs font-bold text-neutral-300 mb-1">Attach files (up to 3, 5 MB each: PNG, JPEG, WEBP, PDF, TXT)</label>
                <input id="chat-files" type="file" wire:model="files" multiple class="file-input file-input-sm bg-neutral-950 border-neutral-700 text-xs">
                @error('files') <span class="text-rose-400 text-xs block mt-1" role="alert">{{ $message }}</span> @enderror
                @error('files.*') <span class="text-rose-400 text-xs block mt-1" role="alert">{{ $message }}</span> @enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 disabled:opacity-60 text-white font-bold rounded-lg text-xs">Send</button>
            </div>
        </form>
    @endif
</div>
