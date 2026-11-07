<?php

namespace App\Policies;

use App\Models\ChatThread;
use App\Models\User;
use App\Services\RbacService;

/** Customers see their own team's conversations; staff with chat.reply see all of them. */
class ChatThreadPolicy
{
    public function view(User $user, ChatThread $thread): bool
    {
        return RbacService::can($user, 'chat.reply') || $user->teams->contains('id', $thread->team_id);
    }

    public function reply(User $user, ChatThread $thread): bool
    {
        return $this->view($user, $thread);
    }

    /** Assign, close and reopen are staff actions. */
    public function manage(User $user, ChatThread $thread): bool
    {
        return RbacService::can($user, 'chat.reply');
    }
}
