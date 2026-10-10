<?php

namespace App\Models;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\CalendarColor;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility;
use App\Models\Casts\OutsideEmailsCast;
use App\Models\Casts\SharedUserIdsCast;
use App\Models\Traits\CalendarRule\HasFingerprint;
use App\Models\Traits\CalendarRule\HasVisibility;
use App\Models\Traits\CalendarRule\Relationships as ExclusiveRelationships;
use App\Models\Traits\General\HasScope;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\NotificationSetting\HasNotificationChannel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class CalendarRule extends Model
{
    use ExclusiveRelationships,
        HasFactory,
        HasFingerprint,
        HasNotificationChannel,
        HasScope,
        HasVisibility,
        Relationships,
        SoftDeletes,
        UserStamps;

    protected $fillable = [
        'name',
        'subject',
        'filters',
        'extra_paths',
        'date_path',
        'day_shift',
        'lead_times',
        'on_day',
        'notification_type',
        'type',
        'color',
        'visibility',
        'shared_user_ids',
        'shared_role_ids',
        'notify_emails',
        'is_active',
        'watched_columns',
        'user_id',
        'updated_by_id',
    ];

    protected $casts = [
        'filters' => 'array',
        'extra_paths' => 'array',
        'lead_times' => 'array',
        'shared_user_ids' => SharedUserIdsCast::class,
        'shared_role_ids' => SharedUserIdsCast::class,
        'notify_emails' => OutsideEmailsCast::class,
        'related_models' => 'array',
        'watched_columns' => 'array',
        'day_shift' => 'integer',
        'on_day' => 'boolean',
        'is_active' => 'boolean',
        'type' => RuleType::class,
        'visibility' => Visibility::class,
        'color' => CalendarColor::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if (! $model->shouldSendEmail()) {
                $model->notify_emails = null;
            }

            if ($model->visibility !== Visibility::ROLES) {
                $model->shared_role_ids = null;
            }
        });

        static::creating(function (self $model): void {
            if (blank($model->user_id)) {
                throw new RuntimeException('CalendarRule requires an explicit user_id when created outside an authenticated context — UserStamps only auto-fills it when auth()->check() is true.');
            }
        });
    }
}
