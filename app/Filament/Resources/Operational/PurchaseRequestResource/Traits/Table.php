<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Traits;

use App\Filament\Resources\Operational\PurchaseRequestResource\Enums\Status;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

trait Table
{

    public static function showID(): TextColumn
    {
        return TextColumn::make('id')
            ->label(__('resources/purchaseRequest/strings.table.id'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true)
            ->searchable();
    }

    public static function showRequester(): TextColumn
    {
        return TextColumn::make('requester.name')
            ->label(__('resources/purchaseRequest/strings.table.requester'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true)
            ->searchable();
    }

    public static function showDepartment(): TextColumn
    {
        return TextColumn::make('department.localized_name')
            ->label(__('resources/purchaseRequest/strings.table.department'))
            ->tooltip(fn($record) => $record?->requester->name)
            ->sortable()
            ->searchable(
                query: fn(Builder $query, string $search) => $query->orWhereHas('department', fn($q) => $q->searchDepartment($search))
            )
            ->toggleable();
    }

    public static function showCostCenter(): TextColumn
    {
        return TextColumn::make('costCenter.localized_name')
            ->label(__('resources/purchaseRequest/strings.form.cost_center'))
            ->tooltip(fn($record) => $record?->requester->name)
            ->sortable()
            ->searchable(
                query: fn(Builder $query, string $search) => $query->orWhereHas('costCenter', fn($q) => $q->searchDepartment($search))
            )
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showStatus(): TextColumn
    {
        return TextColumn::make('status.english_name')
            ->label(__('resources/purchaseRequest/strings.table.status'))
            ->badge()
            ->toggleable()
            ->searchable(
                query: fn(Builder $query, string $search) => $query->orWhereHas('status', fn($q) => $q->searchStatus($search))
            )
            ->iconPosition(IconPosition::Before)
            ->formatStateUsing(fn(?string $state): ?string => Status::tryFrom($state)?->getLabel() ?? $state)
            ->color(fn(?string $state): ?string => Status::tryFrom($state)?->getColor() ?? 'info')
            ->icon(fn(?string $state): ?string => Status::tryFrom($state)?->getIcon() ?? 'heroicon-o-question-mark-circle')
            ->sortable();
    }

    public static function showProformaInvoiceCount()
    {
        return TextColumn::make('proforma_invoices_count')
            ->label(__('resources/purchaseRequest/strings.table.pi_count'))
            ->icon('heroicon-o-document-text')
            ->alignEnd()
            ->badge()
            ->color(fn(int $state): string => match ($state) {
                0 => 'gray',
                default => 'success',
            })
            ->toggleable()
            ->sortable();
    }

    public static function showTotalCost(): TextColumn
    {
        return TextColumn::make('total_estimated_cost')
            ->label(__('resources/purchaseRequest/strings.table.total_estimated_cost'))
            ->toggleable()
            ->sortable()
            ->formatStateUsing(fn(?float $state) => $state === null
                ? '-'
                : number_format($state, 2)
            );
    }

    public static function showRequiredByDate(): TextColumn
    {
        return TextColumn::make('required_by_date')
            ->label(__('resources/purchaseRequest/strings.table.required_by_date'))
            ->date()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUrgency(): TextColumn
    {
        return TextColumn::make('urgency_level')
            ->label(__('resources/purchaseRequest/strings.table.urgency_level'))
            ->badge()
            ->toggleable(isToggledHiddenByDefault: true)
            ->formatStateUsing(fn(?string $state): ?string => __("resources/purchaseRequest/strings.general.urgency.{$state}") ?? $state)
            ->color(fn(?string $state): string => match ($state) {
                'high' => 'danger',
                'medium' => 'warning',
                'low' => 'success',
                default => 'gray'
            })
            ->sortable();
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/purchaseRequest/strings.table.creator'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/purchaseRequest/strings.table.updater'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/purchaseRequest/strings.table.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/purchaseRequest/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
