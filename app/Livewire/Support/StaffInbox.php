<?php

namespace App\Livewire\Support;

use App\Models\ChatThread;
use App\Services\RbacService;
use Livewire\Component;

/** Staff inbox: every conversation, unread first. */
class StaffInbox extends Component
{
    public string $filter = 'open'; // open, mine, unread, closed

    public function boot(): void
    {
        abort_unless(RbacService::can(auth()->user(), 'chat.reply'), 403, 'Support staff only.');
    }

    public function render()
    {
        $query = ChatThread::with(['team', 'assignee', 'opener']);

        $query = match ($this->filter) {
            'closed' => $query->where('status', 'closed'),
            'mine' => $query->where('status', 'open')->where('assigned_to', auth()->id()),
            'unread' => $query->unreadForStaff(),
            default => $query->where('status', 'open'),
        };

        return view('livewire.support.staff-inbox', [
            'threads' => $query->orderByDesc('last_message_at')->limit(100)->get(),
        ]);
    }
}
