<?php

namespace App\Filament\Resources\Operational\PaymentResource\Traits;

use App\Filament\Actions\ImportAction;
use App\Filament\Resources\Operational\PaymentResource\Enums\Target;
use App\Filament\Resources\Operational\PaymentResource\Imports\PaymentImporter;
use App\Filament\Resources\PaymentResource;
use App\Jobs\ExportPayments;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait Table
{
    public static function getImportAction(): ImportAction
    {
        return ImportAction::make('importPayments')
            ->label(__('resources/payment/strings.import.import_payments'))
            ->modalHeading(__('resources/payment/strings.import.import_payments'))
            ->icon('heroicon-o-arrow-up-tray')
            ->importer(PaymentImporter::class)
            ->resourceGate(PaymentResource::class);
    }

    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportPayments')
            ->label(__('resources/payment/strings.export.export_payments'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => PaymentResource::canViewAny())
            ->action(function (Collection $records): void {
                ExportPayments::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/payment/strings.table.created_at'))->adaptiveDateTime()
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/payment/strings.table.created_by'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showId(): TextColumn
    {
        return TextColumn::make('id')
            ->label(__('resources/payment/strings.table.id'))
            ->sortable()
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('payments.id', 'like', "%{$search}%"))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPayee(): TextColumn
    {
        return TextColumn::make('payee.name')
            ->label(__('resources/payment/strings.table.payee'))
            ->sortable()
            ->searchable(
                query: fn (Builder $query, string $search) => $query->whereHas('payee', fn ($q) => $q->searchCompany($search))
            )
            ->formatStateUsing(fn ($record): ?string => $record->payee?->getLocalizedNameAttribute())
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPaymentDate(): TextColumn
    {
        return TextColumn::make('payment_date')
            ->label(__('resources/payment/strings.table.payment_date'))
            ->adaptiveDate()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPaymentNo(): TextColumn
    {
        return TextColumn::make('payment_no')
            ->label(__('resources/payment/strings.table.payment_no'))
            ->searchable(query: fn (Builder $query, string $search): Builder => PaymentResource::orWhereExtraAttributesMatch(
                $query->where('payment_no', 'like', "%{$search}%"),
                $search
            ))
            ->badge()
            ->copyable()
            ->sortable()
            ->tooltip(fn ($record) => $record->payment_date ? adaptiveDate($record->payment_date) : null);
    }

    public static function showPayor(): TextColumn
    {
        return TextColumn::make('payor.name')
            ->label(__('resources/payment/strings.table.payor'))
            ->sortable()
            ->searchable(
                query: fn (Builder $query, string $search) => $query->whereHas('payor', fn ($q) => $q->searchCompany($search))
            )
            ->formatStateUsing(fn ($record): ?string => $record->payor?->getLocalizedNameAttribute())
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showStatus(): TextColumn
    {
        return TextColumn::make('status.name')
            ->label(__('resources/payment/strings.table.status'))
            ->badge()
            ->sortable()
            ->searchable(
                query: fn (Builder $query, string $search) => $query->orWhereHas('status', fn ($q) => $q->searchStatus($search))
            )
            ->formatStateUsing(fn ($record): ?string => $record->status?->getLocalizedNameAttribute())
            ->color('gray');
    }

    public static function showTargetable(): TextColumn
    {
        return TextColumn::make('targetable')
            ->label(__('resources/payment/strings.table.targetable'))
            ->badge()
            ->getStateUsing(fn ($record) => Target::getFromRecord($record))
            ->formatStateUsing(fn (Target $state): ?string => $state->getLabel())
            ->tooltip(fn (Target $state): ?string => $state->getTooltip())
            ->iconPosition(IconPosition::Before)
            ->icon(fn (Target $state): ?string => $state->getIcon())
            ->color(fn (Target $state): string => $state->getColor())
            ->searchable(false)
            ->toggleable();
    }

    public static function showTargetableType(): TextColumn
    {
        return TextColumn::make('targetable_type')
            ->label(__('resources/payment/strings.table.targetable_type'))
            ->badge()
            ->getStateUsing(fn ($record) => Target::getFromRecord($record))
            ->formatStateUsing(function (Target $state, Model $record) {
                $name = $record->getTargetableDisplay(false);
                if ($state === Target::None || $name === '-') {
                    return $state->getLabel();
                }

                return $name;
            })
            ->icon(false)
            ->color(fn (Target $state): string => $state->getColor())
            ->searchable(
                query: function (Builder $query, string $search): Builder {
                    return $query->whereHasMorph(
                        'targetable',
                        [PurchaseOrder::class, RegisteredOrder::class],
                        fn (Builder $q) => $q->searchAll($search)
                    );
                },
                isIndividual: true
            )->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showTotalAmount(): TextColumn
    {
        return TextColumn::make('total_amount')
            ->label(__('resources/payment/strings.table.total_amount'))
            ->formatStateUsing(fn ($state, $record) => delimiter($state))
            ->sortable()
            ->toggleable();
    }

    public static function showTotalMatch(): TextColumn
    {
        return TextColumn::make('total_ratio')
            ->label(__('resources/payment/strings.table.total_match'))
            ->state(fn (Model $record): ?float => $record->total_ratio)
            ->formatStateUsing(fn (?float $state): string => match (true) {
                $state === null => __('resources/payment/strings.table.total_match_unknown'),
                abs($state - 1) < 0.0001 => __('resources/payment/strings.table.total_match_yes'),
                default => __('resources/payment/strings.table.total_match_no'),
            })
            ->badge()
            ->color(fn (?float $state): string => match (true) {
                $state === null => 'gray',
                abs($state - 1) < 0.0001 => 'success',
                default => 'warning',
            })
            ->toggleable()
            ->sortable(false);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/payment/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/payment/strings.table.updater'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
