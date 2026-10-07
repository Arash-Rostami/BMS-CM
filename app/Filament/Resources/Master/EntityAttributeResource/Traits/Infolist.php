<?php

namespace App\Filament\Resources\Master\EntityAttributeResource\Traits;

use App\Models\EntityAttribute;
use App\Services\PermissionLabeler;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Str;

trait Infolist
{
    public static function viewEntityType(): TextEntry
    {
        return TextEntry::make('entity_type')
            ->label(__('resources/entityAttribute/strings.infolist.entity_type'))
            ->formatStateUsing(fn ($state): string => PermissionLabeler::getEntityLabel($state))
            ->badge()
            ->icon('heroicon-m-cube');
    }

    public static function viewEntityId(): TextEntry
    {
        return TextEntry::make('entity_id')
            ->label(__('resources/entityAttribute/strings.infolist.entity_id'))
            ->formatStateUsing(fn ($state, EntityAttribute $record): string => static::ownerLabel($record))
            ->url(fn (EntityAttribute $record): ?string => static::ownerUrl($record))
            ->badge()
            ->color('gray')
            ->icon('heroicon-m-hashtag');
    }

    public static function viewKey(): TextEntry
    {
        return TextEntry::make('key')
            ->label(__('resources/entityAttribute/strings.infolist.key'))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-key')
            ->copyable();
    }

    public static function viewValue(): TextEntry
    {
        return TextEntry::make('value')
            ->label(__('resources/entityAttribute/strings.infolist.value'))
            ->formatStateUsing(fn ($state): string => is_array($state)
                ? (static::renderEavValue($state) ?: '-')
                : e((string) $state))
            ->html()
            ->icon('heroicon-m-document-text')
            ->placeholder('-')
            ->columnSpanFull();
    }

    protected static function renderEavValue(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        if (! is_array($value)) {
            return '<span class="text-sm">'.e((string) $value).'</span>';
        }

        return array_is_list($value)
            ? static::renderEavList($value)
            : static::renderEavRows($value);
    }

    protected static function renderEavRows(array $rows): string
    {
        $html = '';

        foreach ($rows as $key => $item) {
            $rendered = static::renderEavValue($item);

            if ($rendered === '') {
                continue;
            }

            $label = '<span dir="ltr" class="text-sm text-gray-500 dark:text-gray-400 min-w-36">'.e(Str::headline((string) $key)).'</span>';

            $html .= is_array($item)
                ? '<div class="space-y-1"><div dir="ltr" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">'.e(Str::headline((string) $key)).'</div><div class="ps-3 border-s border-gray-100 dark:border-white/5 space-y-1">'.$rendered.'</div></div>'
                : '<div class="flex items-baseline gap-2">'.$label.$rendered.'</div>';
        }

        return $html === '' ? '' : '<div class="space-y-1.5">'.$html.'</div>';
    }

    protected static function renderEavList(array $items): string
    {
        $cards = '';

        foreach ($items as $item) {
            $rendered = static::renderEavValue($item);

            if ($rendered === '') {
                continue;
            }

            $cards .= '<div class="rounded-lg border border-gray-100 dark:border-white/5 p-3">'.$rendered.'</div>';
        }

        return $cards === '' ? '' : '<div class="space-y-2">'.$cards.'</div>';
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/entityAttribute/strings.infolist.created_by'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/entityAttribute/strings.infolist.updated_by'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/entityAttribute/strings.infolist.created_at'))
            ->adaptiveDateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/entityAttribute/strings.infolist.updated_at'))
            ->adaptiveDateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }
}
