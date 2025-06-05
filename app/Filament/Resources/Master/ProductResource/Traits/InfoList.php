<?php


namespace App\Filament\Resources\Master\ProductResource\Traits;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\KeyValueEntry;
use App\Filament\Resources\Master\ProductResource\Enums\InStockStatus;


trait Infolist
{
    public static function viewName(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/product/strings.form.name'));
    }

    public static function viewEnglishName(): TextEntry
    {
        return TextEntry::make('english_name')
            ->label(__('resources/product/strings.form.english_name'));
    }

    public static function viewSlug(): TextEntry
    {
        return TextEntry::make('slug')
            ->label(__('resources/product/strings.form.slug'));
    }

    public static function viewCode(): TextEntry
    {
        return TextEntry::make('code')
            ->label(__('resources/product/strings.form.code'));
    }

    public static function viewDescription(): TextEntry
    {
        return TextEntry::make('description')
            ->label(__('resources/product/strings.form.description'));
    }

    public static function viewCategory(): TextEntry
    {
        return TextEntry::make('category.name')
            ->label(__('resources/product/strings.form.category'))
            ->formatStateUsing(fn($record, $state) => app()->getLocale() != 'fa' ? optional($record->category)->english_name : $state);
    }

    public static function viewInStock(): TextEntry
    {
        return TextEntry::make('in_stock')
            ->label(__('resources/product/strings.form.in_stock'))
            ->formatStateUsing(fn(bool $state): string => InStockStatus::tryFrom((int)$state)?->getLabel() ?? (string)$state)
            ->color(fn(bool $state): string => InStockStatus::tryFrom((int)$state)?->getColor() ?? 'gray');
    }

    public static function viewAttributesJson(): KeyValueEntry
    {
        return KeyValueEntry::make('attributes')
            ->label(__('resources/product/strings.table.attributes'))
            ->getStateUsing(function ($record): array {
                static $cached = null;

                if ($cached === null) {
                    $cached = __('resources/product/strings.attributes_manager');
                }

                $typed = $record->typedAttributes();
                $out = [];

                foreach ($typed as $k => $v) {
                    $lk = strtolower($k);
                    $lbl = $cached[$lk] ?? $k;
                    $out[$lbl] = $v;
                }

                return $out;
            })
            ->valueLabel('')
            ->keyLabel(__('resources/product/strings.table.attributes'))
            ->hiddenLabel()
            ->columnSpanFull();
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/product/strings.form.creator'));
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/product/strings.form.updater'));
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/product/strings.table.created_at'))
            ->dateTime('M Y | D: H:i:s');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/product/strings.table.updated_at'))
            ->dateTime('M Y | D: H:i:s');
    }
}
