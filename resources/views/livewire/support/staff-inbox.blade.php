<div class="max-w-5xl mx-auto p-6 space-y-5" wire:poll.15s>
    <div class="pb-4 border-b border-neutral-800 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Support inbox</h1>
            <p class="text-xs text-neutral-400 mt-1">Customer conversations, newest first.</p>
        </div>
        <div class="flex gap-2 text-xs" role="group" aria-label="Filter">
            @foreach (['open' => 'Open', 'unread' => 'Unread', 'mine' => 'Mine', 'closed' => 'Closed'] as $value => $text)
                <button type="button" wire:click="$set('filter', '{{ $value }}')" @if ($filter === $value) aria-pressed="true" @endif
                    class="px-3 py-1.5 rounded {{ $filter === $value ? 'bg-purple-600 text-white font-bold' : 'bg-neutral-900 text-neutral-300 hover:bg-neutral-800' }}">{{ $text }}</button>
            @endforeach
        </div>
    </div>

    <ul class="space-y-2">
        @forelse ($threads as $thread)
            @php($unread = ! $thread->last_sender_is_staff && (! $thread->staff_read_at || $thread->staff_read_at->lt($thread->last_message_at)) && $thread->isOpen())
            <li wire:key="t-{{ $thread->id }}">
                <a href="{{ route('admin.support.thread', $thread->uuid) }}" class="block bg-neutral-900 hover:bg-neutral-800 border {{ $unread ? 'border-purple-500/50' : 'border-neutral-800' }} rounded-xl p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm {{ $unread ? 'font-bold text-white' : 'font-semibold text-neutral-200' }}">{{ $thread->subject }}</span>
                        <span class="text-[11px] font-mono text-neutral-500">{{ $thread->last_message_at?->diffForHumans() }}</span>
                    </div>
                    <div class="text-[11px] text-neutral-500 mt-1">
                        {{ $thread->team?->name }} &middot; {{ $thread->opener?->name }} &middot; assigned to {{ $thread->assignee?->name ?? 'nobody' }}
                        @if ($unread)<span class="ml-2 px-1.5 py-0.5 rounded bg-purple-600 text-white text-[10px]">Unread</span>@endif
                    </div>
                </a>
            </li>
        @empty
            <li class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 text-xs text-neutral-400">Nothing here.</li>
        @endforelse
    </ul>
</div>
