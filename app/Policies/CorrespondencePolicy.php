<?php

namespace App\Policies;

use App\Models\Correspondence;
use App\Models\User;

class CorrespondencePolicy
{

    public function forceDelete(User $user, Correspondence $correspondence): bool
    {
        return false;
    }

    public function view(User $user, Correspondence $correspondence): bool
    {
        // 1. Owner can always view.
        if ($correspondence->user_id === $user->id) return true;


        // 2. If private, only explicitly linked recipients may view.
        if ($correspondence->is_private) {
            return $correspondence->recipients()
                ->where('correspondence_recipients.user_id', $user->id)
                ->exists();
        }

        // 3. If internal, only internal users can view.
        if ($correspondence->is_internal) return $user->is_internal ?? false;


        // 4. Otherwise, it is visible.
        return true;
    }
}
