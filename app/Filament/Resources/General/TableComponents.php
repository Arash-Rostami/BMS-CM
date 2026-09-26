<?php

namespace App\Filament\Resources\General;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TableComponents
{
    public static function emptyState(Table $table): Table
    {
        return $table
            ->emptyStateIcon(fn (Table $table) => filled($table->getFilterIndicators())
                ? 'heroicon-o-magnifying-glass'
                : 'heroicon-o-inbox')
            ->emptyStateHeading(fn (Table $table) => filled($table->getFilterIndicators())
                ? __('resources/general/strings.empty_state.filtered_heading')
                : __('resources/general/strings.empty_state.heading'))
            ->emptyStateDescription(fn (Table $table) => filled($table->getFilterIndicators())
                ? __('resources/general/strings.empty_state.filtered_description')
                : __('resources/general/strings.empty_state.description'))
            ->emptyStateActions([
                Action::make('resetFilteredEmptyState')
                    ->label(__('resources/general/strings.empty_state.reset_filters'))
                    ->link()
                    ->visible(fn (Table $table) => filled($table->getFilterIndicators()))
                    ->action(fn ($livewire) => $livewire->removeTableFilters()),
            ]);
    }

    public static function gatedEmptyState(Table $table): Table
    {
        return $table
            ->emptyStateIcon('heroicon-o-lock-closed')
            ->emptyStateHeading(__('resources/general/strings.empty_state.gated_heading'))
            ->emptyStateDescription(__('resources/general/strings.empty_state.gated_description'));
    }

    public static function showProformaInvoices(): TextColumn
    {
        return TextColumn::make('proformaInvoices')
            ->label(__('resources/general/strings.relevant_module.table.proforma_invoices'))
            ->html()
            ->badge()
            ->default('-')
            ->wrap()
            ->formatStateUsing(fn ($state) => $state?->formatted_name_without_date ?? '-')
            ->searchable(
                query: fn (Builder $query, string $search) => $query->whereHas(
                    'proformaInvoices',
                    fn (Builder $q) => $q->searchAll($search)
                ), isIndividual: true)
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPurchaseOrders(): TextColumn
    {
        return TextColumn::make('purchaseOrders')
            ->label(__('resources/general/strings.relevant_module.table.purchase_orders'))
            ->html()
            ->badge()
            ->default('-')
            ->wrap()
            ->formatStateUsing(fn ($state) => $state?->formatted_name_without_date ?? '-')
//            ->searchable(query: fn($query, $search) => $query->whereHas('', fn($sq) => $sq->searchAll($search)), isIndividual: true)
            ->searchable(
                query: fn (Builder $query, string $search) => $query->whereHas(
                    'purchaseOrders',
                    fn (Builder $q) => $q->searchAll($search)
                ), isIndividual: true)
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPurchaseRequests(): TextColumn
    {
        return TextColumn::make('purchaseRequests')
            ->label(__('resources/general/strings.relevant_module.table.purchase_requests'))
            ->html()
            ->badge()
            ->default('-')
            ->wrap()
            ->formatStateUsing(fn ($state) => $state?->formatted_name_without_date ?? '-')
            ->searchable(
                query: fn (Builder $query, string $search) => $query->whereHas(
                    'purchaseRequests',
                    fn (Builder $q) => $q->searchAll($search)
                ), isIndividual: true)
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showRegisteredOrders(): TextColumn
    {
        return TextColumn::make('registeredOrders')
            ->label(__('resources/general/strings.relevant_module.table.registered_orders'))
            ->html()
            ->badge()
            ->default('-')
            ->wrap()
            ->formatStateUsing(fn ($state) => $state?->formatted_name_without_date ?? '-')
            ->searchable(
                query: fn (Builder $query, string $search) => $query->whereHas(
                    'registeredOrders',
                    fn (Builder $q) => $q->searchAll($search)
                ), isIndividual: true)
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
