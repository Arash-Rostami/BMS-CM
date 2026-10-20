<?php

namespace App\Filament\Pages\Reports\Operations;

use App\Filament\Pages\Reports\ReportPage;
use App\Filament\Resources\Operational\BankProfileResource\Enums\Status as BankProfileStatus;
use App\Filament\Resources\Operational\CustomResource\Enums\ClearanceStatus;
use App\Filament\Resources\Operational\ShipmentResource\Enums\TrackingStatus;
use App\Filament\Resources\RegisteredOrderResource;
use App\Models\RegisteredOrder;
use App\Services\Reports\Builders\OperationsReportService;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OperationsReport extends ReportPage
{
    private const LANG = 'resources/report/strings.operations.columns.';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 2;

    public static function permission(): string
    {
        return 'view_operations';
    }

    public static function service(): string
    {
        return OperationsReportService::class;
    }

    public static function key(): string
    {
        return 'operations';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('resources/report/strings.filters.heading'))->icon('heroicon-o-funnel')->collapsible()->collapsed()->compact()->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema(static::arrangeFilters([
                static::getDateFromField(),
                static::getDateUntilField(),
                static::getDateBasisField([
                    'order_date' => 'resources/registeredOrder/strings.form.order_date',
                    'validity_date' => 'resources/registeredOrder/strings.form.validity_date',
                    'created_at' => 'resources/registeredOrder/strings.table.created_at',
                ]),
                static::getIncludeUndatedField(),
                static::getSellerField(),
                static::getBuyerField(),
                static::getCompanyField(),
                static::getCategoryField(),
                static::getProductField(),
                static::getCurrencyField(),
                static::getRegisteredOrderField(),
                static::getRegisteredOrderStatusField(),
                static::getOperationsStageField(),
                static::getShowClosedField(),
                static::getClearedFromStageField(),
                Section::make(__('resources/report/strings.filters.advanced'))->collapsible()->collapsed()->compact()->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema(static::arrangeFilters([
                    static::getActivityWindowField(),
                    static::getCauserField(),
                    static::getModuleField(),
                    static::getIdleDaysField(),
                    static::getThresholdField('idle_error'),
                    static::getThresholdField('validity'),
                    static::getThresholdField('supply'),
                ])),
            ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $this->reportTable($table)
            ->columns([
                RegisteredOrderResource::showRoNumber(),
                RegisteredOrderResource::showCtNumber()->toggleable(isToggledHiddenByDefault: true),
                RegisteredOrderResource::showSeller(),
                RegisteredOrderResource::showBuyer(),
                $this->currencyColumn(),
                $this->productsColumn(),
                $this->stageColumn(),
                RegisteredOrderResource::showStatus(),
                RegisteredOrderResource::showOrderDate(),
                RegisteredOrderResource::showValidityDate()->toggleable(isToggledHiddenByDefault: false),
                RegisteredOrderResource::showPurchaseOrdersCount(),
                $this->statusColumn('fx_status', 'fx_status', fn (string $name): ?string => BankProfileStatus::tryFrom($name)?->getColor()),
                $this->statusColumn('shipment_status', 'shipment_status', fn (string $name): ?string => TrackingStatus::tryFrom($name)?->getColor()),
                $this->statusColumn('operation_status', 'operation_status', fn (): string => 'gray', true),
                $this->statusColumn('doc_status', 'doc_status', fn (): string => 'gray', true),
                $this->statusColumn('clearance_status', 'clearance_status', fn (string $name): ?string => ClearanceStatus::tryFrom($name)?->getColor()),
                $this->lastOperationColumn(),
                $this->lastActivityColumn(),
                TextColumn::make('last_by')->label(__(self::LANG.'last_by'))->state(fn ($record): ?string => $record->last_event['by'] ?? null)->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                $this->idleDaysColumn(),
                $this->lastCorrespondenceColumn(),
            ])
            ->groups($this->groups())
            ->defaultSort('idle_days', 'desc');
    }

    private function direction(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    private function groups(): array
    {
        $service = app(OperationsReportService::class);

        return [
            Group::make('seller')
                ->label(__('resources/report/strings.filters.seller'))
                ->getKeyFromRecordUsing(fn (RegisteredOrder $record): ?int => $record->seller_id)
                ->getTitleFromRecordUsing(fn (RegisteredOrder $record): string => $record->sellerCompany?->getLocalizedNameAttribute() ?? '-')
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw(OperationsReportService::sellerNameSql().' '.$this->direction($direction)))
                ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => $query->where('registered_orders.seller_id', $key)),
            Group::make('stage')
                ->label(__(self::LANG.'stage'))
                ->getTitleFromRecordUsing(fn (RegisteredOrder $record): string => __(OperationsReportService::STAGES[$record->stage]))
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw($service->stageOrderSql().' '.$this->direction($direction)))
                ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => $query->whereRaw('('.$service->stageSql()[0].') = ?', [...$service->stageSql()[1], $key])),
        ];
    }

    protected function analyticsWidgets(): array
    {
        return [OperationsStageChart::class, OperationsIdleByStageChart::class, OperationsActivityWidget::class];
    }

    protected function attentionWidget(): ?string
    {
        return OperationsAttentionWidget::class;
    }

    private function productsColumn(): TextColumn
    {
        return TextColumn::make('products')
            ->label(__(self::LANG.'products'))
            ->state(fn (RegisteredOrder $record): array => $record->items->pluck('product')->filter()->unique('id')->map->localized_name->values()->all())
            ->badge()
            ->limitList(3)
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('items.product', fn (Builder $products): Builder => $products->where(fn (Builder $match): Builder => $match
                ->where('products.name', 'like', "%{$search}%")
                ->orWhere('products.english_name', 'like', "%{$search}%")
                ->orWhere('products.code', 'like', "%{$search}%"))));
    }

    private function currencyColumn(): TextColumn
    {
        return TextColumn::make('currency.name')
            ->label(__('resources/registeredOrder/strings.form.currency'))
            ->formatStateUsing(fn (RegisteredOrder $record): ?string => $record->currency?->getLocalizedNameAttribute())
            ->placeholder('-')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private function stageColumn(): TextColumn
    {
        return TextColumn::make('stage')
            ->label(__(self::LANG.'stage'))->badge()
            ->formatStateUsing(fn (string $state): string => __(OperationsReportService::STAGES[$state]))
            ->sortable();
    }

    private function statusColumn(string $name, string $attribute, callable $color, bool $hidden = false): TextColumn
    {
        return TextColumn::make($name)
            ->label(__(self::LANG.$name))->state(fn (RegisteredOrder $record): ?string => $record->{$attribute}?->localized_name)
            ->badge()
            ->color(fn (RegisteredOrder $record): string => $color((string) $record->{$attribute}?->english_name) ?? 'gray')
            ->placeholder('-')
            ->toggleable(isToggledHiddenByDefault: $hidden);
    }

    private function lastOperationColumn(): TextColumn
    {
        return TextColumn::make('last_operation')
            ->label(__(self::LANG.'last_operation'))->state(fn (RegisteredOrder $record): ?string => $record->last_event['text'] ?? null)
            ->placeholder('-')
            ->wrap();
    }

    private function lastActivityColumn(): TextColumn
    {
        return TextColumn::make('last_activity_at')
            ->label(__(self::LANG.'last_activity'))->adaptiveDateTime()
            ->placeholder('-')
            ->sortable();
    }

    private function idleDaysColumn(): TextColumn
    {
        return TextColumn::make('idle_days')
            ->label(__(self::LANG.'idle_days'))->badge()
            ->color(fn ($state): string => match (true) {
                $state > $this->filterObject->threshold('idle_error') => 'danger',
                $state > $this->filterObject->threshold('idle') => 'warning',
                default => 'gray',
            })
            ->tooltip(fn (RegisteredOrder $record): ?string => $record->idle_from_updated_at ? __('resources/report/strings.attention.no_history_note') : null)
            ->sortable();
    }

    private function lastCorrespondenceColumn(): TextColumn
    {
        return TextColumn::make('last_correspondence')
            ->label(__(self::LANG.'last_correspondence'))
            ->state(fn (RegisteredOrder $record): ?string => $record->latest_mail?->subject)
            ->description(fn (RegisteredOrder $record): ?string => $record->latest_mail?->recipients->filter(fn ($user): bool => $user->pivot->type === 'to')->pluck('name')->implode(', ') ?: null)
            ->placeholder('-')
            ->limit(40)
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
