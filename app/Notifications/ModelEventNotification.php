<?php

namespace App\Notifications;

class ModelEventNotification extends BaseModelEventNotification
{
    public function toDatabase($notifiable): array
    {
        $modelName = class_basename($this->model);
        $tableName = $this->model->getTable();
        $recordId = $this->model->getKey();

        return [
            'title' => $this->buildTitle($modelName),
            'body' => $this->buildBody(),
            'actions' => [
                [
                    'name' => 'view',
                    'label' => 'View Record',
                    'url' => "/dashboard/{$tableName}/{$recordId}/edit",
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
