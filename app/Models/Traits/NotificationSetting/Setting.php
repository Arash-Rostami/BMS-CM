<?php

namespace App\Models\Traits\NotificationSetting;

trait Setting
{
    public function getActions(): array
    {
        return $this->settings['actions'] ?? [];
    }

    public function getAllSettings(): array
    {
        return $this->settings ?? [];
    }

    public function getColumns(): array
    {
        return $this->settings['columns'] ?? [];
    }

    public function getLocalizedActions(): array
    {
        return collect($this->getActions())->map(fn ($action) => match ($action) {
            'create' => __('resources/notificationSetting/strings.action_types.create'),
            'update' => __('resources/notificationSetting/strings.action_types.update'),
            'delete' => __('resources/notificationSetting/strings.action_types.delete'),
            default => $action,
        })->all();
    }

    public function getLocalizedTables(): array
    {
        return array_map(fn ($table) => static::getLocalizedTableLabel($table), $this->getTables());
    }

    public function getTables(): array
    {
        return $this->settings['tables'] ?? [];
    }

    public function getUsers(): array
    {
        return $this->settings['users'] ?? [];
    }

    public function getValues(): array
    {
        return $this->settings['values'] ?? [];
    }

    public function isActive(): bool
    {
        return $this->settings['is_active'] ?? false;
    }
}
