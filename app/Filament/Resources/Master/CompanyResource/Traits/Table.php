<?php

namespace App\Filament\Resources\Master\CompanyResource\Traits;


use App\Filament\Resources\Master\CompanyResource\Enums\Type;
use App\Models\Company;
use Filament\Forms\Components\CheckboxList;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;

trait Table
{
    public static function showName(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('resources/company/strings.table.name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() != 'fa');

    }

    public static function showEnglishName(): TextColumn
    {
        return TextColumn::make('english_name')
            ->label(__('resources/company/strings.table.english_name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() == 'fa');
    }

    public static function showDescription(): TextColumn
    {
        return TextColumn::make('description')
            ->label(__('resources/company/strings.table.description'))
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false)
            ->limit(50);

    }

    public static function showCompanyTypes(): TextColumn
    {

        return TextColumn::make('formatted_types')
            ->label(__('resources/company/strings.table.company_types'))
            ->badge()
            ->separator(', ')
            ->color(fn(?string $state): ?string => Type::tryFromLocalised($state)?->getColor() ?? 'info')
            ->icon(fn(?string $state): ?string => Type::tryFromLocalised($state)?->getIcon() ?? 'heroicon-o-question-mark-circle')
            ->searchable(query: fn ($query, $search) => $query->whereJsonContains('types', strtolower($search)))
            ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("JSON_LENGTH(types) {$direction}"))
            ->toggleable(isToggledHiddenByDefault: true);
    }


    public static function showIsActive(): ToggleColumn
    {
        return ToggleColumn::make('is_active')
            ->label(__('resources/company/strings.table.is_active'))
            ->onIcon('heroicon-o-check-circle')
            ->offIcon('heroicon-o-x-circle')
            ->onColor('success')
            ->offColor('danger')
            ->toggleable()
            ->sortable();
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/company/strings.table.creator'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/company/strings.table.updater'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/company/strings.table.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/company/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
