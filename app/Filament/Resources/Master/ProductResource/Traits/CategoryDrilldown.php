<?php

namespace App\Filament\Resources\Master\ProductResource\Traits;

use App\Filament\Resources\Master\CategoryResource\Enums\Level;
use App\Models\Category;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;

trait CategoryDrilldown
{
    public static function getAllFields(): array
    {
        $options  = app(Category::class)->getCachedHierarchy();
        $maxLevel = static::maxDepth($options);

        return array_merge(
            static::buildHiddenFields($maxLevel),
            static::buildSelectFields($options, $maxLevel),
        );
    }

    protected static function maxDepth(array $map, int $parent = 0): int
    {
        if (empty($map[$parent])) {
            return 0;
        }

        $deepest = 0;
        foreach (array_keys($map[$parent]) as $childId) {
            $depthViaChild = 1 + static::maxDepth($map, $childId);
            $deepest       = max($deepest, $depthViaChild);
        }

        return $deepest;
    }


    protected static function buildHiddenFields(int $maxLevel): array
    {
        return [
            Hidden::make('chain_complete')
                ->default(false)
                ->dehydrated(false)
                ->reactive(),

            Hidden::make('category_path')
                ->dehydrated(false)
                ->reactive()
                ->afterStateHydrated(function ($component, $state, $set) use ($maxLevel) {
                    $record = $component->getRecord();
                    if (!$record?->category_id) {
                        return;
                    }

                    $category = Category::find($record->category_id);
                    if (!$category) {
                        return;
                    }

                    // Build the path of ancestor IDs + current
                    $path = array_merge(
                        $category->sortedAncestors()->pluck('id')->toArray(),
                        [$category->id]
                    );

                    // Store path and prefill selects up to maxLevel
                    $set('category_path', $path);
                    foreach ($path as $level => $id) {
                        if ($level < $maxLevel) {
                            $set("categories.{$level}", $id);
                        }
                    }
                    $set('chain_complete', true);
                }),
        ];
    }

    protected static function buildSelectFields(array $options, int $maxLevel): array
    {
        $fields = [];

        for ($level = 0; $level < $maxLevel; $level++) {
            $fields[] = static::buildSelectField($level, $options, $maxLevel);
        }

        return $fields;
    }

    protected static function buildSelectField(int $level, array $options, int $maxLevel): Select
    {
        return Select::make("categories.{$level}")
            ->label(Level::fromLevel($level)->getLabel())
            ->options(fn($get) => // If level 0, show top‑level (parent_id = 0), else children of selected parent
                $options[$get("categories." . ($level - 1)) ?? 0] ?? []
            )
            ->visible(fn($get) =>
                // Always show level 0; for others only if parent has children
                $level === 0
                || ! empty($options[$get("categories." . ($level - 1))] ?? [])
            )
            ->reactive()
            ->afterStateUpdated(function ($state, $set) use ($options, $level, $maxLevel) {
                // Clear any deeper selections when this level changes
                for ($i = $level + 1; $i < $maxLevel; $i++) {
                    $set("categories.{$i}", null);
                }
                // If no further children exist, mark the chain complete
                if (empty($options[$state] ?? [])) {
                    $set('chain_complete', true);
                }
            });
    }
}
