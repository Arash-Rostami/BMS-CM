<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\User;
use App\Notifications\ModelEventEmail;
use App\Notifications\ModelEventNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class NotificationEvaluator
{
    public function evaluate(Model $model, string $action, array $dirty = []): void
    {
        $tableName = $model->getTable();

        $settings = NotificationSetting::whereJsonContains('settings->tables', $tableName)
            ->whereJsonContains('settings->actions', $action)
            ->where(fn ($query) => $query->where('settings->is_active', true)->orWhereJsonLength('settings->is_active', 0))
            ->get();

        foreach ($settings as $setting) {
            if (! $this->shouldNotify($setting, $model, $action, $dirty)) {
                continue;
            }

            $this->dispatch($setting, $model, $action, $dirty);
        }
    }

    private function buildChangeData(Model $model, string $action, array $dirty): array
    {
        if ($action !== 'update' || empty($dirty)) {
            return [];
        }

        $changes = [];
        foreach ($dirty as $column => $newValue) {
            $oldValue = $model->getOriginal($column);

            $changes[$column] = [
                'old' => $this->resolveDisplayValue($model, $column, $oldValue),
                'new' => $this->resolveDisplayValue($model, $column, $newValue),
            ];
        }

        return $changes;
    }

    private function dispatch(NotificationSetting $setting, Model $model, string $action, array $dirty): void
    {
        $users = User::whereIn('id', $setting->getUsers())->get();
        if ($users->isEmpty()) {
            return;
        }

        $changedData = $this->buildChangeData($model, $action, $dirty);

        $notifications = [
            'in_app' => ModelEventNotification::class, 'email' => ModelEventEmail::class,
        ];

        foreach ($notifications as $type => $notificationClass) {
            if ($setting->notification_type === $type || $setting->notification_type === 'all') {
                Notification::send($users, new $notificationClass($model, $action, $changedData, $setting));
            }
        }
    }

    private function resolveDisplayValue(Model $model, string $column, $value)
    {
        if (str_ends_with($column, '_id') &&
            method_exists($model, $relationName = Str::camel(str_replace('_id', '', $column)))) {
            try {
                $relation = $model->{$relationName}();
                $relatedModel = $relation->getRelated();
                $displayColumn = $relatedModel->english_name ?? $relatedModel->name ?? 'name';

                return $relatedModel->where($relatedModel->getKeyName(), $value)->value($displayColumn) ?? $value;
            } catch (\Throwable $e) {
                return $value;
            }
        }

        return $value;
    }

    private function shouldNotify(NotificationSetting $setting, Model $model, string $action, array $dirty): bool
    {
        $columns = $setting->getColumns();
        $values = $setting->getValues();

        // CASE 1: No column filter → Notify on any action for this table
        if (empty($columns)) {
            return true;
        }

        // CASE 2: UPDATE action with column filters
        if ($action === 'update') {
            $changedColumns = array_keys($dirty);
            $relevantColumns = array_intersect($columns, $changedColumns);

            // None of the watched columns changed → Skip notification
            if (empty($relevantColumns)) {
                return false;
            }

            // Watched columns changed, but no value filter → Notify
            if (empty($values)) {
                return true;
            }

            // Check if ANY watched column (not just changed ones) has a value in the values pool
            foreach ($columns as $column) {
                $columnValue = $model->getAttribute($column);
                if (in_array($columnValue, $values, true)) {
                    return true;
                }
            }

            return false;
        }

        // CASE 3: CREATE/DELETE actions with column filters
        if (empty($values)) {
            return true;
        }

        // Check if ANY watched column has a value in the values pool
        foreach ($columns as $column) {
            $value = $model->getAttribute($column);
            if (in_array($value, $values, true)) {
                return true;
            }
        }

        return false;
    }
}
