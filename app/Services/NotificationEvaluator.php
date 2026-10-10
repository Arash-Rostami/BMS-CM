<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\User;
use App\Notifications\ModelEventEmail;
use App\Notifications\ModelEventNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class NotificationEvaluator
{
    private const SYSTEM_COLUMNS = ['updated_at', 'updated_by_id'];

    private const CHANNELS = [
        'in_app' => ModelEventNotification::class,
        'email' => ModelEventEmail::class,
    ];

    /**
     * @var array<string, mixed>
     */
    private array $displayValues = [];

    /**
     * @param  array<string, mixed>  $dirty
     */
    public function evaluate(Model $model, string $action, array $dirty = []): void
    {
        $this->displayValues = [];
        $settings = $this->matchingSettings($model, $action, $dirty);

        if ($settings->isEmpty()) {
            return;
        }

        $recipients = $this->recipients($settings, $model);

        foreach ($settings as $setting) {
            try {
                $this->dispatch($setting, $model, $action, $dirty, $recipients);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $dirty
     * @return Collection<int, NotificationSetting>
     */
    private function matchingSettings(Model $model, string $action, array $dirty): Collection
    {
        return NotificationSetting::activeRules()->filter(
            fn (NotificationSetting $setting): bool => in_array($model->getTable(), $setting->getTables(), true)
                && in_array($action, $setting->getActions(), true)
                && $this->shouldNotify($setting, $model, $action, $dirty)
        )->values();
    }

    /**
     * @param  Collection<int, NotificationSetting>  $settings
     * @return Collection<int, User>
     */
    private function recipients(Collection $settings, Model $model): Collection
    {
        $ids = $settings->flatMap(fn (NotificationSetting $setting): array => $setting->getUsers())->unique()->values();
        $permission = Str::snake(class_basename($model)).'.view';

        return User::query()
            ->whereIn('id', $ids)
            ->where('status', '!=', 'inactive')
            ->with(['roles', 'permissions'])
            ->get()
            ->filter(fn (User $user): bool => $user->can($permission))
            ->keyBy('id');
    }

    /**
     * @param  array<string, mixed>  $dirty
     * @param  Collection<int, User>  $recipients
     */
    private function dispatch(NotificationSetting $setting, Model $model, string $action, array $dirty, Collection $recipients): void
    {
        $users = $recipients->only(array_map('intval', $setting->getUsers()))->values();

        if ($users->isEmpty()) {
            return;
        }

        $changes = $this->buildChangeData($model, $action, $dirty, $setting);

        foreach (self::CHANNELS as $type => $notificationClass) {
            if (in_array($setting->notification_type, [$type, 'all'], true)) {
                $this->send($users, new $notificationClass($model, $action, $changes, $setting));
            }
        }
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function send(Collection $users, object $notification): void
    {
        foreach ($users as $user) {
            try {
                Notification::send($user, clone $notification);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $dirty
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function buildChangeData(Model $model, string $action, array $dirty, NotificationSetting $setting): array
    {
        if ($action !== 'update') {
            return [];
        }

        $watched = $setting->getColumns();
        $changes = [];

        foreach ($dirty as $column => $newValue) {
            if (in_array($column, self::SYSTEM_COLUMNS, true) || ($watched !== [] && ! in_array($column, $watched, true))) {
                continue;
            }

            $changes[$column] = [
                'old' => $this->resolveDisplayValue($model, $column, $model->getOriginal($column)),
                'new' => $this->resolveDisplayValue($model, $column, $newValue),
            ];
        }

        return $changes;
    }

    private function resolveDisplayValue(Model $model, string $column, mixed $value): mixed
    {
        $relationName = Str::camel(Str::beforeLast($column, '_id'));

        if (! str_ends_with($column, '_id') || ! is_scalar($value) || ! method_exists($model, $relationName)) {
            return $value;
        }

        return $this->displayValues[$model::class.'|'.$column.'|'.$value] ??= $this->lookupDisplayValue($model, $relationName, $value);
    }

    private function lookupDisplayValue(Model $model, string $relationName, mixed $value): mixed
    {
        try {
            return NameSearch::labels($model->{$relationName}()->getRelated(), [$value])[$value] ?? $value;
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @param  array<string, mixed>  $dirty
     */
    private function shouldNotify(NotificationSetting $setting, Model $model, string $action, array $dirty): bool
    {
        $columns = $setting->getColumns();

        if ($columns === []) {
            return true;
        }

        if ($setting->hasMalformedValues()) {
            return false;
        }

        $values = $setting->getColumnValues();

        return $action === 'update'
            ? $this->updateMatches($model, $columns, $values, $dirty)
            : $this->currentMatches($model, $values);
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<string, array<int, mixed>>  $values
     * @param  array<string, mixed>  $dirty
     */
    private function updateMatches(Model $model, array $columns, array $values, array $dirty): bool
    {
        foreach (array_intersect($columns, array_keys($dirty)) as $column) {
            if (! isset($values[$column])) {
                return true;
            }

            $new = NotificationValueNormalizer::normalize($dirty[$column], $model->getTable(), $column);
            $old = NotificationValueNormalizer::normalize($model->getOriginal($column), $model->getTable(), $column);

            if ($new !== null && $new !== $old && $this->listed($model, $column, $new, $values[$column], $old, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<int, mixed>>  $values
     */
    private function currentMatches(Model $model, array $values): bool
    {
        if ($values === []) {
            return true;
        }

        foreach ($values as $column => $list) {
            $current = NotificationValueNormalizer::normalize($model->getAttribute($column), $model->getTable(), $column);

            if ($current !== null && $this->listed($model, $column, $current, $list)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $list
     */
    private function listed(Model $model, string $column, string $normalized, array $list, ?string $previous = null, bool $isUpdate = false): bool
    {
        foreach ($list as $candidate) {
            if (NotificationValueNormalizer::matches($candidate, $normalized, $model->getTable(), $column, $previous, $isUpdate)) {
                return true;
            }
        }

        return false;
    }
}
