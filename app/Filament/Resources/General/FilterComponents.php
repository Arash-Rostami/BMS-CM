<?php

namespace App\Filament\Resources\General;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class FilterComponents
{
    public static function dateRangeFilter(
        string $name,
        string $column,
        string $fromField,
        string $untilField,
        string $fromLabel,
        string $untilLabel,
    ): Filter {
        return Filter::make($name)
            ->schema([
                DatePicker::make($fromField)
                    ->label($fromLabel)
                    ->native(false)
                    ->adaptive(),
                DatePicker::make($untilField)
                    ->label($untilLabel)
                    ->native(false)
                    ->adaptive(),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data[$fromField] ?? null, fn (Builder $query, $date): Builder => $query->whereDate($column, '>=', $date))
                ->when($data[$untilField] ?? null, fn (Builder $query, $date): Builder => $query->whereDate($column, '<=', $date)));
    }
}
