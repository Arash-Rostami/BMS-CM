<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use App\Filament\Actions\GroupedImportAction;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Enums\Source;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Imports\ProformaInvoiceImporter;
use App\Filament\Resources\ProformaInvoiceResource;
use App\Jobs\ExportProformaInvoices;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait Table
{
    public static function getImportAction(): GroupedImportAction
    {
        return GroupedImportAction::make('importProformaInvoices')
            ->label(__('resources/proformaInvoice/strings.import.import_invoices'))
            ->modalHeading(__('resources/proformaInvoice/strings.import.import_invoices'))
            ->icon('heroicon-o-arrow-up-tray')
            ->importer(ProformaInvoiceImporter::class)
            ->resourceGate(ProformaInvoiceResource::class)
            ->itemDiscriminatorColumn('product_id')
            ->itemOnlyColumns(['origin', 'hs_code', 'unit', 'quantity', 'unit_price', 'net_weight', 'gross_weight', 'item_freight_charges', 'item_total_amount', 'description']);
    }

    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportProformaInvoices')
            ->label(__('resources/proformaInvoice/strings.export.export_invoices'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => ProformaInvoiceResource::canViewAny())
            ->action(function (Collection $records): void {
                ExportProformaInvoices::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function isQuoteStale($record): bool
    {
        return $record?->validity_date
            && $record->validity_date->isPast()
            && (int) ($record->registered_orders_count ?? 0) === 0;
    }

    protected static function budgetVariancePercent($record): ?float
    {
        $purchaseRequests = $record?->purchaseRequests;

        if (blank($purchaseRequests) || $purchaseRequests->isEmpty()) {
            return null;
        }

        $budget = (float) $purchaseRequests->sum('total_estimated_cost');

        if ($budget <= 0) {
            return null;
        }

        return ((float) $record->total_amount - $budget) / $budget * 100;
    }

    protected static function budgetVarianceColor(?float $percent): string
    {
        // Thresholds are a business call, not derived from any config: small overrun (0-20%)
        // is a soft warning, anything beyond 20% is flagged as a hard overrun.
        return match (true) {
            $percent === null => 'gray',
            $percent > 20 => 'danger',
            $percent > 0 => 'warning',
            default => 'success',
        };
    }

    public static function showBudgetVariance(): TextColumn
    {
        return TextColumn::make('budget_variance')
            ->label(__('resources/proformaInvoice/strings.table.budget_variance'))
            ->getStateUsing(fn ($record) => static::budgetVariancePercent($record))
            ->formatStateUsing(fn (?float $state) => $state === null ? '-' : number_format($state, 1).'%')
            ->badge()
            ->color(fn ($record): string => static::budgetVarianceColor(static::budgetVariancePercent($record)))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showValidityDate(): TextColumn
    {
        return TextColumn::make('validity_date')
            ->label(__('resources/proformaInvoice/strings.form.validity_date'))
            ->formatStateUsing(fn (?string $state, $record) => match (true) {
                blank($state) => null,
                static::isQuoteStale($record) => __('resources/proformaInvoice/strings.table.validity_expired'),
                default => adaptiveDate($state),
            })
            ->badge(fn ($record): bool => static::isQuoteStale($record))
            ->color(fn ($record): ?string => static::isQuoteStale($record) ? 'danger' : null)
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showBuyerCompany(): TextColumn
    {
        return TextColumn::make('buyerCompany.name')
            ->label(__('resources/proformaInvoice/strings.table.buyer_company'))
            ->searchable(
                query: fn (Builder $query, string $search) => $query->whereHas('buyerCompany', fn ($q) => $q->searchCompany($search)))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true)
            ->formatStateUsing(fn ($record): ?string => $record->buyerCompany?->localized_name);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/proformaInvoice/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/proformaInvoice/strings.table.creator'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showID(): TextColumn
    {
        return TextColumn::make('id')
            ->label(__('resources/proformaInvoice/strings.table.id'))
            ->sortable()
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('proforma_invoices.id', 'like', "%{$search}%"))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showInvoiceDate(): TextColumn
    {
        return TextColumn::make('invoice_date')
            ->label(__('resources/proformaInvoice/strings.table.invoice_date'))
            ->adaptiveDate()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showInvoiceNo(): TextColumn
    {
        return TextColumn::make('invoice_no')
            ->label(__('resources/proformaInvoice/strings.table.invoice_no'))
            ->searchable(query: fn (Builder $query, string $search): Builder => ProformaInvoiceResource::orWhereExtraAttributesMatch(
                $query->where('invoice_no', 'like', "%{$search}%"),
                $search
            ))
            ->sortable()
            ->badge()
            ->copyable()
            ->tooltip(fn ($record) => adaptiveDate($record->invoice_date))
            ->toggleable();
    }

    public static function showSellerCompany(): TextColumn
    {
        return TextColumn::make('sellerCompany.name')
            ->label(__('resources/proformaInvoice/strings.table.seller_company'))
            ->searchable(
                query: fn (Builder $query, string $search) => $query->whereHas('sellerCompany', fn ($q) => $q->searchCompany($search))
            )
            ->sortable()
            ->toggleable()
            ->formatStateUsing(fn ($record): ?string => $record->sellerCompany?->localized_name);
    }

    public static function showTotalAmount(): TextColumn
    {
        return TextColumn::make('total_amount')
            ->label(__('resources/proformaInvoice/strings.table.total_amount'))
            ->sortable()
            ->numeric(decimalPlaces: 2)
            ->toggleable(isToggledHiddenByDefault: true)
            ->formatStateUsing(fn (?float $state) => $state === null ? '-' : number_format($state, 2));
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/proformaInvoice/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showRegisteredOrdersCount(): TextColumn
    {
        return TextColumn::make('registered_orders_count')
            ->label(__('resources/proformaInvoice/strings.table.registered_orders_count'))
            ->icon('heroicon-o-document-check')
            ->alignEnd()
            ->badge()
            ->color(fn (?int $state): string => $state === 0 ? 'gray' : 'success')
            ->toggleable()
            ->sortable();
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/proformaInvoice/strings.table.updater'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    protected static function showSource(): TextColumn
    {
        return TextColumn::make('source')
            ->label(__('resources/general/strings.relevant_module.table.related_to'))
            ->badge()
            ->getStateUsing(fn ($record) => Source::getAllFromRecord($record))
            ->formatStateUsing(fn (Source $state): ?string => $state->getLabel())
            ->tooltip(fn (Source $state): ?string => $state->getTooltip())
            ->iconPosition(IconPosition::Before)
            ->icon(fn (Source $state): ?string => $state->getIcon())
            ->color(fn (Source $state): string => $state->getColor())
            ->wrap();
    }
}
