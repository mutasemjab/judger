<?php

namespace App\Policies;

use App\Models\CaseDocument;
use App\Models\User;

class CaseDocumentPolicy
{
    public function view(User $user, CaseDocument $document): bool
    {
        return $user->id === $document->user_id;
    }

    public function delete(User $user, CaseDocument $document): bool
    {
        return $user->id === $document->user_id;
    }

    public function download(User $user, CaseDocument $document): bool
    {
        return $user->id === $document->user_id;
    }
}
