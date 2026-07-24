<?php

namespace App\Models\Traits\NotificationSetting;

trait HasNotificationChannel
{
    public const NOTIFICATION_CHANNEL_IN_APP = 'in_app';

    public const NOTIFICATION_CHANNEL_EMAIL = 'email';

    public const NOTIFICATION_CHANNEL_ALL = 'all';

    public function getNotificationChannelAttribute(): string
    {
        return self::notificationChannel()[$this->notification_type]
            ?? __('resources/notificationSetting/strings.channels.unknown');
    }

    public static function notificationChannel(): array
    {
        return [
            self::NOTIFICATION_CHANNEL_IN_APP => __('resources/notificationSetting/strings.channels.in_app'),
            self::NOTIFICATION_CHANNEL_EMAIL => __('resources/notificationSetting/strings.channels.email'),
            self::NOTIFICATION_CHANNEL_ALL => __('resources/notificationSetting/strings.channels.all'),
        ];
    }

    public function shouldSendEmail(): bool
    {
        return in_array($this->notification_type, [
            self::NOTIFICATION_CHANNEL_EMAIL,
            self::NOTIFICATION_CHANNEL_ALL,
        ], true);
    }

    public function shouldSendInApp(): bool
    {
        return in_array($this->notification_type, [
            self::NOTIFICATION_CHANNEL_IN_APP,
            self::NOTIFICATION_CHANNEL_ALL,
        ], true);
    }
}
