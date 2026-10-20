<?php

namespace App\Filament\Pages\Reports\FxSupply;

use App\Filament\Pages\Reports\ReportPage;
use App\Filament\Resources\BankProfileResource;
use App\Models\BankProfile;
use App\Services\Reports\Builders\FxSupplyReportService;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FxSupplyReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 3;

    public static function permission(): string
    {
        return 'view_fx_supply';
    }

    public static function service(): string
    {
        return FxSupplyReportService::class;
    }

    public static function key(): string
    {
        return 'fx_supply';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('resources/report/strings.filters.heading'))->icon('heroicon-o-funnel')->collapsible()->collapsed()->compact()->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema(static::arrangeFilters([
                static::getDateFromField(),
                static::getDateUntilField(),
                static::getDateBasisField([
                    'creation_date' => 'resources/bankProfile/strings.form.creation_date',
                    'allocation_date' => 'resources/bankProfile/strings.form.allocation_date',
                    'purchase_date' => 'resources/bankProfile/strings.form.purchase_date',
                    'payment_due_date' => 'resources/bankProfile/strings.table.payment_due_date',
                ]),
                static::getIncludeUndatedField(),
                static::getBankField(),
                static::getCompanyField(),
                static::getRegisteredOrderField(),
                static::getRequestedCurrencyField(),
                static::getPurchasedCurrencyField(),
                static::getSupplySourceField(),
                static::getBankProfileStatusField(),
                static::getShowClosedField('show_finished_profiles'),
                static::getThresholdField('stuck', 'th_stuck'),
                static::getThresholdField('due_soon'),
            ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $this->reportTable($table)
            ->columns([
                BankProfileResource::showBpNumber(),
                BankProfileResource::showRegisteredOrder(),
                BankProfileResource::showBank()->toggleable(isToggledHiddenByDefault: false),
                BankProfileResource::showCompany(),
                BankProfileResource::showTargetable(),
                BankProfileResource::showStatus(),
                $this->daysInStatusColumn(),
                BankProfileResource::showRequestedAmount()->toggleable(isToggledHiddenByDefault: false),
                BankProfileResource::showRequestedCurrency()->toggleable(isToggledHiddenByDefault: false),
                $this->purchasedEquivalentColumn(),
                $this->supplySourceColumn(),
                $this->rateColumn('final_rate'),
                $this->rateColumn('commission_rate'),
                $this->dateColumn('creation_date', 'creation_date'),
                $this->dateColumn('allocation_date', 'allocation_date'),
                $this->dateColumn('purchase_date', 'purchase_date'),
                BankProfileResource::showPaymentDueDate()->toggleable(isToggledHiddenByDefault: false),
            ])
            ->groups($this->groups())
            ->defaultSort('days_in_status', 'desc');
    }

    private function groups(): array
    {
        $column = app()->getLocale() === 'fa' ? 'name' : 'english_name';

        return [
            Group::make('bank')
                ->label(__('resources/bankProfile/strings.filters.bank'))
                ->getKeyFromRecordUsing(fn (BankProfile $record): ?int => $record->bank_id)
                ->getTitleFromRecordUsing(fn (BankProfile $record): string => $record->bank?->getLocalizedNameAttribute() ?? '-')
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw("(select banks.{$column} from banks where banks.id = bank_profiles.bank_id) ".(strtolower($direction) === 'desc' ? 'desc' : 'asc')))
                ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => $query->where('bank_profiles.bank_id', $key)),
        ];
    }

    protected function analyticsWidgets(): array
    {
        return [FxSupplyFunnelChart::class, FxSupplyByBankChart::class];
    }

    protected function attentionWidget(): ?string
    {
        return FxSupplyAttentionWidget::class;
    }

    private function daysInStatusColumn(): TextColumn
    {
        return TextColumn::make('days_in_status')
            ->label(__('resources/report/strings.fx_supply.columns.days_in_status'))->sortable()
            ->badge()
            ->color(fn ($state): string => $state > $this->filterObject->threshold('stuck') ? 'danger' : 'gray')
            ->tooltip(fn ($record): ?string => $record->without_history ? __('resources/report/strings.attention.no_history_note') : null);
    }

    private function supplySourceColumn(): TextColumn
    {
        return TextColumn::make('supply_source')
            ->label(__('resources/bankProfile/strings.form.supply_source'))
            ->formatStateUsing(fn ($state): ?string => $state ? __('resources/bankProfile/strings.general.supply_sources.'.$state) : null)
            ->placeholder('-')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private function rateColumn(string $name): TextColumn
    {
        return TextColumn::make($name)
            ->label(__('resources/bankProfile/strings.form.'.$name))
            ->formatStateUsing(fn ($state): string => preciseNumber($state))
            ->placeholder('-')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private function purchasedEquivalentColumn(): TextColumn
    {
        return TextColumn::make('purchased_equivalent')
            ->label(__('resources/bankProfile/strings.form.purchased_equivalent'))
            ->formatStateUsing(fn ($state, $record): string => trim(delimiter($state).' '.$record->purchasedCurrency?->getLocalizedNameAttribute()))
            ->tooltip(fn ($state): string => preciseNumber($state))
            ->sortable();
    }

    private function dateColumn(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)
            ->label(__('resources/bankProfile/strings.form.'.$label))
            ->adaptiveDate()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
