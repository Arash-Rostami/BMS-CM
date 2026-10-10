<?php

namespace App\Notifications;

use App\Models\NotificationSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

abstract class BaseModelEventNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected const STRINGS = 'resources/notificationSetting/strings.notification.';

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        protected Model $model,
        protected string $action,
        protected array $changes,
        protected NotificationSetting $setting
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<string, mixed>
     */
    protected function bodyText(): array
    {
        $type = $this->action === 'update' && empty($this->changes) ? 'default' : $this->actionKey();

        return $this->text('body.'.$type, [
            'columns' => implode(', ', NotificationSetting::columnLabels([$this->model->getTable()], array_keys($this->changes))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function titleText(): array
    {
        return $this->text('title.'.$this->actionKey());
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{key: string, params: array<string, string>}
     */
    protected function text(string $suffix, array $extra = []): array
    {
        return [
            'key' => self::STRINGS.$suffix,
            'params' => [
                'model' => Str::headline(class_basename($this->model)),
                'module' => Str::snake(class_basename($this->model)),
                'identifier' => $this->getModelIdentifier(),
            ] + $extra,
        ];
    }

    protected function actionKey(): string
    {
        return in_array($this->action, NotificationSetting::ACTIONS, true) ? $this->action : 'default';
    }

    protected function getIcon(): string
    {
        return match ($this->action) {
            'create' => 'heroicon-o-plus-circle',
            'update' => 'heroicon-o-pencil-square',
            'delete' => 'heroicon-o-trash',
            default => 'heroicon-o-bell',
        };
    }

    protected function getIconColor(): string
    {
        return match ($this->action) {
            'create' => 'success',
            'update' => 'info',
            'delete' => 'danger',
            default => 'gray',
        };
    }

    protected function getRecordUrl(): string
    {
        $slug = Str::kebab(Str::pluralStudly(class_basename($this->model)));

        return $this->action === 'delete'
            ? "/dashboard/{$slug}"
            : "/dashboard/{$slug}/{$this->model->getKey()}/edit";
    }

    protected function getModelIdentifier(): string
    {
        $modelClass = get_class($this->model);
        $identifier = '';

        if (defined("{$modelClass}::SCANNABLE_IDENTIFIER")) {
            $identifier = $this->model->getAttribute($this->model::SCANNABLE_IDENTIFIER) ?: '';
        }

        if (empty($identifier)) {
            $identifier = "#{$this->model->getKey()}";
        }

        if ($contractNo = $this->model->getAttribute('contract_no')) {
            $identifier .= " ({$contractNo})";
        }

        return Str::limit((string) $identifier, 120);
    }
}
