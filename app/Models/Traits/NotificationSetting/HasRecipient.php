<?php

namespace App\Models\Traits\NotificationSetting;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

trait HasRecipient
{
    public function getRecipientAttribute(): Collection
    {
        // From settings->users
        return User::whereIn('id', $this->getUsers())->get();
    }
}
