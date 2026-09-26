<?php

namespace App\Services\Imports\Stages;

use App\Services\Imports\ImportRowContext;
use Closure;

class AttachPivotRelations
{
    public function handle(ImportRowContext $context, Closure $next): ImportRowContext
    {
        $notes = [];

        foreach ($context->pendingPivotAttaches as $attach) {
            $ids = [];

            foreach ($attach['values'] as $rawValue) {
                $value = trim((string) $rawValue);

                if (blank($value)) {
                    continue;
                }

                $related = $attach['model']::query()->where($attach['identifierColumn'], $value)->first();

                if (! $related) {
                    $notes[] = __('resources/general/strings.import.unresolved_match_left_blank', [
                        'label' => $attach['label'],
                        'value' => $value,
                    ]);

                    continue;
                }

                $ids[] = $related->getKey();
            }

            if ($ids) {
                $context->record->{$attach['relation']}()->syncWithoutDetaching($ids);
            }
        }

        if ($notes) {
            $context->record->notes = trim(implode("\n", array_filter([$context->record->notes, ...$notes])));
            $context->record->save();
        }

        return $next($context);
    }
}
