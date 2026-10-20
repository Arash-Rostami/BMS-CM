<?php

namespace App\Services\Reports\Builders;

use App\Filament\Resources\Operational\BankProfileResource\Enums\Status as BankProfileStatus;
use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Currency;
use App\Models\Product;
use App\Services\Calendar\Display\CalendarRange;
use App\Services\Reports\Checks\BankProfileChecks;
use App\Services\Reports\Rules\FxRateService;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\SqlToday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FxSupplyReportService implements ReportService
{
    public const QUEUED = [BankProfileStatus::ReadyForAllocation, BankProfileStatus::AwaitingBankReview, BankProfileStatus::AwaitingSupply];

    private const LANG = 'resources/report/strings.fx_supply.';

    private const CARD_QUICK = '/^(queued|allocated|purchased_month)(?:_currency_(\d+))?$/';

    private const EXPORT_COLUMNS = [
        'bp_number' => 'resources/bankProfile/strings.table.bp_number',
        'registered_order' => 'resources/bankProfile/strings.table.registered_order',
        'bank' => 'resources/bankProfile/strings.table.bank',
        'company' => 'resources/bankProfile/strings.table.company',
        'targetable' => 'resources/bankProfile/strings.table.targetable',
        'supply_source' => 'resources/bankProfile/strings.form.supply_source',
        'status' => 'resources/bankProfile/strings.table.status',
        'requested_currency' => 'resources/bankProfile/strings.table.requested_currency',
        'requested_amount' => 'resources/bankProfile/strings.table.requested_amount',
        'purchased_currency' => 'resources/bankProfile/strings.form.purchased_currency',
        'purchased_equivalent' => 'resources/bankProfile/strings.form.purchased_equivalent',
        'final_rate' => 'resources/bankProfile/strings.form.final_rate',
        'commission_rate' => 'resources/bankProfile/strings.form.commission_rate',
        'creation_date' => 'resources/bankProfile/strings.form.creation_date',
        'allocation_date' => 'resources/bankProfile/strings.form.allocation_date',
        'purchase_date' => 'resources/bankProfile/strings.form.purchase_date',
        'payment_due_date' => 'resources/bankProfile/strings.table.payment_due_date',
        'days_in_status' => self::LANG.'columns.days_in_status',
    ];

    public function __construct(private readonly FxRateService $fx) {}

    public function query(ReportFilters $filters): Builder
    {
        $query = $this->scopedFor($filters, $this->quickCard($filters))
            ->select('bank_profiles.*')
            ->selectRaw('DATEDIFF('.SqlToday::literal().', COALESCE('.BankProfileChecks::HISTORY_AT.', bank_profiles.updated_at, bank_profiles.created_at)) as days_in_status', [BankProfile::class, 'status_id'])
            ->selectRaw('('.BankProfileChecks::HISTORY_AT.') is null as without_history', [BankProfile::class, 'status_id'])
            ->with(['registeredOrder', 'bank', 'company', 'requestedCurrency', 'purchasedCurrency', 'status', 'targetable']);

        return $this->applyQuick($query, $filters);
    }

    public function summary(ReportFilters $filters): array
    {
        return [
            $this->amountCard('queued', $this->amounts($filters, 'queued')),
            $this->amountCard('allocated', $this->amounts($filters, 'allocated')),
            $this->amountCard('purchased_month', $this->amounts($filters, 'purchased_month'), $this->hasRange($filters) ? 'purchased_period' : 'purchased_month', 'purchased_currency_note'),
            $this->timingAndPaymentsCard($filters),
        ];
    }

    private function timingAndPaymentsCard(ReportFilters $filters): array
    {
        $scoped = fn (): Builder => BankProfileChecks::scoped(BankProfile::query(), $filters);
        $lines = [
            ...$this->averageLines($filters),
            ['label' => __(self::LANG.'lines.payment_due_soon', ['days' => $filters->threshold('due_soon')]), 'value' => (string) BankProfileChecks::dueSoon($scoped(), $filters)->count(), 'quick' => 'payment_due_soon'],
            ['label' => __(self::LANG.'lines.payment_overdue'), 'value' => (string) BankProfileChecks::overdue($scoped())->count(), 'quick' => 'payment_overdue'],
        ];

        if (! BankProfileChecks::showsFinished($filters)) {
            $lines[] = ['label' => __(self::LANG.'lines.finished_hidden'), 'value' => (string) $scoped()->whereIn('bank_profiles.status_id', BankProfileChecks::finishedIds())->count()];
        }

        return ['key' => 'timing_payments', 'label' => __(self::LANG.'cards.timing_payments'), 'lines' => $lines, 'tone' => null];
    }

    public function rows(ReportFilters $filters): iterable
    {
        return $this->query($filters)->orderBy('bank_profiles.id')->lazy()->map(fn (BankProfile $profile): array => $this->row($profile));
    }

    public function exportLabels(): array
    {
        return array_map(fn (string $key): string => __($key), self::EXPORT_COLUMNS);
    }

    /**
     * @return array{labels: array<int, string>, counts: array<int, int>}
     */
    public function chartFunnel(ReportFilters $filters): array
    {
        $counts = BankProfileChecks::scoped(BankProfile::query(), $filters)
            ->selectRaw('status_id, count(*) as total')
            ->groupBy('status_id')
            ->pluck('total', 'status_id');
        $stages = app(ReportStages::class)->ordered(BankProfile::TYPE_BANK_PROFILE);

        return [
            'labels' => $stages->map(fn ($status): string => (string) $status->getLocalizedNameAttribute())->all(),
            'counts' => $stages->map(fn ($status): int => (int) ($counts[$status->id] ?? 0))->all(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, float>}>}
     */
    public function chartByBank(ReportFilters $filters): array
    {
        $rows = BankProfileChecks::scoped(BankProfile::query(), $filters)
            ->whereIn('status_id', BankProfileChecks::statusIds(self::QUEUED))
            ->selectRaw('bank_id, requested_currency_id as currency_id, sum(requested_amount) as amount')
            ->groupBy('bank_id', 'requested_currency_id')
            ->get();
        $banks = $rows->pluck('bank_id')->unique()->values();
        $names = $this->currencyNames($rows->pluck('currency_id'));
        $bankNames = Bank::withTrashed()->whereIn('id', $banks->filter())->get()->mapWithKeys(fn (Bank $bank): array => [$bank->id => (string) $bank->getLocalizedNameAttribute()]);

        return [
            'labels' => $banks->map(fn ($id): string => $bankNames[$id] ?? '-')->all(),
            'datasets' => $rows->groupBy('currency_id')->map(fn (Collection $group, $currencyId): array => [
                'label' => $names[$currencyId] ?? '-',
                'data' => $banks->map(fn ($id): float => (float) $group->firstWhere('bank_id', $id)?->amount)->all(),
            ])->values()->all(),
        ];
    }

    private function row(BankProfile $profile): array
    {
        return [
            'bp_number' => $profile->bp_number,
            'registered_order' => $profile->registeredOrder?->ro_number,
            'bank' => $profile->bank?->getLocalizedNameAttribute(),
            'company' => $profile->company?->getLocalizedNameAttribute(),
            'targetable' => $this->targetable($profile),
            'supply_source' => $profile->supply_source ? __('resources/bankProfile/strings.general.supply_sources.'.$profile->supply_source) : null,
            'status' => $profile->status?->getLocalizedNameAttribute(),
            'requested_currency' => $profile->requestedCurrency?->getLocalizedNameAttribute(),
            'requested_amount' => $profile->requested_amount,
            'purchased_currency' => $profile->purchasedCurrency?->getLocalizedNameAttribute(),
            'purchased_equivalent' => $profile->purchased_equivalent,
            'final_rate' => $profile->final_rate,
            'commission_rate' => $profile->commission_rate,
            'creation_date' => $this->date($profile->creation_date),
            'allocation_date' => $this->date($profile->allocation_date),
            'purchase_date' => $this->date($profile->purchase_date),
            'payment_due_date' => $this->date($profile->payment_due_date),
            'days_in_status' => $profile->days_in_status,
        ];
    }

    private function date(mixed $date): ?string
    {
        return $date ? adaptiveDate($date) : null;
    }

    private function targetable(BankProfile $profile): ?string
    {
        if (! $profile->targetable) {
            return null;
        }

        return $profile->targetable_type === Product::class
            ? ($profile->targetable->customized_label ?? $profile->targetable->getLocalizedNameAttribute())
            : $profile->targetable->getLocalizedNameAttribute();
    }

    private function hasRange(ReportFilters $filters): bool
    {
        return $filters->dateFrom !== null || $filters->dateUntil !== null;
    }

    private function cardSet(Builder $base, string $card, ReportFilters $filters): Builder
    {
        $statuses = match ($card) {
            'queued' => self::QUEUED,
            'allocated' => [BankProfileStatus::Allocated],
            default => [BankProfileStatus::Purchased, BankProfileStatus::Received],
        };
        $set = $base->whereIn('bank_profiles.status_id', BankProfileChecks::statusIds($statuses));

        return $card === 'purchased_month' && ! $this->hasRange($filters)
            ? $set->whereBetween('bank_profiles.purchase_date', $this->monthRange())
            : $set;
    }

    private function scopedFor(ReportFilters $filters, ?string $card): Builder
    {
        $forced = $card === 'purchased_month' && $this->hasRange($filters)
            ? ReportFilters::fromArray([...$filters->toArray(), 'date_basis' => 'purchase_date', 'include_undated' => false])
            : $filters;

        return BankProfileChecks::scoped(BankProfile::query(), $forced);
    }

    private function quickCard(ReportFilters $filters): ?string
    {
        return $filters->quick !== null && preg_match(self::CARD_QUICK, $filters->quick, $match) ? $match[1] : null;
    }

    private function amountColumns(string $card): array
    {
        return $card === 'purchased_month' ? ['purchased_equivalent', 'purchased_currency_id'] : ['requested_amount', 'requested_currency_id'];
    }

    private function amounts(ReportFilters $filters, string $card): Collection
    {
        [$amount, $currency] = $this->amountColumns($card);

        return $this->cardSet($this->scopedFor($filters, $card), $card, $filters)
            ->selectRaw("bank_profiles.{$currency} as currency_id, count(*) as total, sum(bank_profiles.{$amount}) as amount")
            ->groupBy("bank_profiles.{$currency}")
            ->get();
    }

    private function applyQuick(Builder $query, ReportFilters $filters): Builder
    {
        $quick = $filters->quick;

        if ($quick === 'payment_due_soon') {
            return BankProfileChecks::dueSoon($query, $filters);
        }

        if ($quick === 'payment_overdue') {
            return BankProfileChecks::overdue($query);
        }

        if ($quick === null || ! preg_match(self::CARD_QUICK, $quick, $match)) {
            return BankProfileChecks::showsFinished($filters) ? $query : BankProfileChecks::notFinished($query);
        }

        $currency = 'bank_profiles.'.$this->amountColumns($match[1])[1];

        return $this->cardSet($query, $match[1], $filters)
            ->when(isset($match[2]), fn (Builder $scoped) => (int) $match[2] === 0
                ? $scoped->whereNull($currency)
                : $scoped->where($currency, (int) $match[2]));
    }

    private function monthRange(): array
    {
        $range = CalendarRange::forMonth(today()->toDateString(), isJalaliCalendar());

        return [$range->start->toDateString(), $range->end->toDateString()];
    }

    private function amountCard(string $key, Collection $rows, ?string $label = null, string $note = 'requested_currency_note'): array
    {
        $names = $this->currencyNames($rows->pluck('currency_id'));
        $lines = [['label' => __(self::LANG.'lines.count'), 'value' => (string) $rows->sum('total'), 'quick' => $key]];

        foreach ($rows as $row) {
            $lines[] = ['label' => $names[$row->currency_id] ?? '-', 'value' => delimiter($row->amount), 'quick' => $key.'_currency_'.(int) $row->currency_id];
        }

        return ['key' => $key, 'label' => __(self::LANG.'cards.'.($label ?? $key)), 'lines' => [...$lines, ...$this->euroLines($rows, $names), ['label' => __(self::LANG.'lines.'.$note), 'value' => '-']], 'tone' => null];
    }

    private function euroLines(Collection $rows, array $names): array
    {
        $converted = $rows->mapWithKeys(fn ($row): array => [$row->currency_id => $this->fx->toEur($row->amount, $row->currency_id)]);
        $excluded = $rows->filter(fn ($row): bool => $converted[$row->currency_id] === null && (float) $row->amount > 0)
            ->map(fn ($row): string => ($names[$row->currency_id] ?? '-').' '.delimiter($row->amount));
        $lines = [['label' => __(self::LANG.'lines.eur_total'), 'value' => delimiter($converted->filter()->sum())]];

        return $excluded->isEmpty() ? $lines : [...$lines, ['label' => __(self::LANG.'lines.excluded'), 'value' => $excluded->implode(', ')]];
    }

    private function averageLines(ReportFilters $filters): array
    {
        $average = BankProfileChecks::scoped(BankProfile::query(), $filters)
            ->selectRaw('AVG(DATEDIFF(allocation_date, creation_date)) as to_allocation, AVG(DATEDIFF(purchase_date, allocation_date)) as to_purchase')
            ->first();

        return [
            ['label' => __(self::LANG.'lines.creation_to_allocation'), 'value' => $this->days($average?->to_allocation)],
            ['label' => __(self::LANG.'lines.allocation_to_purchase'), 'value' => $this->days($average?->to_purchase)],
        ];
    }

    private function days(mixed $value): string
    {
        return $value === null ? '-' : (string) round((float) $value, 1);
    }

    private function currencyNames(Collection $ids): array
    {
        return Currency::withTrashed()->whereIn('id', $ids->filter()->unique())->get()
            ->mapWithKeys(fn (Currency $currency): array => [$currency->id => (string) $currency->getLocalizedNameAttribute()])
            ->all();
    }
}
