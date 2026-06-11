<?php

namespace App\Services;

use Filament\Infolists\Components\Component;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SearchExtractorService
{
    public function extractDetails(string $resourceClass, Model $record): array
    {
        $details = [];

        if (! class_exists($resourceClass)) {
            return $details;
        }

        try {
            $infolist = Infolist::make()->record($record);
            if (method_exists($resourceClass, 'infolist')) {
                $infolist = $resourceClass::infolist($infolist);
            }
            $components = $infolist->getComponents();
            $this->extractFromComponents($components, $record, $details);
        } catch (\Throwable $e) {
            // fallback
        }

        return array_values(array_filter($details, fn($d) => !empty($d['value'])));
    }

    private function extractFromComponents(array $components, Model $record, array &$details): void
    {
        foreach ($components as $component) {

            // Recurse into containers (e.g. Sections, Grid, Tabs)
            if (method_exists($component, 'getChildComponents')) {
                $this->extractFromComponents($component->getChildComponents(), $record, $details);
            }

            if ($component instanceof Entry) {
                // Ensure the component has the record context
                $component->record($record);

                // Skip if hidden
                if ($component->isHidden()) {
                    continue;
                }

                $label = $component->getLabel() ?? ucwords(str_replace(['_', '.'], ' ', $component->getName()));
                $name = $component->getName();

                // For TextEntry we try to get the formatted state
                if ($component instanceof TextEntry) {
                    try {
                        $state = $component->getState();

                        // Special case handling for custom components like InfoComponents::viewPurchaseOrders()
                        // These usually rely on relations
                        if (is_null($state) && str_contains($name, '.')) {
                             // The state is already evaluated, maybe listWithLineBreaks is doing magic
                        }

                        if ($state instanceof Collection) {
                            if ($state->isEmpty()) continue;

                            // Try to format relation collection
                            $values = $state->map(function ($item) {
                                if (is_string($item)) return $item;
                                if (is_object($item)) {
                                    return $item->formatted_name ?? $item->name ?? $item->title ?? $item->localized_name ?? '#' . $item->id;
                                }
                                return (string) $item;
                            })->filter()->implode(', ');

                            if (!empty($values)) {
                                $details[$name] = [
                                    'label' => $label,
                                    'value' => $values,
                                ];
                            }
                        } else if (is_array($state)) {
                            if (empty($state)) continue;
                            $details[$name] = [
                                'label' => $label,
                                'value' => implode(', ', $state),
                            ];
                        } else if (! is_null($state) && $state !== '') {
                            // remove HTML tags for clean search result view
                            $cleanValue = strip_tags(html_entity_decode((string) $state));
                            $cleanValue = trim(preg_replace('/\s+/', ' ', $cleanValue));
                            if (!empty($cleanValue)) {
                                $details[$name] = [
                                    'label' => $label,
                                    'value' => $cleanValue,
                                ];
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore evaluation errors
                    }
                }

                // For RepeatableEntry (usually HasMany/MorphMany relations)
                if ($component instanceof RepeatableEntry) {
                    try {
                        $state = $component->getState();
                        $count = 0;
                        if ($state instanceof Collection) {
                            $count = $state->count();
                        } else if (is_array($state)) {
                            $count = count($state);
                        } else if (method_exists($record, $name)) {
                            $count = $record->{$name}()->count();
                        }

                        if ($count > 0) {
                            $details[$name] = [
                                'label' => $label,
                                'value' => "{$count} related record(s)",
                            ];
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }
        }
    }
}
