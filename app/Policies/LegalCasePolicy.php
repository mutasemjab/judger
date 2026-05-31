<?php

namespace App\Policies;

use App\Models\LegalCase;
use App\Models\User;

class LegalCasePolicy
{
    public function view(User $user, LegalCase $case): bool
    {
        return $user->id === $case->user_id;
    }

    public function update(User $user, LegalCase $case): bool
    {
        return $user->id === $case->user_id;
    }

    public function delete(User $user, LegalCase $case): bool
    {
        return $user->id === $case->user_id;
    }
}
