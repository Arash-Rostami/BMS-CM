<?php

namespace App\Livewire;

use App\Services\PermissionLabeler;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Filament\Notifications\Notification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class CalendarDatabaseNotifications extends DatabaseNotifications
{
    public function getNotification(DatabaseNotification $notification): Notification
    {
        return Notification::fromArray($this->resolvedData($notification->data))
            ->id($notification->getKey())
            ->date($this->formatNotificationDate($notification->getAttributeValue('created_at')));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolvedData(array $data): array
    {
        if (is_array($data['title'] ?? null)) {
            $data['title'] = $this->isTranslatable($data['title']) ? $this->resolvedText($data['title']) : '';
            $data['body'] = $this->resolvedBody($data);
        }

        $data['actions'] = is_array($data['actions'] ?? null) ? $this->resolvedActions($data['actions']) : [];

        return $data;
    }

    /**
     * @param  array<int, mixed>  $actions
     * @return array<int, mixed>
     */
    private function resolvedActions(array $actions): array
    {
        foreach ($actions as $index => $action) {
            if (! is_array($action)) {
                unset($actions[$index]);

                continue;
            }

            if (is_string($action['key'] ?? null)) {
                $actions[$index] = ['label' => __($action['key'])] + $action;
            }

            unset($actions[$index]['key']);
        }

        return array_values($actions);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvedBody(array $data): string
    {
        $lines = is_array($data['items'] ?? null) ? $this->itemLines($data['items']) : [];

        if ($lines !== []) {
            return implode("\n", $lines);
        }

        if ($this->isTranslatable($data['body'] ?? null)) {
            return $this->resolvedText($data['body']);
        }

        return is_string($data['body'] ?? null) ? $data['body'] : '';
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    private function itemLines(array $items): array
    {
        $lines = [];

        foreach (array_slice($items, 0, 3) as $item) {
            if (is_array($item) && is_string($item['label'] ?? null) && is_string($item['kind'] ?? null)) {
                $lines[] = $item['label'].' · '.__('resources/calendarRule/strings.alerts.kinds.'.$item['kind']);
            }
        }

        if (count($items) > 3) {
            $lines[] = __('resources/calendarRule/strings.alerts.more', ['more' => count($items) - 3]);
        }

        return $lines;
    }

    private function isTranslatable(mixed $text): bool
    {
        return is_array($text) && is_string($text['key'] ?? null);
    }

    /**
     * @param  array{key: string, params?: array<string, mixed>}  $text
     */
    private function resolvedText(array $text): string
    {
        $params = array_filter(is_array($text['params'] ?? null) ? $text['params'] : [], 'is_scalar');

        if (is_string($params['module'] ?? null)) {
            $params['model'] = PermissionLabeler::getEntityLabel(Str::studly($params['module']));
        }

        return __($text['key'], $params);
    }
}
