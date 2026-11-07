<?php

namespace App\Livewire\Support;

use App\Models\ChatThread;
use App\Services\Chat\ChatService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/** A customer's conversations with us, and a form to start a new one. */
class ClientIndex extends Component
{
    use WithFileUploads;

    public string $subject = '';

    public string $message = '';

    public array $files = [];

    public function start(ChatService $chat)
    {
        $team = currentTeam();
        abort_unless($team, 403, 'No active team.');

        $this->validate([
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:'.ChatService::MAX_BODY,
            'files' => 'array|max:'.ChatService::MAX_FILES,
            'files.*' => 'file|max:5120',
        ]);

        try {
            $thread = $chat->open(auth()->user(), $team, $this->subject, $this->message, $this->files);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field === 'reply' ? 'message' : $field, $messages[0]);
            }

            return null;
        }

        return redirect()->route('portal.support.thread', ['uuid' => $thread->uuid]);
    }

    public function render()
    {
        $team = currentTeam();

        return view('livewire.support.client-index', [
            'threads' => $team ? ChatThread::where('team_id', $team->id)->orderByDesc('last_message_at')->limit(50)->get() : collect(),
        ]);
    }
}
