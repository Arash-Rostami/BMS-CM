<?php

namespace App\Notifications;

class ModelEventNotification extends BaseModelEventNotification
{
    public function toDatabase($notifiable): array
    {
        $modelName = class_basename($this->model);

        return [
            'title' => $this->buildTitle($modelName),
            'body' => $this->buildBody(),
            'actions' => [
                [
                    'name' => 'view',
                    'label' => 'View Record',
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
