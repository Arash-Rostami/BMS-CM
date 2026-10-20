<?php

namespace App\Filament\Pages\Reports\FxCommitments;

use App\Filament\Pages\Reports\ReportPage;
use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Company;
use App\Services\Reports\Builders\FxCommitmentsReportService;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class FxCommitmentsReport extends ReportPage
{
    private const LANG = 'resources/report/strings.fx_commitments.';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 6;

    public static function permission(): string
    {
        return 'view_fx_commitments';
    }

    public static function service(): string
    {
        return FxCommitmentsReportService::class;
    }

    public static function key(): string
    {
        return 'fx_commitments';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('resources/report/strings.filters.heading'))->icon('heroicon-o-funnel')->collapsible()->collapsed()->compact()->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema(static::arrangeFilters([
                static::getDateFromField(),
                static::getDateUntilField(),
                static::getDateBasisField([
                    'commitment_payment_date' => self::LANG.'columns.commitment_due_date',
                    'creation_date' => 'resources/bankProfile/strings.form.creation_date',
                    'purchase_date' => 'resources/bankProfile/strings.form.purchase_date',
                ]),
                static::getIncludeUndatedField(),
                static::getBankField(),
                static::getCompanyField(),
                static::getRegisteredOrderField(),
                static::getPurchasedCurrencyField(),
                static::getSupplySourceField(),
                static::getBankProfileStatusField(),
                static::getCommitmentStateField(),
                static::getCommitmentStatusField(),
                static::getBankGuaranteeStatusField(),
                static::getClearedFromStageField(),
                static::getAmountInField(),
                static::getThresholdField('late_grace'),
                static::getThresholdField('commit_due'),
                Section::make(__('resources/report/strings.filters.advanced'))->collapsible()->collapsed()->compact()->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                    static::getRateBasisField(),
                    static::getValueBasisField(),
                    ...static::arrangeFilters([
                        static::getIncludeFlaggedInvoicesField(),
                        static::getIncludeOutliersField(),
                        static::getFallbackOrderAmountField(),
                        static::getIncludeVoidField(),
                        static::getThresholdField('exceed_pct'),
                        static::getThresholdField('outlier_factor'),
                    ]),
                ]),
            ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $this->reportTable($table)
            ->columns([
                $this->cell('ro_number')->badge()->searchable(query: fn (Builder $query, string $search): Builder => $query->whereRaw('ro.ro_number like ?', ["%{$search}%"]))->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('ro.ro_number', $this->direction($direction))),
                $this->cell('bp_number')->searchable(query: fn (Builder $query, string $search): Builder => $query->where('bank_profiles.bp_number', 'like', "%{$search}%"))->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('bank_profiles.bp_number', $this->direction($direction))),
                $this->cell('company')->searchable(query: fn (Builder $query, string $search): Builder => $query->whereIn('bank_profiles.company_id', Company::query()->select('id')->where(fn (Builder $names) => $names->where('name', 'like', "%{$search}%")->orWhere('english_name', 'like', "%{$search}%")))),
                $this->cell('bank')->searchable(query: fn (Builder $query, string $search): Builder => $query->whereIn('bank_profiles.bank_id', Bank::query()->select('id')->where(fn (Builder $names) => $names->where('name', 'like', "%{$search}%")->orWhere('english_name', 'like', "%{$search}%")))),
                $this->cell('supply_source')->toggleable(isToggledHiddenByDefault: true),
                $this->cell('status')->badge()->color('gray'),
                $this->cell('requested')->toggleable(isToggledHiddenByDefault: true),
                $this->cell('purchased_currency'),
                $this->money('purchased_equivalent', 'purchased_equivalent')->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('bank_profiles.purchased_equivalent', $this->direction($direction))),
                $this->money('eur_purchased'),
                $this->money('documents_amount')->tooltip(__(self::LANG.'tooltips.documents_amount'))->toggleable(isToggledHiddenByDefault: true),
                $this->money('resolved', 'res_share')->tooltip(fn (BankProfile $record): string => __(self::LANG.'tooltips.resolved', ['resolved' => delimiter($record->ord_resolved), 'used' => $record->led_used, 'cleared' => $record->led_cleared, 'flags' => app(FxCommitmentsReportService::class)->cell($record, 'flags') ?? '-']))->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('res_share', $this->direction($direction))),
                $this->money('remaining', 'rem_share')->tooltip(fn (BankProfile $record): string => __(self::LANG.'tooltips.remaining', ['remaining' => delimiter($record->ord_remaining), 'excess' => delimiter(max((float) $record->ord_resolved - (float) $record->ord_purchased, 0))]))->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('rem_share', $this->direction($direction))),
                $this->money('eur_remaining'),
                $this->cell('resolved_pct')->suffix('%'),
                TextColumn::make('commitment_payment_date')->label(__(FxCommitmentsReportService::EXPORT_COLUMNS['commitment_payment_date']))->adaptiveDate()->sortable(),
                $this->cell('days_to_due')->badge()->color(fn ($state): string => match (true) {
                    $state === null => 'gray',
                    $state < 0 => 'danger',
                    $state <= $this->filterObject->threshold('commit_due') => 'warning',
                    default => 'gray',
                })->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('days_to_due', $this->direction($direction))),
                $this->cell('state')->badge()->color(fn (BankProfile $record): string => match ($record->state) {
                    'resolved' => 'success',
                    'overdue' => 'danger',
                    'open' => 'info',
                    default => 'gray',
                })->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('state', $this->direction($direction))),
                $this->cell('commitment_status'),
                $this->cell('guarantee_status')->toggleable(isToggledHiddenByDefault: true),
                $this->cell('ten_percent_exit_date')->toggleable(isToggledHiddenByDefault: true),
                $this->cell('rial_return_date')->toggleable(isToggledHiddenByDefault: true),
                $this->cell('flags')->badge()->color('warning'),
            ])
            ->groups($this->groups())
            ->defaultSort(fn (Builder $query): Builder => $query->orderByRaw('bank_profiles.commitment_payment_date is null')->orderBy('bank_profiles.commitment_payment_date'));
    }

    protected function analyticsWidgets(): array
    {
        return [FxCommitmentsByMonthChart::class, FxCommitmentsByBankChart::class];
    }

    protected function attentionWidget(): ?string
    {
        return FxCommitmentsAttentionWidget::class;
    }

    private function cell(string $key): TextColumn
    {
        return TextColumn::make($key)
            ->label(__(FxCommitmentsReportService::EXPORT_COLUMNS[$key]))->state(fn (BankProfile $record): mixed => app(FxCommitmentsReportService::class)->cell($record, $key))
            ->placeholder('-');
    }

    private function money(string $key, ?string $summarize = null): TextColumn
    {
        $column = $this->cell($key)
            ->formatStateUsing(fn ($state): string => delimiter($state))
            ->tooltip(fn ($state): ?string => $state === null ? null : preciseNumber($state))
            ->alignEnd();

        return $summarize === null ? $column : $column->summarize(
            Summarizer::make()
                ->label(__(self::LANG.'columns.total'))
                ->using(fn (QueryBuilder $query): string => app(FxCommitmentsReportService::class)->currencyTotals($query, $summarize))
        );
    }

    private function direction(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    private function groups(): array
    {
        $column = app()->getLocale() === 'fa' ? 'name' : 'english_name';
        $named = fn (string $table, string $foreign): string => "(select {$table}.{$column} from {$table} where {$table}.id = bank_profiles.{$foreign})";

        return [
            Group::make('order')
                ->label(__(FxCommitmentsReportService::EXPORT_COLUMNS['ro_number']))
                ->getKeyFromRecordUsing(fn (BankProfile $record): ?int => $record->registered_order_id)
                ->getTitleFromRecordUsing(fn (BankProfile $record): string => $record->ro_number ?? '-')
                ->groupQueryUsing(fn ($query) => $query->groupBy('bank_profiles.registered_order_id'))
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy('ro.ro_number', $this->direction($direction)))
                ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => $query->where('bank_profiles.registered_order_id', $key)),
            Group::make('company')
                ->label(__(FxCommitmentsReportService::EXPORT_COLUMNS['company']))
                ->getKeyFromRecordUsing(fn (BankProfile $record): ?int => $record->company_id)
                ->getTitleFromRecordUsing(fn (BankProfile $record): string => $record->company?->getLocalizedNameAttribute() ?? '-')
                ->groupQueryUsing(fn ($query) => $query->groupBy('bank_profiles.company_id'))
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw($named('companies', 'company_id').' '.$this->direction($direction)))
                ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => $query->where('bank_profiles.company_id', $key)),
            Group::make('bank')
                ->label(__(FxCommitmentsReportService::EXPORT_COLUMNS['bank']))
                ->getKeyFromRecordUsing(fn (BankProfile $record): ?int => $record->bank_id)
                ->getTitleFromRecordUsing(fn (BankProfile $record): string => $record->bank?->getLocalizedNameAttribute() ?? '-')
                ->groupQueryUsing(fn ($query) => $query->groupBy('bank_profiles.bank_id'))
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw($named('banks', 'bank_id').' '.$this->direction($direction)))
                ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => $query->where('bank_profiles.bank_id', $key)),
            Group::make('currency')
                ->label(__(FxCommitmentsReportService::EXPORT_COLUMNS['purchased_currency']))
                ->getKeyFromRecordUsing(fn (BankProfile $record): ?int => $record->purchased_currency_id)
                ->getTitleFromRecordUsing(fn (BankProfile $record): string => $record->purchasedCurrency?->getLocalizedNameAttribute() ?? '-')
                ->groupQueryUsing(fn ($query) => $query->groupBy('bank_profiles.purchased_currency_id'))
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw($named('currencies', 'purchased_currency_id').' '.$this->direction($direction)))
                ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => $query->where('bank_profiles.purchased_currency_id', $key)),
        ];
    }
}
