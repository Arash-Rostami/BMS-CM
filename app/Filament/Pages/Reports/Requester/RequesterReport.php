<?php

namespace App\Filament\Pages\Reports\Requester;

use App\Filament\Pages\Reports\ReportPage;
use App\Filament\Resources\Operational\CustomResource\Enums\ClearanceStatus;
use App\Filament\Resources\Operational\ShipmentResource\Enums\TrackingStatus;
use App\Filament\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RegisteredOrder;
use App\Services\Reports\Builders\RequesterReportService;
use App\Services\Reports\Rules\RequesterStages;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class RequesterReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 7;

    private const LANG = 'resources/report/strings.requester.columns.';

    public static function permission(): string
    {
        return 'view_requester';
    }

    public static function service(): string
    {
        return RequesterReportService::class;
    }

    public static function key(): string
    {
        return 'requester';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('resources/report/strings.filters.heading'))->icon('heroicon-o-funnel')->collapsible()->collapsed()->compact()->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema(static::arrangeFilters([
                static::getDateFromField(),
                static::getDateUntilField(),
                static::getDateBasisField([
                    'required_by_date' => 'resources/purchaseRequest/strings.table.required_by_date',
                    'created_at' => 'resources/purchaseRequest/strings.table.created_at',
                ]),
                static::getIncludeUndatedField(),
                static::getRequesterField(),
                static::getCostCenterField(),
                static::getProductField(),
                static::getCategoryField(),
                static::getPurchaseRequestStatusField(),
                static::getStageField(),
                static::getRegisteredOrderField()->options(fn () => RegisteredOrder::query()->orderByDesc('id')->pluck('ro_number', 'id')),
                static::getShowClosedField('show_finished'),
                Section::make(__('resources/report/strings.filters.advanced'))->collapsible()->collapsed()->compact()->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema(static::arrangeFilters([
                    static::getClearedFromStageField(),
                    static::getIncludeVoidField(),
                    static::getDepartureBasisField(),
                    static::getThresholdField('arrive_1'),
                    static::getThresholdField('late_grace'),
                    static::getThresholdField('supply', 'th_supply_request'),
                ])),
            ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $this->reportTable($table)
            ->columns([
                $this->prNumberColumn(),
                $this->requiredByColumn(),
                $this->requesterColumn(),
                $this->productColumn(),
                $this->numberColumn('quantity', 'quantity'),
                TextColumn::make('unit')->label(__('resources/purchaseRequest/strings.infolist.item_unit'))->toggleable(),
                $this->shippedColumn('shipped_quantity', 'shipped_quantity'),
                $this->shippedColumn('remaining_quantity', 'remaining_quantity'),
                $this->weightColumn('gross_weight'),
                $this->weightColumn('net_weight')->toggleable(isToggledHiddenByDefault: true),
                $this->shipmentsColumn(),
                $this->stageColumn(),
                $this->etaColumn(),
                $this->latestStatusColumn(),
                $this->prStatusColumn(),
            ])
            ->defaultSort('required_by_date');
    }

    protected function analyticsWidgets(): array
    {
        return [RequesterStageChart::class, RequesterLateByStageChart::class];
    }

    protected function attentionWidget(): ?string
    {
        return RequesterAttentionWidget::class;
    }

    private function prNumberColumn(): TextColumn
    {
        return TextColumn::make('pr_number')
            ->label(__('resources/purchaseRequest/strings.table.pr_number'))
            ->badge()
            ->copyable()
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('pr.pr_number', $direction))
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('pr.pr_number', 'like', "%{$search}%"))
            ->url(fn (PurchaseRequestItem $record): ?string => PurchaseRequestResource::canEdit(new PurchaseRequest) ? PurchaseRequestResource::getUrl('edit', ['record' => $record->purchase_request_id]) : null);
    }

    private function requiredByColumn(): TextColumn
    {
        return TextColumn::make('required_by_date')
            ->label(__('resources/purchaseRequest/strings.table.required_by_date'))
            ->formatStateUsing(fn ($state): ?string => $state ? adaptiveDate($state) : null)
            ->color(fn (PurchaseRequestItem $record): ?string => $record->required_by_date && $record->stage !== 'cleared' && today()->gt(Carbon::parse($record->required_by_date)->addDays($this->filterObject->threshold('late_grace'))) ? 'danger' : null)
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('pr.required_by_date', $direction));
    }

    private function requesterColumn(): TextColumn
    {
        return TextColumn::make('requester_name')
            ->label(__('resources/purchaseRequest/strings.table.requester'))
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('u.name', 'like', "%{$search}%"))
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('u.name', $direction));
    }

    private function productColumn(): TextColumn
    {
        return TextColumn::make('product_name')
            ->label(__('resources/purchaseRequest/strings.form.product'))
            ->state(fn (PurchaseRequestItem $record): ?string => $record->product?->localized_name)
            ->wrap()
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('product', fn (Builder $products): Builder => $products->where('name', 'like', "%{$search}%")->orWhere('english_name', 'like', "%{$search}%")));
    }

    private function numberColumn(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)
            ->label(__(self::LANG.$label))->formatStateUsing(fn ($state): string => $state !== null ? delimiter($state) : '-')
            ->tooltip(fn ($state): ?string => $state !== null ? preciseNumber($state) : null);
    }

    private function shippedColumn(string $name, string $label): TextColumn
    {
        return $this->numberColumn($name, $label)
            ->tooltip(fn (PurchaseRequestItem $record, $state): ?string => match (true) {
                $state !== null => preciseNumber($state),
                (bool) $record->chain_unspecific => __(self::LANG.'chain_unspecific'),
                (bool) $record->unit_mismatch => __(self::LANG.'unit_mismatch'),
                default => null,
            });
    }

    private function weightColumn(string $name): TextColumn
    {
        return $this->numberColumn($name, $name)
            ->toggleable()
            ->tooltip(fn (PurchaseRequestItem $record, $state): ?string => match (true) {
                $state !== null => preciseNumber($state),
                (bool) $record->chain_unspecific => __(self::LANG.'chain_unspecific'),
                default => null,
            });
    }

    private function shipmentsColumn(): TextColumn
    {
        return TextColumn::make('shipments')
            ->label(__(self::LANG.'shipments'))
            ->state(fn (PurchaseRequestItem $record): array => $record->shipment_labels)
            ->badge()
            ->color('gray')
            ->toggleable();
    }

    private function stageColumn(): TextColumn
    {
        return TextColumn::make('stage')
            ->label(__(self::LANG.'stage'))->badge()
            ->formatStateUsing(fn (string $state): string => RequesterStages::label($state))
            ->color(fn (string $state): string => RequesterStages::COLORS[$state]);
    }

    private function etaColumn(): TextColumn
    {
        return TextColumn::make('eta')
            ->label(__(self::LANG.'eta'))->state(fn (PurchaseRequestItem $record): ?string => $record->eta_missing ? 'missing' : $record->latest_eta)
            ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                null => null,
                'missing' => __(self::LANG.'no_eta'),
                default => adaptiveDate($state),
            })
            ->badge(fn (PurchaseRequestItem $record): bool => (bool) $record->eta_missing)
            ->color(fn (?string $state): ?string => $state === 'missing' ? 'danger' : null)
            ->placeholder('-');
    }

    private function latestStatusColumn(): TextColumn
    {
        return TextColumn::make('latest_status')
            ->label(__(self::LANG.'latest_status'))->state(fn (PurchaseRequestItem $record): ?string => $record->latest_status?->localized_name)
            ->badge()
            ->color(fn (PurchaseRequestItem $record): string|array|null => ClearanceStatus::tryFrom((string) $record->latest_status?->english_name)?->getColor()
                ?? TrackingStatus::tryFrom((string) $record->latest_status?->english_name)?->getColor()
                ?? 'gray')
            ->placeholder('-');
    }

    private function prStatusColumn(): TextColumn
    {
        return TextColumn::make('pr_status')
            ->label(__(self::LANG.'pr_status'))
            ->state(fn (PurchaseRequestItem $record): ?string => $record->pr_status?->localized_name)
            ->badge()
            ->color('gray')
            ->toggleable(isToggledHiddenByDefault: true)
            ->placeholder('-');
    }
}
