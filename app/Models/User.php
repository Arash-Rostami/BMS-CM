<?php

namespace App\Models;

use App\Models\Traits\User\DashboardAccess;
use App\Models\Traits\User\IpLookup;
use App\Models\Traits\User\Relationships;
use App\Models\Traits\User\Setting;
use App\Models\Traits\User\UserImage;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel\Concerns\HasAvatars;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasAvatar, CanResetPassword
{
    use HasFactory, Notifiable, SoftDeletes, HasAvatars, UserImage,
        DashboardAccess, Relationships, IpLookup, Setting;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'last_log_in',
        'last_log_out',
        'department_id',
        'position',
        'role',
        'image',
        'status',
        'ip',
        'company',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_log_in' => 'datetime',
            'last_log_out' => 'datetime',
            'settings' => 'json',
            'deleted_at' => 'datetime',
        ];
    }
}
