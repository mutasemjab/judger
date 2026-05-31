<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        return $message->conversation && $user->id === $message->conversation->user_id;
    }

    public function pin(User $user, Message $message): bool
    {
        return $message->conversation && $user->id === $message->conversation->user_id;
    }
}
