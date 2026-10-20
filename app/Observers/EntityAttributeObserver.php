<?php

namespace App\Observers;

use App\Models\EntityAttribute;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\SmartCacheManager;

class EntityAttributeObserver
{
    public function saved(EntityAttribute $attribute): void
    {
        $this->invalidate($attribute);
    }

    public function deleted(EntityAttribute $attribute): void
    {
        $this->invalidate($attribute);
    }

    public function restored(EntityAttribute $attribute): void
    {
        $this->invalidate($attribute);
    }

    private function invalidate(EntityAttribute $attribute): void
    {
        SmartCacheManager::invalidate('EntityAttribute');

        if ($attribute->key === 'commercial_invoice') {
            CheckRegistry::invalidate();
        }
    }
}
