<?php

namespace App\Filament\Resources\Master\CompanyResource\Traits;


use App\Models\Company;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;

trait Filters
{
    public static function getActiveFilter(): TernaryFilter
    {
        return TernaryFilter::make('is_active')
            ->label(__('resources/company/strings.table.is_active'))
            ->trueLabel(__('resources/company/strings.table.only_active'))
            ->falseLabel(__('resources/company/strings.table.only_inactive'));
    }

    public static function getCompanyTypeFilter(): Filter
    {
        return Filter::make('company_types')
            ->label(__('resources/company/strings.filters.company_types'))
            ->form([
                Select::make('types')
                    ->label(__('resources/company/strings.filters.select_types'))
                    ->options(Company::getAvailableTypes())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->placeholder(__('resources/company/strings.filters.all_types'))
            ])
            ->query(function (Builder $query, array $data): Builder {
                if (empty($data['types'])) return $query;

                return $query->where(function (Builder $query) use ($data) {
                    foreach ($data['types'] as $type) {
                        $query->orWhereJsonContains('types', $type);
                    }
                });
            })
            ->indicateUsing(function (array $data): array {
                if (empty($data['types'])) return [];

                $availableTypes = Company::getAvailableTypes();
                $selectedTypes = collect($data['types'])
                    ->map(fn($type) => $availableTypes[$type] ?? $type)
                    ->join(', ');

                return [
                    __('resources/company/strings.filters.types_indicator', ['types' => $selectedTypes])
                ];
            });
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/company/strings.table.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getThrashedFilter()
    {
        return TrashedFilter::make();
    }

    public static function getUpdaterFilter(): SelectFilter
    {
        return SelectFilter::make('updated_by_id')
            ->label(__('resources/company/strings.table.updater'))
            ->relationship('updater', 'name')
            ->searchable()
            ->preload();
    }
}
