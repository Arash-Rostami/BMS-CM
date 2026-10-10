<?php

namespace App\Observers;

use App\Services\NotificationEvaluator;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Throwable;

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
        $this->run($model, 'create');
    }

    public function updated(Model $model): void
    {
        if ($model->isDirty('deleted_at') && $model->getAttribute('deleted_at') === null) {
            return;
        }

        $this->run($model, 'update', $model->getDirty());
    }

    public function deleted(Model $model): void
    {
        $this->run($model, 'delete');
    }

    /**
     * @param  array<string, mixed>  $dirty
     */
    private function run(Model $model, string $action, array $dirty = []): void
    {
        if (static::$suspended) {
            return;
        }

        try {
            $this->evaluator->evaluate($model, $action, $dirty);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
