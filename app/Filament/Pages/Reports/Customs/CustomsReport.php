<?php

namespace App\Filament\Pages\Reports\Customs;

use App\Filament\Pages\Reports\ReportPage;
use App\Filament\Resources\CustomResource;
use App\Models\Custom;
use App\Models\Shipment;
use App\Services\Reports\Builders\CustomsReportService;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomsReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 5;

    public static function permission(): string
    {
        return 'view_customs';
    }

    public static function service(): string
    {
        return CustomsReportService::class;
    }

    public static function key(): string
    {
        return 'customs';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('resources/report/strings.filters.heading'))->icon('heroicon-o-funnel')->collapsible()->collapsed()->compact()->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema(static::arrangeFilters([
                static::getDateFromField(),
                static::getDateUntilField(),
                static::getDateBasisField([
                    'doc_submission_date' => 'resources/custom/strings.form.doc_submission_date',
                    'clearance_date' => 'resources/custom/strings.form.clearance_date',
                    'warehouse_date' => 'resources/shipment/strings.form.warehouse_date',
                    'eta' => 'resources/shipment/strings.form.eta',
                    'created_at' => 'resources/custom/strings.table.created_at',
                ]),
                static::getIncludeUndatedField(),
                static::getSellerField(),
                static::getBuyerField(),
                static::getRegisteredOrderField(),
                static::getProductField(),
                static::getCategoryField(),
                static::getCarrierField(),
                static::getClearanceTypeField(),
                static::getClearanceStatusField(),
                static::getBankGuaranteeStatusField(),
                static::getCommitmentStatusField(),
                static::getStatusField('doc_status_id', 'resources/report/strings.filters.doc_status', Shipment::TYPE_DOC_STATUS),
                static::getClearedFromStageField(),
                static::getArrivalBasisField(),
                static::getShowClosedField(),
                static::getStuckDaysField(),
            ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $this->reportTable($table)
            ->columns([
                CustomResource::showCustomNo(),
                CustomResource::showDeclarationNo(),
                CustomResource::showShipment(),
                CustomResource::showContractNo(),
                $this->orderColumn(),
                $this->sellerColumn(),
                $this->productsColumn(),
                $this->weightColumn(),
                CustomResource::showClearanceType(),
                CustomResource::showClearanceStatus(),
                $this->dateColumn('shipment.warehouse_date', 'resources/shipment/strings.form.warehouse_date'),
                $this->dateColumn('shipment.eta', 'resources/shipment/strings.form.eta'),
                $this->dateColumn('doc_submission_date', 'resources/custom/strings.form.doc_submission_date', false),
                $this->daysInCustomsColumn(),
                CustomResource::showClearanceDate(),
                $this->customsQuantityColumn(),
                $this->dateColumn('ten_percent_exit_date', 'resources/custom/strings.form.ten_percent_exit_date'),
                CustomResource::showBankGuaranteeStatus(),
                CustomResource::showCommitmentStatus(),
                $this->docStatusColumn(),
            ])
            ->groups($this->groups())
            ->defaultSort('days_in_customs', 'desc');
    }

    private function direction(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    private function groups(): array
    {
        $service = app(CustomsReportService::class);

        return [
            Group::make('clearance_status_id')
                ->label(__('resources/custom/strings.form.clearance_status'))
                ->getTitleFromRecordUsing(fn (Custom $record): string => $record->clearanceStatus?->getLocalizedNameAttribute() ?? '-')
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw($service->stageOrderSql().' '.$this->direction($direction))),
            Group::make('seller')
                ->label(__('resources/report/strings.filters.seller'))
                ->getKeyFromRecordUsing(fn (Custom $record): ?int => $record->registeredOrder?->seller_id)
                ->getTitleFromRecordUsing(fn (Custom $record): string => $record->registeredOrder?->sellerCompany?->getLocalizedNameAttribute() ?? '-')
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw(CustomsReportService::sellerNameSql().' '.$this->direction($direction)))
                ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => $query->whereHas('registeredOrder', fn (Builder $order): Builder => $order->where('registered_orders.seller_id', $key))),
        ];
    }

    private function sellerColumn(): TextColumn
    {
        return TextColumn::make('registeredOrder.sellerCompany.name')
            ->label(__('resources/report/strings.filters.seller'))
            ->state(fn (Custom $record): ?string => $record->registeredOrder?->sellerCompany?->getLocalizedNameAttribute())
            ->placeholder('-')
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('registeredOrder.sellerCompany', fn (Builder $companies): Builder => $companies->searchCompany($search)))
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(CustomsReportService::sellerNameSql().' '.$this->direction($direction)))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    protected function analyticsWidgets(): array
    {
        return [CustomsStageChart::class, CustomsClearanceByMonthChart::class];
    }

    protected function attentionWidget(): ?string
    {
        return CustomsAttentionWidget::class;
    }

    private function orderColumn(): TextColumn
    {
        return TextColumn::make('registeredOrder.ro_number')
            ->label(__('resources/custom/strings.form.registered_order'))
            ->badge()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private function productsColumn(): TextColumn
    {
        return TextColumn::make('products_text')
            ->label(__('resources/report/strings.customs.columns.products'))
            ->wrap()
            ->tooltip(fn ($record): ?string => $record->weight_source === 'order' ? __('resources/report/strings.customs.columns.from_order') : null)
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereRaw(CustomsReportService::PRODUCTS_SQL.' like ?', ["%{$search}%"]))
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(CustomsReportService::PRODUCTS_SQL.' '.$this->direction($direction)));
    }

    private function weightColumn(): TextColumn
    {
        return TextColumn::make('total_weight')
            ->label(__('resources/report/strings.customs.columns.weight'))->formatStateUsing(fn ($state): string => $state !== null ? delimiter($state) : '-')
            ->tooltip(fn ($state): ?string => $state !== null ? preciseNumber($state) : null)
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(CustomsReportService::WEIGHT_SQL.' '.$this->direction($direction)))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private function daysInCustomsColumn(): TextColumn
    {
        return TextColumn::make('days_in_customs')
            ->label(__('resources/report/strings.customs.columns.days_in_customs'))->sortable()
            ->badge()
            ->formatStateUsing(fn ($state): string => $state === null ? '-' : (string) $state)
            ->color(fn ($state): string => CustomsReportService::agingColor($state !== null ? (int) $state : null, $this->filterObject->threshold('stuck')))
            ->tooltip(fn ($record): ?string => $record->basis_missing ? __('resources/report/strings.customs.columns.no_basis') : null);
    }

    private function customsQuantityColumn(): TextColumn
    {
        return TextColumn::make('shipment.customs_quantity')
            ->label(__('resources/shipment/strings.form.customs_quantity'))
            ->formatStateUsing(fn ($state): string => $state !== null ? delimiter($state) : '-')
            ->tooltip(fn ($state): ?string => $state !== null ? preciseNumber($state) : null)
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private function docStatusColumn(): TextColumn
    {
        return TextColumn::make('doc_status')
            ->label(__('resources/shipment/strings.table.doc_status'))
            ->badge()
            ->color('gray')
            ->state(fn (Custom $record): ?string => $record->shipment?->docStatus?->getLocalizedNameAttribute())
            ->placeholder('-')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private function dateColumn(string $name, string $labelKey, bool $hidden = true): TextColumn
    {
        return TextColumn::make($name)
            ->label(__($labelKey))
            ->adaptiveDate()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: $hidden);
    }
}
