<?php

namespace App\Policies;

use App\Models\Hearing;
use App\Models\User;

class HearingPolicy
{
    public function view(User $user, Hearing $hearing): bool
    {
        return $user->id === $hearing->user_id;
    }

    public function update(User $user, Hearing $hearing): bool
    {
        return $user->id === $hearing->user_id;
    }

    public function delete(User $user, Hearing $hearing): bool
    {
        return $user->id === $hearing->user_id;
    }
}
