<?php

namespace App\Services;

use Filament\Infolists\Components\Component;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SearchExtractorService
{
    public function extractDetails(Model $record, string $resourceClass): array
    {
        if (!class_exists($resourceClass)) {
            return [];
        }

        $infolist = Infolist::make()->record($record);
        $schema = $resourceClass::infolist($infolist)->getComponents();

        $details = [];
        $this->extractEntries($schema, $record, $details);

        // Add relationship summary if relationships exist and are loaded
        $this->extractRelationships($record, $details);

        return $details;
    }

    private function extractEntries(array $components, Model $record, array &$details): void
    {
        foreach ($components as $component) {
            if ($component instanceof Entry) {
                // Determine label
                $label = $component->getLabel() ?? Str::headline($component->getName());
                $name = $component->getName();

                // Determine value based on entry type or state
                $value = $this->resolveEntryValue($component, $record, $name);

                if (!is_null($value) && $value !== '' && $value !== '—') {
                    // Try to evaluate closures if passed
                    if ($label instanceof \Closure) {
                         $label = $label();
                    }
                    if (!is_string($label)) {
                        $label = Str::headline($name);
                    }

                    $details[] = [
                        'label' => $label,
                        'value' => is_scalar($value) ? (string) $value : json_encode($value),
                    ];
                }
            }

            // Recursively check child components
            if (method_exists($component, 'getChildComponents')) {
                $this->extractEntries($component->getChildComponents(), $record, $details);
            }
        }
    }

    private function resolveEntryValue(Entry $component, Model $record, string $name)
    {
        // Many filament components use getState()
        try {
            $state = $component->getState();
            if ($state !== null) {
                if (is_array($state)) {
                    return implode(', ', $state);
                }
                return $state;
            }
        } catch (\Exception $e) {
            // getState might fail if not fully booted or inside certain contexts
        }

        // Fallback: resolve from model properties or relationships
        if (str_contains($name, '.')) {
            // Nested relationship (e.g., status.name)
            return data_get($record, $name);
        }

        return $record->{$name};
    }

    private function extractRelationships(Model $record, array &$details): void
    {
        $relations = $record->getRelations();

        foreach ($relations as $relationName => $relatedModels) {
            if (is_null($relatedModels)) {
                continue;
            }

            $label = __('dashboard/strings.related') . ' ' . Str::headline($relationName);
            $value = '';

            if ($relatedModels instanceof \Illuminate\Database\Eloquent\Collection) {
                $count = $relatedModels->count();
                if ($count > 0) {
                     // Try to get a meaningful identifier
                     $identifiers = $relatedModels->take(3)->map(function ($model) {
                         return $model->name ?? $model->title ?? $model->id ?? 'Record';
                     })->implode(', ');

                     $value = "{$count} records (" . $identifiers . ($count > 3 ? ', ...' : '') . ")";
                }
            } elseif ($relatedModels instanceof Model) {
                 $value = $relatedModels->name ?? $relatedModels->title ?? $relatedModels->id ?? 'Record';
            }

            if (!empty($value)) {
                $details[] = [
                    'label' => $label,
                    'value' => $value,
                ];
            }
        }
    }
}
