<?php

namespace App\Services\Chat;

use App\Events\ChatMessageSent;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Commerce\ChatReplyNotification;
use App\Services\Notifications\StaffEvents;
use App\Services\RbacService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Support conversations between a customer team and staff.
 *
 * - history is kept forever on the thread
 * - files are checked by content, stored on a private disk and only served through a permission check
 * - a customer who is not looking at the conversation gets an email when staff reply
 * - staff are alerted through the Discord router
 */
class ChatService
{
    public const MAX_BODY = 4000;

    public const MAX_FILES = 3;

    public const MAX_FILE_BYTES = 5 * 1024 * 1024;

    private const ALLOWED = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
    ];

    /** Seconds a conversation page counts as "being looked at" after its last refresh. */
    public const PRESENCE_SECONDS = 90;

    /** Do not email the customer again about the same conversation within this many minutes. */
    public const EMAIL_COOLDOWN_MINUTES = 10;

    /** @param  array<int, UploadedFile>  $files */
    public function open(User $user, Team $team, string $subject, string $body, array $files = []): ChatThread
    {
        $subject = trim($subject);
        if ($subject === '' || mb_strlen($subject) > 150) {
            throw ValidationException::withMessages(['subject' => 'Give the conversation a short subject (up to 150 characters).']);
        }

        $thread = ChatThread::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'subject' => $subject,
            'status' => 'open',
        ]);

        StaffEvents::notify(StaffEvents::TICKET_CREATED, 'New support conversation', "{$user->name}: {$subject}", [
            'Team' => (string) $team->name,
        ], 'warning', 'thread:'.$thread->id);

        $this->post($thread, $user, $body, $files);

        return $thread->fresh();
    }

    /** @param  array<int, UploadedFile>  $files */
    public function post(ChatThread $thread, User $author, string $body, array $files = []): ChatMessage
    {
        abort_unless($author->can('reply', $thread), 403);

        $isStaff = RbacService::can($author, 'chat.reply');
        $body = trim(str_replace("\0", '', $body));

        if ($body === '' && $files === []) {
            throw ValidationException::withMessages(['reply' => 'Write a message or attach a file.']);
        }
        if (mb_strlen($body) > self::MAX_BODY) {
            throw ValidationException::withMessages(['reply' => 'That message is too long. The limit is '.self::MAX_BODY.' characters.']);
        }
        if (count($files) > self::MAX_FILES) {
            throw ValidationException::withMessages(['files' => 'Attach up to '.self::MAX_FILES.' files per message.']);
        }
        if ($isStaff && ! $thread->isOpen()) {
            throw ValidationException::withMessages(['reply' => 'This conversation is closed. Reopen it first.']);
        }

        if (! RateLimiter::attempt("chat-post:{$author->id}", 20, fn () => true, 60)) {
            throw ValidationException::withMessages(['reply' => 'You are sending messages too quickly. Wait a moment.']);
        }

        $message = DB::transaction(function () use ($thread, $author, $isStaff, $body, $files) {
            $message = ChatMessage::create([
                'chat_thread_id' => $thread->id,
                'user_id' => $author->id,
                'is_staff' => $isStaff,
                'body' => $body,
            ]);

            foreach ($files as $file) {
                $this->storeAttachment($thread, $message, $file);
            }

            $thread->forceFill([
                'status' => 'open', // a customer writing again reopens a closed conversation
                'last_message_at' => now(),
                'last_sender_is_staff' => $isStaff,
                ($isStaff ? 'staff_read_at' : 'client_read_at') => now(),
                'assigned_to' => $isStaff ? ($thread->assigned_to ?? $author->id) : $thread->assigned_to,
            ])->save();

            return $message;
        });

        try {
            broadcast(new ChatMessageSent($thread->uuid, $message->uuid))->toOthers();
        } catch (\Throwable $e) {
            logger()->warning('Chat broadcast failed: '.$e->getMessage());
        }

        $isStaff ? $this->emailCustomerIfAway($thread, $message) : $this->alertStaff($thread, $author, $message);

        return $message;
    }

    public function markRead(ChatThread $thread, User $reader): void
    {
        if (RbacService::can($reader, 'chat.reply')) {
            $thread->forceFill(['staff_read_at' => now()])->save();
        } else {
            // Reading it means the customer is up to date: a future reply may email them again.
            $thread->forceFill(['client_read_at' => now(), 'client_emailed_at' => null])->save();
        }
    }

    /** Called every time a conversation page refreshes. */
    public function touchPresence(ChatThread $thread, User $viewer): void
    {
        Cache::put($this->presenceKey($thread, $viewer), true, self::PRESENCE_SECONDS);
    }

    public function isCustomerPresent(ChatThread $thread): bool
    {
        return Cache::has("chat-presence:{$thread->id}:client");
    }

    public function unreadForCustomer(User $user): int
    {
        $teamIds = $user->teams->pluck('id');

        return ChatThread::whereIn('team_id', $teamIds)->unreadForClient()->count();
    }

    public function unreadForStaff(): int
    {
        return ChatThread::unreadForStaff()->count();
    }

    public function close(ChatThread $thread, User $by): void
    {
        abort_unless($by->can('manage', $thread), 403);
        $thread->update(['status' => 'closed']);
    }

    public function reopen(ChatThread $thread, User $by): void
    {
        abort_unless($by->can('manage', $thread), 403);
        $thread->update(['status' => 'open']);
    }

    public function assignToMe(ChatThread $thread, User $by): void
    {
        abort_unless($by->can('manage', $thread), 403);
        $thread->update(['assigned_to' => $by->id]);
    }

    private function presenceKey(ChatThread $thread, User $viewer): string
    {
        return 'chat-presence:'.$thread->id.':'.(RbacService::can($viewer, 'chat.reply') ? 'staff' : 'client');
    }

    private function storeAttachment(ChatThread $thread, ChatMessage $message, UploadedFile $file): ChatAttachment
    {
        $path = $file->getRealPath();

        if ($path === false || filesize($path) > self::MAX_FILE_BYTES) {
            throw ValidationException::withMessages(['files' => 'Each file can be up to 5 MB.']);
        }

        $bytes = file_get_contents($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if (! array_key_exists($mime, self::ALLOWED)) {
            throw ValidationException::withMessages(['files' => 'Only PNG, JPEG, WEBP, PDF and plain text files can be attached.']);
        }

        $key = 'chat/'.$thread->uuid.'/'.Str::uuid().'.'.self::ALLOWED[$mime];
        Storage::disk(config('commerce.chat_disk', 'local'))->put($key, $bytes);

        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', $file->getClientOriginalName()) ?: 'file';

        return ChatAttachment::create([
            'chat_message_id' => $message->id,
            'path' => $key,
            'original_name' => mb_substr($name, 0, 200),
            'mime' => $mime,
            'size_bytes' => strlen($bytes),
        ]);
    }

    private function alertStaff(ChatThread $thread, User $author, ChatMessage $message): void
    {
        StaffEvents::notify(StaffEvents::CHAT_MESSAGE, 'New message: '.$thread->subject, "{$author->name}: ".Str::limit($message->body, 300), [
            'Team' => (string) $thread->team?->name,
        ], 'info', 'chat:'.$thread->id.':'.intdiv(time(), 300)); // at most one alert per conversation per 5 minutes
    }

    private function emailCustomerIfAway(ChatThread $thread, ChatMessage $message): void
    {
        if ($this->isCustomerPresent($thread)) {
            return;
        }

        $recent = $thread->client_emailed_at && $thread->client_emailed_at->gt(now()->subMinutes(self::EMAIL_COOLDOWN_MINUTES));
        $recipient = $thread->opener;

        if ($recent || ! $recipient) {
            return;
        }

        try {
            $recipient->notify(new ChatReplyNotification($message->load('thread')));
            $thread->forceFill(['client_emailed_at' => now()])->save();
        } catch (\Throwable $e) {
            logger()->warning('Could not email chat reply: '.$e->getMessage());
        }
    }
}
