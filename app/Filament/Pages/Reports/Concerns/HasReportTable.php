<?php

namespace App\Filament\Pages\Reports\Concerns;

use App\Filament\Resources\General\TableComponents;
use App\Jobs\ExportReport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

trait HasReportTable
{
    protected function reportTable(Table $table): Table
    {
        return TableComponents::emptyState($table
            ->query(fn (): Builder => app(static::service())->query($this->filterObject))
            ->filters([$this->quickFilter()])
            ->filtersLayout(FiltersLayout::Hidden)
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped());
    }

    protected function quickFilter(): Filter
    {
        return Filter::make('quick')->indicateUsing(fn (): ?array => $this->quick === null ? null : [
            Indicator::make(__('resources/report/strings.quick.active', ['label' => $this->quickLabel])),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [$this->resetFiltersAction(), $this->exportCsvAction()];
    }

    protected function resetFiltersAction(): Action
    {
        return Action::make('resetFilters')
            ->label(fn (): string => __('resources/report/strings.filters.reset'))
            ->icon('heroicon-o-x-mark')
            ->color('gray')
            ->visible(fn (): bool => $this->hasActiveFilters() || $this->quick !== null)
            ->action(fn () => $this->resetReportFilters());
    }

    protected function exportCsvAction(): Action
    {
        return Action::make('exportCsv')
            ->label(fn (): string => __('filament-actions::export.label', ['label' => $this->getTitle()]))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => static::canAccess())
            ->action(function (): void {
                ExportReport::dispatch(static::key(), $this->filterObject->toArray(), auth()->id(), app()->getLocale(), isJalaliCalendar() ? 'jalali' : 'gregorian');

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            });
    }
}
