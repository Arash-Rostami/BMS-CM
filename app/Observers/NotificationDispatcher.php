<?php

namespace App\Observers;

use App\Services\NotificationEvaluator;
use Illuminate\Database\Eloquent\Model;

class NotificationDispatcher
{
    public function __construct(
        private NotificationEvaluator $evaluator
    ) {}

    public function created(Model $model): void
    {
        $this->evaluator->evaluate($model, 'create');
    }

    public function updated(Model $model): void
    {
        $this->evaluator->evaluate($model, 'update', $model->getDirty());
    }

    public function deleted(Model $model): void
    {
        $this->evaluator->evaluate($model, 'delete');
    }
}
