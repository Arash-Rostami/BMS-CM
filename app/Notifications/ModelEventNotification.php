<?php

namespace App\Notifications;

class ModelEventNotification extends BaseModelEventNotification
{
    public function toDatabase($notifiable): array
    {
        return [
            'title' => $this->titleText(),
            'body' => $this->bodyText(),
            'actions' => [
                [
                    'name' => 'view',
                    'key' => self::STRINGS.'action_view',
                    'url' => $this->getRecordUrl(),
                    'shouldMarkAsRead' => true,
                ],
            ],
            'icon' => $this->getIcon(),
            'iconColor' => $this->getIconColor(),
            'format' => 'filament',
            'duration' => 'persistent',
        ];
    }

    public function via($notifiable): array
    {
        return ['database'];
    }
}
