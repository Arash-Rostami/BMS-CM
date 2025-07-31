<?php

namespace App\Models\Traits\General;

use Illuminate\Support\Facades\App;

trait HasLocalizedAttributes
{
    public function getLocalizedAttribute(string $attributeBaseName): string
    {
        $locale = app()->getLocale();
        $columnName = $this->localizedAttributesMap[$attributeBaseName][$locale] ?? null;

        if (!$columnName) {
            $columnName = $this->localizedAttributesMap[$attributeBaseName]['en'] ?? $attributeBaseName;
        }

        return $this->{$columnName} ?? '';
    }

    public function __get($key)
    {
        if (str_starts_with($key, 'localized_')) {
            $attributeBaseName = substr($key, strlen('localized_'));

            if (isset($this->localizedAttributesMap[$attributeBaseName])) {
                return $this->getLocalizedAttribute($attributeBaseName);
            }
        }

        return parent::__get($key);
    }
}
