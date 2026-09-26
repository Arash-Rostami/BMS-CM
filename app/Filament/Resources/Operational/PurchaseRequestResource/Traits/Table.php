<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Traits;

use App\Filament\Actions\GroupedImportAction;
use App\Filament\Resources\Operational\PurchaseRequestResource\Enums\Source;
use App\Filament\Resources\Operational\PurchaseRequestResource\Enums\Status;
use App\Filament\Resources\Operational\PurchaseRequestResource\Imports\PurchaseRequestImporter;
use App\Filament\Resources\PurchaseRequestResource;
use App\Jobs\ExportPurchaseRequests;
use App\Models\Department;
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
        return GroupedImportAction::make('importPurchaseRequests')
            ->label(__('resources/purchaseRequest/strings.import.import_requests'))
            ->modalHeading(__('resources/purchaseRequest/strings.import.import_requests'))
            ->icon('heroicon-o-arrow-up-tray')
            ->importer(PurchaseRequestImporter::class)
            ->resourceGate(PurchaseRequestResource::class)
            ->itemDiscriminatorColumn('product_id')
            ->itemOnlyColumns(['quantity', 'unit', 'estimated_cost', 'item_status_id', 'item_notes']);
    }

    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportPurchaseRequests')
            ->label(__('resources/purchaseRequest/strings.export.export_requests'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => PurchaseRequestResource::canViewAny())
            ->action(function (Collection $records): void {
                ExportPurchaseRequests::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function sortByRelatedDepartmentName(string $foreignKey): \Closure
    {
        return fn (Builder $query, string $direction): Builder => $query->orderBy(
            Department::select(app()->getLocale() === 'fa' ? 'name' : 'english_name')
                ->whereColumn('departments.id', "purchase_requests.{$foreignKey}"),
            $direction
        );
    }

    public static function showCostCenter(): TextColumn
    {
        return TextColumn::make('costCenter.localized_name')
            ->label(__('resources/purchaseRequest/strings.form.cost_center'))
            ->tooltip(fn ($record) => $record?->requester->name)
            ->sortable(query: static::sortByRelatedDepartmentName('cost_center_id'))
            ->searchable(
                query: fn (Builder $query, string $search) => $query->orWhereHas('costCenter', fn ($q) => $q->searchDepartment($search))
            )
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/purchaseRequest/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/purchaseRequest/strings.table.creator'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showDepartment(): TextColumn
    {
        return TextColumn::make('department.localized_name')
            ->label(__('resources/purchaseRequest/strings.table.department'))
            ->tooltip(fn ($record) => $record?->requester->name)
            ->sortable(query: static::sortByRelatedDepartmentName('department_id'))
            ->searchable(
                query: fn (Builder $query, string $search) => $query->orWhereHas('department', fn ($q) => $q->searchDepartment($search))
            )
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showID(): TextColumn
    {
        return TextColumn::make('id')
            ->label(__('resources/purchaseRequest/strings.table.id'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true)
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('purchase_requests.id', 'like', "%{$search}%"));
    }

    public static function showPrNumber(): TextColumn
    {
        return TextColumn::make('pr_number')
            ->label(__('resources/purchaseRequest/strings.table.pr_number'))
            ->sortable()
            ->badge()
            ->copyable()
            ->toggleable()
            ->searchable(query: fn (Builder $query, string $search): Builder => PurchaseRequestResource::orWhereExtraAttributesMatch(
                $query->where('pr_number', 'like', "%{$search}%"),
                $search
            ));
    }

    public static function showProformaInvoiceCount()
    {
        return TextColumn::make('proforma_invoices_count')
            ->label(__('resources/purchaseRequest/strings.table.pi_count'))
            ->icon('heroicon-o-document-text')
            ->alignEnd()
            ->badge()
            ->color(fn (int $state): string => match ($state) {
                0 => 'gray',
                default => 'success',
            })
            ->toggleable()
            ->sortable();
    }

    public static function showRequester(): TextColumn
    {
        return TextColumn::make('requester.name')
            ->label(__('resources/purchaseRequest/strings.table.requester'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true)
            ->searchable();
    }

    private static function requiredByDateUrgency($record): ?string
    {
        $date = $record?->required_by_date;

        if (! $date || in_array($record->status?->english_name, ['Authorized', 'Declined'], true)) {
            return null;
        }

        $today = today();

        return match (true) {
            $date->lt($today) => 'overdue',
            $date->lte($today->copy()->addDays(3)) => 'due_soon',
            default => null,
        };
    }

    public static function showRequiredByDate(): TextColumn
    {
        return TextColumn::make('required_by_date')
            ->label(__('resources/purchaseRequest/strings.table.required_by_date'))
            ->formatStateUsing(fn (?string $state, $record) => match (static::requiredByDateUrgency($record)) {
                'overdue' => __('resources/purchaseRequest/strings.table.overdue'),
                'due_soon' => __('resources/purchaseRequest/strings.table.due_soon'),
                default => blank($state) ? null : adaptiveDate($state),
            })
            ->badge(fn ($record): bool => static::requiredByDateUrgency($record) !== null)
            ->color(fn ($record): ?string => match (static::requiredByDateUrgency($record)) {
                'overdue' => 'danger',
                'due_soon' => 'warning',
                default => null,
            })
            ->sortable()
            ->toggleable();
    }

    public static function showStatus(): TextColumn
    {
        return TextColumn::make('status.english_name')
            ->label(__('resources/purchaseRequest/strings.table.status'))
            ->badge()
            ->toggleable()
            ->searchable(
                query: fn (Builder $query, string $search) => $query->orWhereHas('status', fn ($q) => $q->searchStatus($search))
            )
            ->iconPosition(IconPosition::Before)
            ->formatStateUsing(fn (?string $state): ?string => Status::tryFrom($state)?->getLabel() ?? $state)
            ->color(fn (?string $state): ?string => Status::tryFrom($state)?->getColor() ?? 'info')
            ->icon(fn (?string $state): ?string => Status::tryFrom($state)?->getIcon() ?? 'heroicon-o-question-mark-circle')
            ->sortable();
    }

    public static function showTotalCost(): TextColumn
    {
        return TextColumn::make('total_estimated_cost')
            ->label(__('resources/purchaseRequest/strings.table.total_estimated_cost'))
            ->sortable()
            ->formatStateUsing(fn (?float $state) => $state === null
                ? '-'
                : number_format($state, 2)
            )
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/purchaseRequest/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
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

    public static function showUrgency(): TextColumn
    {
        return TextColumn::make('urgency_level')
            ->label(__('resources/purchaseRequest/strings.table.urgency_level'))
            ->badge()
            ->toggleable(isToggledHiddenByDefault: true)
            ->formatStateUsing(fn (?string $state): ?string => __("resources/purchaseRequest/strings.general.urgency.{$state}") ?? $state)
            ->color(fn (?string $state): string => match ($state) {
                'high' => 'danger',
                'medium' => 'warning',
                'low' => 'success',
                default => 'gray'
            })
            ->sortable();
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
