<?php

namespace App\Notifications;

use App\Models\NotificationSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

abstract class BaseModelEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Model $model,
        protected string $action,
        protected array $changes,
        protected NotificationSetting $setting
    ) {}

    protected function buildBody(): string
    {
        $modelName = Str::headline(class_basename($this->model));
        $identifier = $this->getModelIdentifier();

        if ($this->action === 'update' && ! empty($this->changes)) {
            $columns = array_keys($this->changes);
            $columnsList = implode(', ', array_map(fn ($c) => Str::headline($c), $columns));

            return "{$modelName} {$identifier} updated: {$columnsList}";
        }

        return match ($this->action) {
            'create' => "{$modelName} {$identifier} has been created",
            'delete' => "{$modelName} {$identifier} has been deleted",
            default => "{$modelName} {$identifier} has changed",
        };
    }

    protected function buildTitle(string $modelName): string
    {
        return match ($this->action) {
            'create' => "{$modelName} Created",
            'update' => "{$modelName} Updated",
            'delete' => "{$modelName} Deleted",
            default => "{$modelName} Changed",
        };
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

    protected function getIntroLine(string $modelName, string $identifier): string
    {
        return match ($this->action) {
            'create' => "A new {$modelName} **{$identifier}** has been created.🟢",
            'update' => "The {$modelName} **{$identifier}** has been updated.🟡",
            'delete' => "The {$modelName} **{$identifier}** has been deleted.🔴",
            default => "The {$modelName} **{$identifier}** has changed.⚙️",
        };
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

        return $identifier;
    }

    protected function getSubject(string $modelName, string $identifier): string
    {
        return match ($this->action) {
            'create' => "{$modelName} Created: {$identifier}",
            'update' => "{$modelName} Updated: {$identifier}",
            'delete' => "{$modelName} Deleted: {$identifier}",
            default => "{$modelName} Changed: {$identifier}",
        };
    }
}
