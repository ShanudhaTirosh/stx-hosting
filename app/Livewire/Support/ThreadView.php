<?php

namespace App\Livewire\Support;

use App\Models\ChatThread;
use App\Services\Chat\ChatService;
use App\Services\RbacService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/** One conversation. Used by customers (/portal/support/{uuid}) and staff (/admin/support/{uuid}). */
class ThreadView extends Component
{
    use WithFileUploads;

    public string $uuid = '';

    public string $reply = '';

    public array $files = [];

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $this->thread(); // 404 unless this person may see it
    }

    private function thread(): ChatThread
    {
        $thread = ChatThread::where('uuid', $this->uuid)->first();
        abort_unless($thread && auth()->user()->can('view', $thread), 404);

        return $thread;
    }

    public function getListeners(): array
    {
        // Live updates when the realtime server is running; the page also polls as a fallback.
        return ["echo-private:chat.thread.{$this->uuid},.ChatMessageSent" => '$refresh'];
    }

    public function send(ChatService $chat): void
    {
        $this->validate([
            'reply' => 'nullable|string|max:'.ChatService::MAX_BODY,
            'files' => 'array|max:'.ChatService::MAX_FILES,
            'files.*' => 'file|max:5120',
        ]);

        try {
            $chat->post($this->thread(), auth()->user(), $this->reply, $this->files);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->reset(['reply', 'files']);
    }

    public function assignToMe(ChatService $chat): void
    {
        $chat->assignToMe($this->thread(), auth()->user());
    }

    public function close(ChatService $chat): void
    {
        $chat->close($this->thread(), auth()->user());
    }

    public function reopen(ChatService $chat): void
    {
        $chat->reopen($this->thread(), auth()->user());
    }

    public function render(ChatService $chat)
    {
        $thread = $this->thread();
        $user = auth()->user();

        $chat->touchPresence($thread, $user);
        $chat->markRead($thread, $user);

        $isStaff = RbacService::can($user, 'chat.reply');

        return view('livewire.support.thread-view', [
            'thread' => $thread->fresh(['team', 'assignee', 'opener']),
            'messages' => $thread->messages()->with(['author', 'attachments'])->get(),
            'isStaff' => $isStaff,
            'canManage' => $user->can('manage', $thread),
            'backUrl' => $isStaff ? route('admin.support') : route('portal.support'),
        ]);
    }
}
