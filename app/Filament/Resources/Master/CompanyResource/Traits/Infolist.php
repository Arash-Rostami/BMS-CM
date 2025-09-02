<?php

namespace App\Filament\Resources\Master\CompanyResource\Traits;


use App\Filament\Resources\Master\CompanyResource\Enums\Type;
use Filament\Infolists\Components;
use Filament\Infolists\Components\TextEntry;

trait Infolist
{
    public static function viewCompanyTypes(): TextEntry
    {
        return TextEntry::make('formatted_types')
            ->label(__('resources/company/strings.table.company_types'))
            ->badge()
            ->separator(', ')
            ->color(fn(?string $state): ?string => Type::tryFromLocalised($state)?->getColor() ?? 'info')
            ->icon(fn(?string $state): ?string => Type::tryFromLocalised($state)?->getIcon() ?? 'heroicon-o-question-mark-circle')
            ->placeholder('❌');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return Components\TextEntry::make('created_at')
            ->label(__('resources/company/strings.table.created_at'))
            ->dateTime('M Y | D: H:i:s');
    }

    public static function viewCreator(): TextEntry
    {
        return Components\TextEntry::make('creator.name')
            ->label(__('resources/company/strings.form.creator'));
    }

    public static function viewDescription(): TextEntry
    {
        return Components\TextEntry::make('description')
            ->label(__('resources/company/strings.form.description'));
    }

    public static function viewEnglishName(): TextEntry
    {
        return Components\TextEntry::make('english_name')
            ->label(__('resources/company/strings.form.english_name'));
    }

    public static function viewIsActive(): TextEntry
    {
        return Components\TextEntry::make('is_active')
            ->label(__('resources/company/strings.form.is_active'))
            ->formatStateUsing(fn(bool $state): string => $state ? '✅' : '❌')
            ->color(fn(bool $state): string => $state ? 'success' : 'danger');
    }

    public static function viewName(): TextEntry
    {
        return Components\TextEntry::make('name')
            ->label(__('resources/company/strings.form.name'));
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return Components\TextEntry::make('updated_at')
            ->label(__('resources/company/strings.table.updated_at'))
            ->dateTime('M Y | D: H:i:s');
    }

    public static function viewUpdater(): TextEntry
    {
        return Components\TextEntry::make('updater.name')
            ->label(__('resources/company/strings.form.updater'));
    }
}
