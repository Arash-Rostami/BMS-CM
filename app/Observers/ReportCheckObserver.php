<?php

namespace App\Observers;

use App\Models\BankProfile;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Rules\FxRateService;
use Illuminate\Database\Eloquent\Model;

class ReportCheckObserver
{
    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        CheckRegistry::invalidate();

        if ($model instanceof BankProfile) {
            FxRateService::flush();
        }
    }
}
