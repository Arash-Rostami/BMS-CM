<?php

namespace App\Filament\Pages\Reports\InTransit;

use App\Filament\Pages\Reports\ReportPage;
use App\Filament\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\Reports\Builders\InTransitReportService;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InTransitReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 4;

    public static function permission(): string
    {
        return 'view_in_transit';
    }

    public static function service(): string
    {
        return InTransitReportService::class;
    }

    public static function key(): string
    {
        return 'in_transit';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('resources/report/strings.filters.heading'))->icon('heroicon-o-funnel')->collapsible()->collapsed()->compact()->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema(static::arrangeFilters([
                static::getDateFromField(),
                static::getDateUntilField(),
                static::getDateBasisField([
                    'eta' => 'resources/shipment/strings.form.eta',
                    'etd' => 'resources/shipment/strings.form.etd',
                    'exit_date' => 'resources/shipment/strings.form.exit_date',
                    'warehouse_date' => 'resources/shipment/strings.form.warehouse_date',
                ]),
                static::getIncludeUndatedField(),
                static::getSellerField(),
                static::getBuyerField(),
                static::getRegisteredOrderField(),
                static::getProductField(),
                static::getCategoryField(),
                static::getCarrierField(),
                static::getStatusField('status_id', 'resources/report/strings.filters.tracking_status', Shipment::TYPE_TRACKING_STATUS, true),
                static::getStatusField('shipment_status_id', 'resources/report/strings.filters.shipment_status', Shipment::TYPE_SHIPMENT_STATUS, true),
                static::getStatusField('doc_status_id', 'resources/report/strings.filters.doc_status', Shipment::TYPE_DOC_STATUS),
                static::getShowArrivedField(),
                static::getIncludeVoidField(),
                static::getDepartureBasisField(),
                static::getThresholdField('arrive_1'),
                static::getThresholdField('arrive_2'),
                static::getThresholdField('late_error'),
                static::getThresholdField('stuck', 'stuck_days_stage'),
            ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $this->reportTable($table)
            ->columns([
                ShipmentResource::showShipmentNo(),
                ShipmentResource::showPart(),
                ShipmentResource::showRegisteredOrder(),
                $this->sellerColumn(),
                ShipmentResource::showCarrier(),
                $this->productsColumn(),
                $this->weightColumn(),
                $this->dateColumn('etd', 'resources/shipment/strings.form.etd'),
                $this->dateColumn('exit_date', 'resources/shipment/strings.form.exit_date'),
                $this->dateColumn('eta', 'resources/shipment/strings.form.eta', false),
                $this->dateColumn('warehouse_date', 'resources/shipment/strings.form.warehouse_date'),
                $this->daysToEtaColumn(),
                $this->stateColumn(),
                ShipmentResource::showTrackingStatus()->toggleable(isToggledHiddenByDefault: false),
                ShipmentResource::showDocStatus(),
                ShipmentResource::showDocsProgress(),
                ShipmentResource::showBlNumber(),
                $this->containerColumn(),
            ])
            ->groups($this->groups())
            ->defaultSort(fn (Builder $query): Builder => app(InTransitReportService::class)->orderByEta($query));
    }

    protected function analyticsWidgets(): array
    {
        return [InTransitStageChart::class, InTransitEtaChart::class];
    }

    protected function attentionWidget(): ?string
    {
        return InTransitAttentionWidget::class;
    }

    private function direction(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    private function groups(): array
    {
        return [
            Group::make('company_id')
                ->label(__('resources/shipment/strings.table.carrier'))
                ->getTitleFromRecordUsing(fn (Shipment $record): string => $record->carrier?->getLocalizedNameAttribute() ?? '-')
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw(InTransitReportService::carrierNameSql().' '.$this->direction($direction))),
            Group::make('seller')
                ->label(__('resources/report/strings.filters.seller'))
                ->getKeyFromRecordUsing(fn (Shipment $record): ?int => $record->registeredOrder?->seller_id)
                ->getTitleFromRecordUsing(fn (Shipment $record): string => $record->registeredOrder?->sellerCompany?->getLocalizedNameAttribute() ?? '-')
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw(InTransitReportService::sellerNameSql().' '.$this->direction($direction)))
                ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => $query->whereHas('registeredOrder', fn (Builder $order): Builder => $order->where('registered_orders.seller_id', $key))),
        ];
    }

    private function sellerColumn(): TextColumn
    {
        return TextColumn::make('registeredOrder.sellerCompany.name')
            ->label(__('resources/report/strings.filters.seller'))
            ->state(fn (Shipment $record): ?string => $record->registeredOrder?->sellerCompany?->getLocalizedNameAttribute())
            ->placeholder('-')
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('registeredOrder.sellerCompany', fn (Builder $companies): Builder => $companies->searchCompany($search)))
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(InTransitReportService::sellerNameSql().' '.$this->direction($direction)))
            ->toggleable();
    }

    private function productsColumn(): TextColumn
    {
        return TextColumn::make('products_text')
            ->label(__('resources/report/strings.in_transit.columns.products'))
            ->wrap()
            ->tooltip(fn ($record): ?string => $record->weight_source === 'order' ? __('resources/report/strings.in_transit.columns.from_order') : null)
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereRaw(InTransitReportService::PRODUCTS_SQL.' like ?', ["%{$search}%"]))
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(InTransitReportService::PRODUCTS_SQL.' '.$this->direction($direction)));
    }

    private function weightColumn(): TextColumn
    {
        return TextColumn::make('total_weight')
            ->label(__('resources/report/strings.in_transit.columns.weight'))->formatStateUsing(fn ($state): string => $state !== null ? delimiter($state) : '-')
            ->tooltip(fn ($state): ?string => $state !== null ? preciseNumber($state) : null)
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(InTransitReportService::WEIGHT_SQL.' '.$this->direction($direction)))
            ->toggleable();
    }

    private function containerColumn(): TextColumn
    {
        return TextColumn::make('container_no')
            ->label(__('resources/shipment/strings.form.container_no'))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private function daysToEtaColumn(): TextColumn
    {
        return TextColumn::make('days_to_eta')
            ->label(__('resources/report/strings.in_transit.columns.days_to_eta'))->badge()
            ->state(fn (Shipment $record): string => match (true) {
                $record->days_to_eta === null => __('resources/report/strings.in_transit.columns.no_eta'),
                $record->days_to_eta < 0 => __('resources/report/strings.in_transit.columns.late', ['count' => abs($record->days_to_eta)]),
                default => __('resources/report/strings.in_transit.columns.left', ['count' => $record->days_to_eta]),
            })
            ->color(fn (Shipment $record): string => match (true) {
                $record->days_to_eta === null => 'warning',
                $record->days_to_eta < 0 => 'danger',
                $record->days_to_eta <= $this->filterObject->threshold('arrive_1') => 'warning',
                default => 'success',
            })
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw('shipments.eta is null')->orderBy('shipments.eta', $direction));
    }

    private function stateColumn(): TextColumn
    {
        return TextColumn::make('state')
            ->label(__('resources/report/strings.in_transit.columns.state'))->badge()
            ->formatStateUsing(fn (?string $state): string => __('resources/report/strings.in_transit.states.'.($state ?? 'awaiting')))
            ->color(fn (?string $state): string => match ($state) {
                'in_transit' => 'info',
                'arrived' => 'success',
                default => 'gray',
            })
            ->searchable(query: fn (Builder $query, string $search): Builder => app(InTransitReportService::class)->searchState($query, $search, $this->filterObject))
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('state', $this->direction($direction)))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private function dateColumn(string $name, string $labelKey, bool $hidden = true): TextColumn
    {
        return TextColumn::make($name)
            ->label(__($labelKey))
            ->adaptiveDate()
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy("shipments.{$name}", $direction))
            ->toggleable(isToggledHiddenByDefault: $hidden);
    }
}
