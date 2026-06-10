<?php

namespace App\Models\Traits\User;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

trait UserImage
{
    public function getFilamentAvatarUrl(): ?string
    {
        $roleName = $this->roles->first()?->name ?? $this->role ?? 'agent';

        $baseRole = Str::before($roleName, '_');

        return Vite::asset(sprintf('%s%s.png', 'resources/img/avatars/', strtolower($baseRole)));
    }
}
