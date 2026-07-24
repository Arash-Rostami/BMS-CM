<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Traits;

use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Priority;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Type;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;

trait Filters
{
    public static function getCreationDateFilter(): Filter
    {
        return Filter::make('created_at')
            ->schema([
                DatePicker::make('created_from')
                    ->label(__('resources/correspondence/strings.filters.created_from'))
                    ->native(false)
                    ->jalali(app()->isLocale('fa')),
                DatePicker::make('created_until')
                    ->label(__('resources/correspondence/strings.filters.created_until'))
                    ->native(false)
                    ->jalali(app()->isLocale('fa')),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when($data['created_from'], fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                    ->when($data['created_until'], fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date));
            });
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/correspondence/strings.filters.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getMyMentionsFilter(): Filter
    {
        return Filter::make('my_mentions')
            ->label(__('resources/correspondence/strings.filters.my_mentions'))
            ->query(fn (Builder $query) => $query->whereHas('recipients', fn ($q) => $q->where('user_id', auth()->id())))
            ->toggle();
    }

    public static function getUnreadFilter(): Filter
    {
        return Filter::make('unread')
            ->label(__('resources/correspondence/strings.filters.unread'))
            ->query(fn (Builder $query) => $query->whereHas(
                'recipients',
                fn (Builder $q) => $q->where('user_id', auth()->id())->whereNull('read_at')
            ))
            ->toggle();
    }

    public static function getPriorityFilter(): SelectFilter
    {
        return SelectFilter::make('priority')
            ->label(__('resources/correspondence/strings.filters.priority'))
            ->options(Priority::class)
            ->multiple();
    }

    public static function getStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status_id')
            ->label(__('resources/correspondence/strings.filters.status'))
            ->relationship('status', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->preload()
            ->searchable();
    }

    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }

    public static function getTypeFilter(): SelectFilter
    {
        return SelectFilter::make('type')
            ->label(__('resources/correspondence/strings.filters.type'))
            ->options(Type::class)
            ->multiple();
    }
}
