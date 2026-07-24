<?php

namespace App\Models;

use App\Models\Traits\General\ModelInspector;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\NotificationSetting\HasNotificationChannel;
use App\Models\Traits\NotificationSetting\HasRecipient;
use App\Models\Traits\NotificationSetting\Setting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationSetting extends Model
{
    use HasFactory,
        HasNotificationChannel,
        HasRecipient,
        ModelInspector,
        Relationships,
        Setting,
        SoftDeletes,
        UserStamps;

    protected $fillable = [
        'settings',
        'notification_type',
        'notes',
        'user_id',
        'updated_by_id',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (blank($model->user_id)) {
                throw new \RuntimeException('NotificationSetting requires an explicit user_id when created outside an authenticated context — UserStamps only auto-fills it when auth()->check() is true.');
            }
        });
    }
}
