<?php

namespace App\Models;

use App\Filament\Resources\Master\UserResource\Enums\UserRole;
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
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements CanResetPassword, FilamentUser, HasAvatar
{
    use DashboardAccess,
        HasAvatars,
        HasFactory,
        HasRoles,
        IpLookup,
        Notifiable,
        Relationships,
        Setting,
        SoftDeletes,
        UserImage;

    public const CACHE_MINUTES = 60;

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

    public function isAdmin(): bool
    {
        return $this->hasRole([
            UserRole::ADMIN_JUNIOR->value,
            UserRole::ADMIN_MID->value,
            UserRole::ADMIN_SENIOR->value,
        ]);
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (self $user) {
            $hasOrders = PurchaseOrder::where('user_id', $user->id)->orWhere('updated_by_id', $user->id)->exists()
                || RegisteredOrder::where('user_id', $user->id)->orWhere('updated_by_id', $user->id)->exists();

            if ($hasOrders) {
                throw new \RuntimeException("Cannot permanently delete user #{$user->id}: they are the creator or last updater of one or more PurchaseOrder/RegisteredOrder records — force-deleting would cascade-delete those records at the database level. Reassign those records' user_id/updated_by_id first.");
            }
        });
    }
}
