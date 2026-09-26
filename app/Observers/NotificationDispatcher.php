<?php

namespace App\Observers;

use App\Services\NotificationEvaluator;
use Closure;
use Illuminate\Database\Eloquent\Model;

class NotificationDispatcher
{
    protected static bool $suspended = false;

    public function __construct(
        private NotificationEvaluator $evaluator
    ) {}

    public static function suspended(Closure $callback): mixed
    {
        $previous = static::$suspended;
        static::$suspended = true;

        try {
            return $callback();
        } finally {
            static::$suspended = $previous;
        }
    }

    public function created(Model $model): void
    {
        if (static::$suspended) {
            return;
        }

        $this->evaluator->evaluate($model, 'create');
    }

    public function updated(Model $model): void
    {
        if (static::$suspended) {
            return;
        }

        $this->evaluator->evaluate($model, 'update', $model->getDirty());
    }

    public function deleted(Model $model): void
    {
        if (static::$suspended) {
            return;
        }

        $this->evaluator->evaluate($model, 'delete');
    }
}
