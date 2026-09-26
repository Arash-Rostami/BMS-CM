<?php

namespace App\Observers;

use App\Models\Status;
use App\Services\SmartCacheManager;

class StatusObserver
{
    public function saved(Status $status): void
    {
        SmartCacheManager::invalidate('Status');
    }

    public function deleted(Status $status): void
    {
        SmartCacheManager::invalidate('Status');
    }

    public function restored(Status $status): void
    {
        SmartCacheManager::invalidate('Status');
    }
}
