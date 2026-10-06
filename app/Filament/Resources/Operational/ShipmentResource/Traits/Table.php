<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Traits;

use App\Filament\Actions\ImportAction;
use App\Filament\Resources\Operational\ShipmentResource\Imports\ShipmentImporter;
use App\Filament\Resources\ShipmentResource;
use App\Jobs\ExportShipments;
use App\Models\Shipment;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait Table
{
    private const DUE_SOON_DAYS = 3;

    public static function getImportAction(): ImportAction
    {
        return ImportAction::make('importShipments')
            ->label(__('resources/shipment/strings.import.import_shipments'))
            ->modalHeading(__('resources/shipment/strings.import.import_shipments'))
            ->icon('heroicon-o-arrow-up-tray')
            ->importer(ShipmentImporter::class)
            ->resourceGate(ShipmentResource::class);
    }

    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportShipments')
            ->label(__('resources/shipment/strings.export.export_shipments'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => ShipmentResource::canViewAny())
            ->action(function (Collection $records): void {
                ExportShipments::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function showBlNumber(): TextColumn
    {
        return TextColumn::make('bl_number')
            ->label(__('resources/shipment/strings.table.bl_number'))
            ->searchable()
            ->copyable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCarrier(): TextColumn
    {
        return TextColumn::make('carrier.name')
            ->label(__('resources/shipment/strings.table.carrier'))
            ->sortable()
            ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('carrier', fn ($q) => $q->searchCompany($search)))
            ->formatStateUsing(fn (Shipment $record): ?string => $record->carrier?->getLocalizedNameAttribute())
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showContainerStatus(): TextColumn
    {
        return TextColumn::make('containerStatus.name')
            ->label(__('resources/shipment/strings.table.container_status'))
            ->badge()
            ->formatStateUsing(fn ($record) => $record->containerStatus?->getLocalizedNameAttribute())
            ->color('gray')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/shipment/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/shipment/strings.table.created_by'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private static function docsProgressStats(Shipment $record): ?array
    {
        if (! $record->isDocumentTrackingEnabled()) {
            return null;
        }

        $rows = collect($record->documentChecklist())->reject(fn ($row) => ($row['name'] ?? null) === 'track');

        return $rows->isEmpty() ? null : ['received' => $rows->where('received', true)->count(), 'total' => $rows->count()];
    }

    public static function showDocsProgress(): TextColumn
    {
        return TextColumn::make('docs_progress')
            ->label(__('resources/shipment/strings.table.docs_progress'))
            ->state(fn (Shipment $record) => ($stats = static::docsProgressStats($record)) ? "{$stats['received']}/{$stats['total']}" : null)
            ->placeholder('—')
            ->badge()
            ->color(function (Shipment $record): string {
                $stats = static::docsProgressStats($record);

                return match (true) {
                    $stats === null => 'gray',
                    $stats['received'] === 0 => 'danger',
                    $stats['received'] === $stats['total'] => 'success',
                    default => 'warning',
                };
            })
            ->toggleable();
    }

    public static function showDocStatus(): TextColumn
    {
        return TextColumn::make('docStatus.name')
            ->label(__('resources/shipment/strings.table.doc_status'))
            ->badge()
            ->formatStateUsing(fn ($record) => $record->docStatus?->getLocalizedNameAttribute())
            ->color('gray')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showEtaStatus(): TextColumn
    {
        return TextColumn::make('eta_status')
            ->label(__('resources/shipment/strings.table.eta_status'))
            ->state(function (Shipment $record): ?string {
                if (! $record->eta) {
                    return null;
                }

                if ($record->exit_date) {
                    return 'on_track';
                }

                $today = now()->startOfDay();

                if ($record->eta->lt($today)) {
                    return 'overdue';
                }

                return $record->eta->lte($today->copy()->addDays(self::DUE_SOON_DAYS)) ? 'due_soon' : 'on_track';
            })
            ->formatStateUsing(fn (?string $state): string => match ($state) {
                'overdue' => '🔴 '.__('resources/shipment/strings.table.eta_status_overdue'),
                'due_soon' => '🟡 '.__('resources/shipment/strings.table.eta_status_due_soon'),
                'on_track' => '🟢 '.__('resources/shipment/strings.table.eta_status_on_track'),
                default => '—',
            })
            ->badge()
            ->color(fn (?string $state): string => match ($state) {
                'overdue' => 'danger',
                'due_soon' => 'warning',
                'on_track' => 'success',
                default => 'gray',
            })
            ->toggleable();
    }

    public static function showId(): TextColumn
    {
        return TextColumn::make('id')
            ->label(__('resources/shipment/strings.table.id'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showOperationStatus(): TextColumn
    {
        return TextColumn::make('operationStatus.name')
            ->label(__('resources/shipment/strings.table.operation_status'))
            ->badge()
            ->formatStateUsing(fn ($record) => $record->operationStatus?->getLocalizedNameAttribute())
            ->color('gray')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPart(): TextColumn
    {
        return TextColumn::make('part')
            ->label(__('resources/shipment/strings.table.part'))
            ->sortable()
            ->badge()
            ->color('info')
            ->toggleable();
    }

    public static function showRegisteredOrder(): TextColumn
    {
        return TextColumn::make('registeredOrder.ro_number')
            ->label(__('resources/shipment/strings.table.registered_order'))
            ->searchable(
                query: fn (Builder $query, string $search) => $query->whereHas(
                    'registeredOrder',
                    fn (Builder $q) => $q->searchAll($search)
                ), isIndividual: true)
            ->sortable()
            ->copyable()
            ->iconPosition(IconPosition::Before)
            ->icon('heroicon-o-document-check')
            ->badge()
            ->color('info')
            ->tooltip(fn (Shipment $record) => ' 💼 '.$record->contract_no)
            ->toggleable();
    }

    public static function showShipmentNo(): TextColumn
    {
        return TextColumn::make('shipment_no')
            ->label(__('resources/shipment/strings.table.shipment_no'))
            ->searchable(query: fn (Builder $query, string $search): Builder => ShipmentResource::orWhereExtraAttributesMatch(
                $query->where('shipment_no', 'like', "%{$search}%"),
                $search
            ))
            ->badge()
            ->copyable()
            ->sortable()
            ->tooltip(fn (Shipment $record) => $record->bl_number);
    }

    public static function showStatus(): TextColumn
    {
        return TextColumn::make('status.name')
            ->label(__('resources/shipment/strings.table.status'))
            ->badge()
            ->sortable()
            ->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas('status', fn ($q) => $q->searchStatus($search)))
            ->formatStateUsing(fn ($record) => $record->status?->getLocalizedNameAttribute())
            ->color('primary');
    }

    public static function showTrackingStatus(): TextColumn
    {
        return TextColumn::make('trackingStatus.name')
            ->label(__('resources/shipment/strings.table.tracking_status'))
            ->badge()
            ->formatStateUsing(fn ($record) => $record->trackingStatus?->getLocalizedNameAttribute())
            ->color('warning')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/shipment/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/shipment/strings.table.updated_by'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
