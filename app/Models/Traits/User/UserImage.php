<?php

namespace App\Models\Traits\User;

use Illuminate\Support\Facades\Vite;

trait UserImage
{
    public function getFilamentAvatarUrl(): ?string
    {
        return Vite::asset(sprintf('%s%s.svg', 'resources/img/avatars/', strtolower($this->role)));
    }
}
