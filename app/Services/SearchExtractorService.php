<?php

namespace App\Services;

use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Component;
use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

/**
 * A dummy livewire component to satisfy Filament's requirement.
 */
class DummyLivewire extends LivewireComponent implements \Filament\Infolists\Contracts\HasInfolists
{
    use \Filament\Infolists\Concerns\InteractsWithInfolists;

    public function render()
    {
        return '<div></div>';
    }
}

class SearchExtractorService
{
    /**
     * Recursively extract configured fields from the Resource's infolist() method.
     *
     * @param string $resourceClass The fully qualified class name of the Filament Resource.
     * @param Model $record The eloquent model instance.
     * @return array
     */
    public function extractDetails(string $resourceClass, Model $record): array
    {
        if (!class_exists($resourceClass) || !method_exists($resourceClass, 'infolist')) {
            return [];
        }

        if (!class_exists(Infolist::class)) {
             // Fallback for Filament v3 where it might be Schema instead
             return $this->extractSchemaDetails($resourceClass, $record);
        }

        // Filament v3 uses Infolist with a Livewire requirement.
        $dummyLivewire = new DummyLivewire();

        $infolist = Infolist::make($dummyLivewire)->record($record);

        try {
            $infolist = $resourceClass::infolist($infolist);
        } catch (\Throwable $e) {
            return [];
        }

        $components = $infolist->getComponents();

        $extractedDetails = [];
        $this->extractEntries($components, $extractedDetails, $record);

        return $extractedDetails;
    }

    private function extractSchemaDetails(string $resourceClass, Model $record): array
    {
         if (!class_exists(\Filament\Schemas\Schema::class)) {
             return [];
         }

         $dummyLivewire = new class extends LivewireComponent implements \Filament\Schemas\Contracts\HasSchemas {
              use \Filament\Schemas\Concerns\InteractsWithSchemas;
         };

         $schema = \Filament\Schemas\Schema::make($dummyLivewire);

         try {
             $schema = $resourceClass::infolist($schema);
         } catch (\Throwable $e) {
             return [];
         }

         $components = $schema->getComponents();
         $extractedDetails = [];
         $this->extractEntries($components, $extractedDetails, $record);
         return $extractedDetails;
    }

    /**
     * Recursively extract displayable entries from the schema components.
     */
    private function extractEntries(array $components, array &$extractedDetails, Model $record): void
    {
        foreach ($components as $component) {
            try {
                if (method_exists($component, 'getChildComponentContainer')) {
                    $childComponents = $component->getChildComponentContainer()->getComponents();
                    if (!empty($childComponents)) {
                         $this->extractEntries($childComponents, $extractedDetails, $record);
                    }
                } elseif (method_exists($component, 'getChildComponents')) {
                    $childComponents = $component->getChildComponents();
                    if (!empty($childComponents)) {
                         $this->extractEntries($childComponents, $extractedDetails, $record);
                    }
                }
            } catch (\Throwable $e) {
                // Ignore any components that fail to resolve children
            }

            // We only care about components that have names and can be entries.
            if (!method_exists($component, 'getName')) {
                continue;
            }

            $name = $component->getName();
            if (!$name) {
                continue;
            }

            // Try to resolve the state if it has a getState method (like an Entry)
            $state = null;
            if (method_exists($component, 'getState')) {
                try {
                    $state = $component->getState();
                } catch (\Throwable $e) {
                    // Fallback to manual resolution if getState fails (e.g., due to closure requiring livewire context)
                }
            }

            if ($state === null) {
                // Fallback manual resolution
                try {
                    if (str_contains($name, '.')) {
                        $parts = explode('.', $name);
                        $current = $record;
                        foreach ($parts as $part) {
                            if (is_object($current)) {
                                $current = $current->{$part};
                            } else {
                                $current = null;
                                break;
                            }
                        }
                        $state = $current;
                    } else {
                        $state = $record->{$name};
                        if ($state instanceof Model) {
                            $state = $state->localized_name ?? $state->name ?? $state->title ?? null;
                        }
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            if ($state instanceof \BackedEnum) {
                 $state = $state->value;
            } else if ($state instanceof \UnitEnum) {
                 $state = $state->name;
            } else if ($state instanceof \Carbon\CarbonInterface) {
                 $state = $state->format('Y-m-d');
            }

            if ($state === null || $state === '' || is_array($state) || is_object($state)) {
                continue;
            }

            $label = null;
            if (method_exists($component, 'getLabel')) {
                try {
                    $label = $component->getLabel();
                } catch (\Throwable $e) {
                    // Ignore
                }
            }

            if (!$label) {
                $label = str_replace('_', ' ', Str::title($name));
            }

            $exists = false;
            foreach($extractedDetails as $detail) {
                 if ($detail['label'] === $label) {
                     $exists = true;
                     break;
                 }
            }

            if (!$exists) {
                $extractedDetails[] = [
                    'label' => $label,
                    'value' => (string) $state,
                ];
            }
        }
    }
}
