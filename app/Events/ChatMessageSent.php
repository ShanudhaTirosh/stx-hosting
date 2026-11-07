<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/** Tells open conversation pages to refresh. Carries no message text: pages reload it with their own permission check. */
class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public string $threadUuid, public string $messageUuid) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.thread.'.$this->threadUuid)];
    }

    public function broadcastAs(): string
    {
        return 'ChatMessageSent';
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->messageUuid];
    }
}
