<?php

namespace App\Observers;

use App\Models\EntityAttribute;
use App\Services\SmartCacheManager;

class EntityAttributeObserver
{
    public function saved(EntityAttribute $attribute): void
    {
        SmartCacheManager::invalidate('EntityAttribute');
    }

    public function deleted(EntityAttribute $attribute): void
    {
        SmartCacheManager::invalidate('EntityAttribute');
    }

    public function restored(EntityAttribute $attribute): void
    {
        SmartCacheManager::invalidate('EntityAttribute');
    }
}
