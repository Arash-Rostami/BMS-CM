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
        Relationships,
        SoftDeletes,
        UserStamps,
        HasNotificationChannel,
        Setting,
        HasRecipient,
        ModelInspector;

    protected $fillable = [
        'settings',
        'notification_type',
        'notes',
        'user_id',
        'updated_by_id',
    ];

    protected $casts = [
        'settings' => 'array'
    ];
}
