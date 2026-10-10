<?php

namespace App\Filament\Traits;

use App\Services\NameSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

trait HasGlobalSearchConvention
{
    protected static function globalSearchRelations(): array
    {
        return [];
    }

    protected static function restrictGlobalSearch(Builder $query): Builder
    {
        return $query;
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        $query = static::getEloquentQuery()->setEagerLoads([])->with(static::globalSearchRelations());

        return static::restrictGlobalSearch(
            $query->when(
                in_array(SoftDeletes::class, class_uses_recursive(static::getModel())),
                fn (Builder $q): Builder => $q->whereNull($q->getModel()->getQualifiedDeletedAtColumn())
            )
        );
    }

    public static function getGloballySearchableAttributes(): array
    {
        return NameSearch::columns(app(static::getModel()));
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::hasPage('edit')
            ? parent::getGlobalSearchResultUrl($record)
            : static::getUrl('index', ['search' => $record->english_name ?? $record->name ?? '']);
    }
}
