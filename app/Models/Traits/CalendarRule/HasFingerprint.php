<?php

namespace App\Models\Traits\CalendarRule;

trait HasFingerprint
{
    private const ITEM_CONTAINERS = ['rules', 'groups'];

    public static function canonical(array $filters): array
    {
        return self::canonicalNode($filters);
    }

    public function computeFingerprints(): void
    {
        $identity = implode('|', [
            $this->subject,
            json_encode(static::canonical($this->filters ?? [])),
            $this->date_path,
        ]);

        $this->conditions_hash = sha1($identity);
        $this->fingerprint = sha1($identity.'|'.(int) $this->day_shift.'|'.$this->type?->value);
    }

    private static function canonicalNode(array $node, ?string $parentKey = null): array
    {
        $canonical = [];

        foreach ($node as $key => $value) {
            $canonical[$key] = is_array($value) ? self::canonicalNode($value, $key) : $value;
        }

        if ($parentKey !== null && in_array($parentKey, self::ITEM_CONTAINERS, true)) {
            $items = array_values($canonical);
            usort($items, fn (array $a, array $b): int => strcmp(json_encode($a), json_encode($b)));

            return $items;
        }

        ksort($canonical);

        return $canonical;
    }
}
