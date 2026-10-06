<?php

namespace App\Models\Traits\General;

use App\Models\EntityAttribute;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasCustomAttributes
{
    public function customAttributes(): MorphMany
    {
        return $this->morphMany(EntityAttribute::class, 'entity');
    }

    public function extraAttributes(): MorphMany
    {
        return $this->morphMany(EntityAttribute::class, 'entity');
    }

    public function reservedCustomAttributeKeys(): array
    {
        return [];
    }

    public function getCustomAttributesMap(): array
    {
        return $this->customAttributes()
            ->whereNotIn('key', $this->reservedCustomAttributeKeys())
            ->pluck('value', 'key')
            ->map(fn ($v) => match (true) {
                is_string($v) => $v,
                is_null($v) => '',
                default => json_encode($v, JSON_UNESCAPED_UNICODE),
            })
            ->toArray();
    }

    public function syncCustomAttributes(array $keyValueMap, ?int $userId = null): void
    {
        $userId ??= auth()->id();
        $reservedKeys = $this->reservedCustomAttributeKeys();

        $this->customAttributes()
            ->whereNotIn('key', array_merge(array_keys($keyValueMap), $reservedKeys))
            ->delete();

        foreach ($keyValueMap as $key => $value) {
            if (in_array($key, $reservedKeys, true)) {
                continue;
            }

            $value ??= '';

            $attribute = $this->customAttributes()->withTrashed()->firstWhere('key', $key);

            if ($attribute) {
                if ($attribute->trashed()) {
                    $attribute->restore();
                }

                $attribute->update(['value' => $value]);

                continue;
            }

            $this->customAttributes()->create(['key' => $key, 'value' => $value, 'user_id' => $userId]);
        }
    }
}
