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

    public function getCustomAttributesMap(): array
    {
        return $this->customAttributes()
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

        $this->customAttributes()->whereNotIn('key', array_keys($keyValueMap))->delete();

        foreach ($keyValueMap as $key => $value) {
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
