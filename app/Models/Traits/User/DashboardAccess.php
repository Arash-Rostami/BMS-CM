<?php

namespace App\Models\Traits\User;

use App\Enums\AllowedDomain;
use Filament\Panel;
use Illuminate\Support\Str;

trait DashboardAccess
{
    public function canAccessPanel(Panel $panel): bool
    {
        return Str::endsWith($this->email, AllowedDomain::values());
    }
}
