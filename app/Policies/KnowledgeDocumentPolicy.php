<?php

namespace App\Policies;

use App\Models\KnowledgeDocument;
use App\Models\User;

class KnowledgeDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, KnowledgeDocument $document): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, KnowledgeDocument $document): bool
    {
        return $user->isAdmin();
    }
}
