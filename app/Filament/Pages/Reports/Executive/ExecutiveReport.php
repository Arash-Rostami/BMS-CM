<?php

namespace App\Filament\Pages\Reports\Executive;

use App\Filament\Pages\Reports\ReportPage;
use App\Filament\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\Reports\Builders\ExecutiveReportService;
use App\Services\Reports\Support\ReportFilters;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\ArrayRecord;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ExecutiveReport extends ReportPage
{
    private const LANG = 'resources/report/strings.executive.';

    private const SUPPLY_COLORS = [0 => 'success', 1 => 'warning', 2 => 'danger', 3 => 'info', 4 => 'gray', -1 => 'gray'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?int $navigationSort = 1;

    #[Url(as: 'mode')]
    public string $viewMode = 'shipment';

    public static function permission(): string
    {
        return 'view_executive';
    }

    public static function service(): string
    {
        return ExecutiveReportService::class;
    }

    public static function key(): string
    {
        return 'executive';
    }

    #[Computed]
    public function filterObject(): ReportFilters
    {
        return ReportFilters::fromArray([...($this->filters ?? []), 'quick' => $this->quick, 'view_mode' => $this->viewMode]);
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ReportFilters::VIEW_MODES, true) ? $mode : ReportFilters::VIEW_MODES[0];
        $this->modeChanged();
    }

    public function updatedViewMode(): void
    {
        $this->modeChanged();
    }

    public function filterTableBy(mixed $key = null): void
    {
        if ($this->filterObject->viewMode !== 'shipment') {
            $this->viewMode = 'shipment';
            $this->modeChanged();
        }

        parent::filterTableBy($key);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('resources/report/strings.filters.heading'))->icon('heroicon-o-funnel')->collapsible()->collapsed()->compact()->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema(static::arrangeFilters([
                static::getDateFromField(),
                static::getDateUntilField(),
                static::getSellerField(),
                static::getBuyerField(),
                static::getRegisteredOrderField(),
                static::getProductField(),
                static::getCategoryField(),
                static::getCarrierField(),
                static::getStatusField('status_id', 'resources/report/strings.filters.tracking_status', Shipment::TYPE_TRACKING_STATUS, true),
                static::getStatusField('shipment_status_id', 'resources/report/strings.filters.shipment_status', Shipment::TYPE_SHIPMENT_STATUS, true),
                static::getStatusField('doc_status_id', 'resources/report/strings.filters.doc_status', Shipment::TYPE_DOC_STATUS),
                Section::make(__('resources/report/strings.filters.advanced'))->collapsible()->collapsed()->compact()->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema(static::arrangeFilters([
                    static::getDateBasisField([
                        'eta' => 'resources/shipment/strings.form.eta',
                        'etd' => 'resources/shipment/strings.form.etd',
                        'exit_date' => 'resources/shipment/strings.form.exit_date',
                        'warehouse_date' => 'resources/shipment/strings.form.warehouse_date',
                    ]),
                    static::getIncludeUndatedField(),
                    static::getIncludeFlaggedInvoicesField(),
                    static::getIncludeOutliersField(),
                    static::getWeightBasisField(),
                    static::getDepartureBasisField(),
                    static::getIncludeVoidField(),
                    static::getThresholdField('arrive_1'),
                    static::getThresholdField('arrive_2'),
                    static::getThresholdField('outlier_factor'),
                    static::getThresholdField('supply_full'),
                    static::getThresholdField('top_n'),
                ])),
            ])),
        ]);
    }

    public function table(Table $table): Table
    {
        $table = $this->reportTable($table);

        return $this->filterObject->viewMode === 'product' ? $this->productTable($table) : $this->shipmentTable($table);
    }

    protected function getHeaderActions(): array
    {
        return [$this->modeAction(), $this->resetFiltersAction(), $this->exportCsvAction()];
    }

    protected function analyticsWidgets(): array
    {
        return [ExecutiveValueByCategoryChart::class, ExecutiveEtaValueChart::class];
    }

    protected function attentionWidget(): ?string
    {
        return ExecutiveAttentionWidget::class;
    }

    private function modeAction(): Action
    {
        return Action::make('viewMode')
            ->label(fn (): string => __(self::LANG.'mode.'.($this->filterObject->viewMode === 'product' ? 'shipment' : 'product')))
            ->icon(fn (): string => $this->filterObject->viewMode === 'product' ? 'heroicon-o-truck' : 'heroicon-o-cube')
            ->color('gray')
            ->action(fn () => $this->setViewMode($this->filterObject->viewMode === 'product' ? 'shipment' : 'product'));
    }

    private function modeChanged(): void
    {
        $this->quick = null;
        $this->tableColumns = [];
        $this->cachedDefaultTableColumnState = null;
        unset($this->filterObject, $this->quickLabel);
        $this->resetTable();
    }

    private function exec(): ExecutiveReportService
    {
        return app(ExecutiveReportService::class);
    }

    private function direction(?string $direction): string
    {
        return strtolower((string) $direction) === 'desc' ? 'desc' : 'asc';
    }

    private function shipmentTable(Table $table): Table
    {
        return $table
            ->columns([
                ShipmentResource::showShipmentNo(),
                ShipmentResource::showPart(),
                ShipmentResource::showRegisteredOrder(),
                $this->sellerColumn(),
                ShipmentResource::showCarrier(),
                $this->productsColumn(),
                $this->valueColumn(),
                $this->ledColumn('led_eur', 'eur', 'led_eur', fn ($state): string => delimiter($state)),
                $this->sourceColumn(),
                $this->supplyColumn(),
                $this->ledColumn('supplied_percent', 'supplied_percent', 'led_ratio', fn ($state): string => number_format((float) $state * 100, 1, '.', '').'%'),
                $this->suppliedRemainingColumn(),
                $this->ledColumn('led_weight', 'weight', 'led_weight', fn ($state): string => delimiter($state)),
                $this->dateColumn('etd', 'resources/shipment/strings.form.etd', true),
                $this->dateColumn('eta', 'resources/shipment/strings.form.eta', false),
                $this->daysToEtaColumn(),
                ShipmentResource::showTrackingStatus()->toggleable(isToggledHiddenByDefault: false),
                $this->flagsColumn(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByRaw('led.led_eur is null')->orderByDesc('led.led_eur')->orderBy('shipments.id'));
    }

    private function productTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Shipment::query())
            ->records(fn (?string $search, ?string $sortColumn, ?string $sortDirection, int $page, int|string $recordsPerPage): LengthAwarePaginator => $this->productPage($search, $sortColumn, $sortDirection, $page, $recordsPerPage))
            ->columns([
                TextColumn::make('category')->label(__(self::LANG.'columns.category'))->wrap()->searchable()->sortable()->placeholder('-'),
                TextColumn::make('product')->label(__(self::LANG.'columns.product'))->wrap()->searchable()->sortable()->description(fn (array $record): ?string => ($record['no_product'] ?? false) ? __(self::LANG.'columns.no_product_flag') : null),
                TextColumn::make('quantity')->label(__(self::LANG.'columns.quantity'))->sortable()->placeholder('-')->formatStateUsing(fn ($state, array $record): string => trim(delimiter($state).' '.($record['unit'] ?? ''))),
                TextColumn::make('net_weight')->label(__(self::LANG.'columns.net_weight'))->sortable()->placeholder('-')->formatStateUsing(fn ($state): string => delimiter($state))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gross_weight')->label(__(self::LANG.'columns.gross_weight'))->sortable()->placeholder('-')->formatStateUsing(fn ($state): string => delimiter($state)),
                TextColumn::make('eur')->label(__(self::LANG.'columns.eur'))->sortable()->formatStateUsing(fn ($state): string => delimiter($state)),
                TextColumn::make('shipments')->label(__(self::LANG.'columns.shipments'))->sortable()->placeholder('-'),
                TextColumn::make('eta')->label(__('resources/shipment/strings.form.eta'))->sortable()->placeholder('-')->formatStateUsing(fn ($state): ?string => $state ? adaptiveDate($state) : null),
                TextColumn::make('supply')->label(__(self::LANG.'columns.supply'))->sortable()->badge()->placeholder('-')
                    ->formatStateUsing(fn ($state): string => $this->exec()->supplyLabel($state === null ? null : (int) $state))
                    ->color(fn ($state): string => self::SUPPLY_COLORS[(int) $state] ?? 'gray'),
            ]);
    }

    private function productPage(?string $search, ?string $sortColumn, ?string $sortDirection, int $page, int|string $perPage): LengthAwarePaginator
    {
        $lines = $this->exec()->productRows($this->filterObject);
        $footer = $lines->pop();
        $term = mb_strtolower(trim((string) $search));
        $lines = $lines
            ->when($term !== '', fn (Collection $items): Collection => $items->filter(fn (array $line): bool => str_contains(mb_strtolower($line['category'].' '.$line['product']), $term)))
            ->when(in_array($sortColumn, ['category', 'product', 'quantity', 'net_weight', 'gross_weight', 'eur', 'shipments', 'eta', 'supply'], true), fn (Collection $items): Collection => $items->sortBy($sortColumn, SORT_REGULAR, $this->direction($sortDirection) === 'desc'))
            ->push($footer)
            ->values();
        $size = is_numeric($perPage) ? max((int) $perPage, 1) : max($lines->count(), 1);
        $records = $lines->map(fn (array $line): array => [ArrayRecord::getKeyName() => $line['key']] + $line);

        return new LengthAwarePaginator($records->forPage($page, $size)->values(), $records->count(), $size, $page);
    }

    private function ledColumn(string $name, string $labelKey, string $attribute, callable $format): TextColumn
    {
        return TextColumn::make($name)
            ->label(__(self::LANG."columns.{$labelKey}"))->placeholder('-')
            ->state(fn (Shipment $record): ?string => $record->{$attribute} === null ? null : $format($record->{$attribute}))
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw("led.{$attribute} is null")->orderBy("led.{$attribute}", $this->direction($direction)))
            ->toggleable();
    }

    private function valueColumn(): TextColumn
    {
        return TextColumn::make('led_value')
            ->label(__(self::LANG.'columns.value'))->placeholder('-')
            ->formatStateUsing(fn ($state, Shipment $record): string => delimiter($state, $this->exec()->currencyNames()[$record->led_currency_id] ?? null))
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw('led.led_value is null')->orderBy('led.led_value', $this->direction($direction)));
    }

    private function sourceColumn(): TextColumn
    {
        return TextColumn::make('source')
            ->label(__(self::LANG.'columns.source'))->badge()
            ->state(fn (Shipment $record): string => $this->exec()->sourceLabel($record))
            ->color(fn (Shipment $record): string => match (true) {
                $record->led_reason === null => 'gray',
                (int) $record->led_reason !== 0 => 'danger',
                (bool) $record->led_estimated => 'warning',
                default => 'success',
            })
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('led.led_reason', $this->direction($direction))->orderBy('led.led_estimated', $this->direction($direction)));
    }

    private function supplyColumn(): TextColumn
    {
        return TextColumn::make('supply')
            ->label(__(self::LANG.'columns.supply'))->badge()
            ->state(fn (Shipment $record): ?int => $record->led_supply === null ? null : (int) $record->led_supply)
            ->formatStateUsing(fn ($state): string => $this->exec()->supplyLabel($state))
            ->color(fn ($state): string => self::SUPPLY_COLORS[(int) $state] ?? 'gray')
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('led.led_supply', $this->direction($direction)));
    }

    private function suppliedRemainingColumn(): TextColumn
    {
        return TextColumn::make('supplied_remaining')
            ->label(__(self::LANG.'columns.supplied_remaining'))->placeholder('-')
            ->state(fn (Shipment $record): ?string => $record->led_supplied === null ? null : delimiter($record->led_supplied).' / '.delimiter(max((float) $record->led_order_value - (float) $record->led_supplied, 0), $this->exec()->currencyNames()[$record->registeredOrder?->currency_id] ?? null))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private function flagsColumn(): TextColumn
    {
        return TextColumn::make('flags')
            ->label(__(self::LANG.'columns.flags'))->badge()
            ->color('warning')
            ->placeholder('-')
            ->state(fn (Shipment $record): ?string => ($text = $this->exec()->flagsText((int) $record->led_flags)) === '' ? null : $text)
            ->toggleable();
    }

    private function sellerColumn(): TextColumn
    {
        return TextColumn::make('registeredOrder.sellerCompany.name')
            ->label(__('resources/report/strings.filters.seller'))
            ->state(fn (Shipment $record): ?string => $record->registeredOrder?->sellerCompany?->getLocalizedNameAttribute())
            ->placeholder('-')
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('registeredOrder.sellerCompany', fn (Builder $companies): Builder => $companies->searchCompany($search)))
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(ExecutiveReportService::sellerNameSql().' '.$this->direction($direction)))
            ->toggleable();
    }

    private function productsColumn(): TextColumn
    {
        return TextColumn::make('products_text')
            ->label(__(self::LANG.'columns.products'))
            ->wrap()
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereRaw(ExecutiveReportService::productsSql().' like ?', ["%{$search}%"]))
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(ExecutiveReportService::productsSql().' '.$this->direction($direction)))
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
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw('shipments.eta is null')->orderBy('shipments.eta', $this->direction($direction)));
    }

    private function dateColumn(string $name, string $labelKey, bool $hidden): TextColumn
    {
        return TextColumn::make($name)
            ->label(__($labelKey))
            ->adaptiveDate()
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy("shipments.{$name}", $this->direction($direction)))
            ->toggleable(isToggledHiddenByDefault: $hidden);
    }
}
