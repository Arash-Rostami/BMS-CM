<?php

namespace App\Services\Reports\Builders;

use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Currency;
use App\Services\Calendar\Display\CalendarRange;
use App\Services\Reports\Checks\FxCommitmentChecks;
use App\Services\Reports\Rules\FxRateService;
use App\Services\Reports\Support\FxCommitmentLedger;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\SqlToday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FxCommitmentsReportService implements ReportService
{
    private const LANG = 'resources/report/strings.fx_commitments.';

    private const MONTHS = 6;

    public const EXPORT_COLUMNS = [
        'ro_number' => 'resources/bankProfile/strings.table.registered_order',
        'bp_number' => 'resources/bankProfile/strings.table.bp_number',
        'company' => 'resources/bankProfile/strings.table.company',
        'bank' => 'resources/bankProfile/strings.table.bank',
        'supply_source' => 'resources/bankProfile/strings.form.supply_source',
        'status' => 'resources/bankProfile/strings.table.status',
        'requested' => 'resources/bankProfile/strings.table.requested_amount',
        'purchased_currency' => 'resources/bankProfile/strings.form.purchased_currency',
        'purchased_equivalent' => 'resources/bankProfile/strings.form.purchased_equivalent',
        'eur_purchased' => self::LANG.'columns.eur_purchased',
        'documents_amount' => self::LANG.'columns.documents_amount',
        'resolved' => self::LANG.'columns.resolved',
        'remaining' => self::LANG.'columns.remaining',
        'eur_remaining' => self::LANG.'columns.eur_remaining',
        'resolved_pct' => self::LANG.'columns.resolved_pct',
        'commitment_payment_date' => self::LANG.'columns.commitment_due_date',
        'days_to_due' => self::LANG.'columns.days_to_due',
        'state' => self::LANG.'columns.state',
        'commitment_status' => 'resources/custom/strings.form.commitment_status',
        'guarantee_status' => 'resources/custom/strings.form.bank_guarantee_status',
        'ten_percent_exit_date' => 'resources/custom/strings.form.ten_percent_exit_date',
        'rial_return_date' => 'resources/custom/strings.form.rial_return_date',
        'flags' => self::LANG.'columns.flags',
    ];

    private const COLORS = ['#10B981', '#F59E0B', '#3B82F6', '#EF4444', '#8B5CF6', '#14B8A6'];

    private ?string $eurRate = null;

    public function __construct(private readonly FxRateService $fx, private readonly FxCommitmentLedger $ledger) {}

    public function query(ReportFilters $filters): Builder
    {
        $parts = $this->parts($filters);
        $name = app()->getLocale() === 'fa' ? 'name' : 'english_name';
        $status = fn (string $column): string => "(select statuses.{$name} from statuses where statuses.id = c.{$column})";

        $query = $this->joined(BankProfile::query(), $filters)
            ->select('bank_profiles.*')
            ->selectRaw('bank_profiles.purchased_currency_id as pcur')
            ->selectRaw('ro.ro_number as ro_number')
            ->selectRaw("{$parts['res']} as res_share")
            ->selectRaw("{$parts['rem']} as rem_share")
            ->selectRaw("{$parts['state']} as state")
            ->selectRaw("{$parts['resolved']} as ord_resolved")
            ->selectRaw('po.purchased as ord_purchased')
            ->selectRaw("{$parts['remaining']} as ord_remaining")
            ->selectRaw('coalesce(led.cleared, 0) as led_cleared')
            ->selectRaw('coalesce(led.used, 0) as led_used')
            ->selectRaw('coalesce(led.flags, 0) as led_flags')
            ->selectRaw('coalesce(po.ccount > 1 or po.nullcur > 0, 0) as is_mixed')
            ->selectRaw("DATEDIFF({$parts['date']}, ".SqlToday::literal().') as days_to_due')
            ->selectRaw($this->eurRateSql().' as eur_rate')
            ->selectRaw($status('commitment_status_id').' as lc_commitment')
            ->selectRaw($status('bank_guarantee_status_id').' as lc_guarantee')
            ->selectRaw('c.ten_percent_exit_date as lc_exit')
            ->selectRaw('c.rial_return_date as lc_rial')
            ->with(['company:id,name,english_name', 'bank:id,name,english_name', 'status', 'purchasedCurrency:id,name,english_name', 'requestedCurrency:id,name,english_name']);

        return $this->applyQuick($this->ledger->summary($filters)['oversize'] ? $query->whereRaw('0 = 1') : $query, $filters, $parts);
    }

    public function joined(Builder $base, ReportFilters $filters): Builder
    {
        $parts = $this->parts($filters);

        return FxCommitmentChecks::scoped($base, $filters)
            ->leftJoin('registered_orders as ro', fn ($join) => $join->on('ro.id', '=', 'bank_profiles.registered_order_id')->whereNull('ro.deleted_at'))
            ->leftJoinSub($this->purchasedTotals(), 'po', fn ($join) => $join->on('po.oid', '=', 'bank_profiles.registered_order_id'))
            ->leftJoin(DB::raw('('.$this->ledger->literalSql($filters).') as led'), 'led.oid', '=', 'bank_profiles.registered_order_id')
            ->leftJoinSub($this->latestCustoms(), 'lc', fn ($join) => $join->on('lc.oid', '=', 'bank_profiles.registered_order_id'))
            ->leftJoin('customs as c', 'c.id', '=', 'lc.cid')
            ->when($filters->commitmentStatusId, fn (Builder $q, int $id): Builder => $q->where('c.commitment_status_id', $id))
            ->when($filters->bankGuaranteeStatusId, fn (Builder $q, int $id): Builder => $q->where('c.bank_guarantee_status_id', $id))
            ->when($filters->commitmentState, fn (Builder $q, string $state): Builder => $q->whereRaw("{$parts['state']} = ?", [$state]));
    }

    public function profiles(Builder $base, ReportFilters $filters): Builder
    {
        return $this->joined($base, $filters)->select('bank_profiles.*');
    }

    /**
     * @return array<string, string>
     */
    public function predicates(ReportFilters $filters): array
    {
        return $this->parts($filters)['predicates'];
    }

    public function summary(ReportFilters $filters): array
    {
        $stats = $this->stats($filters);
        $names = $this->currencyNames($stats->pluck('pcur'));
        $ledger = $this->ledger->summary($filters);
        $eur = $filters->amountIn === 'eur';
        $notice = ['label' => __(self::LANG.'lines.unusable'), 'value' => $ledger['unusable'].' / '.$ledger['cleared']];

        if ($ledger['oversize']) {
            $over = [['label' => __(self::LANG.'lines.oversize'), 'value' => '-']];

            return [
                $this->card('purchased', array_map(fn (array $line): array => array_diff_key($line, ['quick' => 0]), [
                    $this->line(__(self::LANG.'lines.profiles'), $stats->sum('cnt'), 'profiles'),
                    ...$this->moneyLines($stats, 'pur', $names, $eur),
                    $this->line(__(self::LANG.'lines.not_computable'), $stats->sum('nc'), 'not_computable'),
                ])),
                $this->card('resolved', $over, 'warning'),
                $this->card('remaining', $over, 'warning'),
                $this->card('overdue', $over, 'warning'),
            ];
        }

        return [
            $this->card('purchased', [
                $this->line(__(self::LANG.'lines.profiles'), $stats->sum('cnt'), 'profiles'),
                ...$this->moneyLines($stats, 'pur', $names, $eur),
                $this->line(__(self::LANG.'lines.not_computable'), $stats->sum('nc'), 'not_computable'),
            ]),
            $this->card('resolved', [
                $this->line(__(self::LANG.'lines.resolved'), $stats->sum('resolved_cnt'), 'resolved'),
                $this->line(__(self::LANG.'lines.partial'), $stats->sum('partial_cnt'), 'partial'),
                ...$this->moneyLines($stats, 'resd', $names, $eur),
                $notice,
            ], $ledger['unusable'] > 0 ? 'warning' : null),
            $this->card('remaining', [
                $this->line(__(self::LANG.'lines.open_remaining'), $stats->sum('open_cnt'), 'open_remaining'),
                ...$this->moneyLines($stats, 'remn', $names, $eur),
                ...$this->moneyLines($stats, 'exc', $names, $eur, 'lines.excess'),
                $notice,
            ]),
            $this->card('overdue', [
                $this->line(__(self::LANG.'lines.overdue'), $stats->sum('overdue_cnt'), 'overdue'),
                $this->line(__(self::LANG.'lines.due_soon', ['days' => $filters->threshold('commit_due')]), $stats->sum('due_cnt'), 'due_soon'),
                $this->line(__(self::LANG.'lines.no_date'), $stats->sum('nodate_cnt'), 'no_date'),
                ...$this->moneyLines($stats, 'over_amt', $names, $eur, 'lines.overdue_amount'),
            ]),
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        return $this->query($filters)->orderBy('bank_profiles.id')->lazy()->map(function (BankProfile $profile): array {
            $row = [];

            foreach (array_keys(self::EXPORT_COLUMNS) as $key) {
                $row[$key] = $this->cell($profile, $key);
            }

            return $row;
        });
    }

    public function exportLabels(): array
    {
        return array_map(fn (string $key): string => __($key), self::EXPORT_COLUMNS);
    }

    public function cell(BankProfile $profile, string $key): mixed
    {
        return match ($key) {
            'ro_number' => $profile->ro_number,
            'bp_number' => $profile->bp_number,
            'company' => $profile->company?->getLocalizedNameAttribute(),
            'bank' => $profile->bank?->getLocalizedNameAttribute(),
            'supply_source' => $profile->supply_source ? __('resources/bankProfile/strings.general.supply_sources.'.$profile->supply_source) : null,
            'status' => $profile->status?->getLocalizedNameAttribute(),
            'requested' => $profile->requested_amount === null ? null : trim(delimiter($profile->requested_amount).' '.$profile->requestedCurrency?->getLocalizedNameAttribute()),
            'purchased_currency' => $profile->purchasedCurrency?->getLocalizedNameAttribute(),
            'purchased_equivalent' => $profile->purchased_equivalent,
            'eur_purchased' => $this->eurPurchased($profile),
            'documents_amount' => $profile->documents_amount,
            'resolved' => $profile->res_share,
            'remaining' => $profile->rem_share,
            'eur_remaining' => $this->eurRemaining($profile),
            'resolved_pct' => $this->percent($profile),
            'commitment_payment_date' => $this->date($profile->commitment_payment_date),
            'days_to_due' => $profile->days_to_due,
            'state' => __(self::LANG.'states.'.$profile->state),
            'commitment_status' => $profile->lc_commitment,
            'guarantee_status' => $profile->lc_guarantee,
            'ten_percent_exit_date' => $this->date($profile->lc_exit),
            'rial_return_date' => $this->date($profile->lc_rial),
            'flags' => $this->flagText($profile),
        };
    }

    public function currencyTotals(QueryBuilder $query, string $field): string
    {
        $rows = DB::query()
            ->fromSub($query, 'r')
            ->selectRaw("pcur, coalesce(sum({$field}), 0) as amount")
            ->groupBy('pcur')
            ->get()
            ->filter(fn (object $row): bool => $row->pcur !== null && (float) $row->amount != 0.0);
        $names = $this->currencyNames($rows->pluck('pcur'));

        return $rows->map(fn (object $row): string => ($names[$row->pcur] ?? '-').' '.delimiter($row->amount))->implode(' · ') ?: '-';
    }

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, float>}>}
     */
    public function chartByMonth(ReportFilters $filters): array
    {
        if ($this->ledger->summary($filters)['oversize']) {
            return ['labels' => [], 'datasets' => []];
        }

        $parts = $this->parts($filters);
        $jalali = isJalaliCalendar();
        $months = collect(range(0, self::MONTHS - 1))->map(fn (int $step): CalendarRange => CalendarRange::forMonth(CalendarRange::shiftMonths(today()->toDateString(), $step, $jalali), $jalali));
        $case = "case when {$parts['state']} = 'overdue' then 'overdue' when {$parts['date']} is null then 'nodate'"
            .$months->map(fn (CalendarRange $month, int $step): string => " when {$parts['date']} <= '{$month->end->toDateString()}' then 'm{$step}'")->implode('')
            .' else \'later\' end';
        $keys = ['overdue', ...$months->keys()->map(fn (int $step): string => "m{$step}")->all(), 'later', 'nodate'];
        $rows = $this->joined(BankProfile::query(), $filters)->toBase()
            ->selectRaw('bank_profiles.purchased_currency_id as pcur')
            ->selectRaw("{$case} as bucket")
            ->selectRaw("coalesce(sum({$parts['rem']}), 0) as amount")
            ->groupBy('pcur', 'bucket')
            ->get();

        return [
            'labels' => [__(self::LANG.'buckets.overdue'), ...$months->map(fn (CalendarRange $month): string => $month->label())->all(), __(self::LANG.'buckets.later'), __(self::LANG.'buckets.no_date')],
            'datasets' => $this->datasets($rows, 'bucket', $keys, $filters),
        ];
    }

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, float>}>}
     */
    public function chartByBank(ReportFilters $filters): array
    {
        if ($this->ledger->summary($filters)['oversize']) {
            return ['labels' => [], 'datasets' => []];
        }

        $parts = $this->parts($filters);
        $rows = $this->joined(BankProfile::query(), $filters)->toBase()
            ->selectRaw('bank_profiles.purchased_currency_id as pcur')
            ->selectRaw('coalesce(bank_profiles.bank_id, 0) as bank')
            ->selectRaw("coalesce(sum({$parts['rem']}), 0) as amount")
            ->groupBy('pcur', 'bank')
            ->get()
            ->filter(fn (object $row): bool => (float) $row->amount > 0);
        $banks = $rows->pluck('bank')->unique()->sort()->values()->map(fn ($id): int => (int) $id);
        $names = Bank::withTrashed()->whereIn('id', $banks->filter())->get()->mapWithKeys(fn (Bank $bank): array => [$bank->id => (string) $bank->getLocalizedNameAttribute()]);

        return [
            'labels' => $banks->map(fn (int $id): string => $id === 0 ? __(self::LANG.'buckets.no_bank') : ($names[$id] ?? '-'))->all(),
            'datasets' => $this->datasets($rows, 'bank', $banks->all(), $filters),
        ];
    }

    private function datasets(Collection $rows, string $axis, array $keys, ReportFilters $filters): array
    {
        $eur = $filters->amountIn === 'eur';
        $rows = $rows->filter(fn (object $row): bool => (float) $row->amount != 0.0);
        $names = $this->currencyNames($rows->pluck('pcur'));
        $value = fn (object $row): float => $eur
            ? (float) ($row->pcur === null ? 0 : $this->fx->toEur($row->amount, (int) $row->pcur))
            : (float) $row->amount;

        return $rows
            ->groupBy(fn (object $row): string => $eur ? __(self::LANG.'lines.eur_label') : (string) ($names[$row->pcur] ?? '-'))
            ->map(fn (Collection $group, string $label): array => [
                'label' => $label,
                'data' => collect($keys)->map(fn (string|int $key): float => round($group->filter(fn (object $row): bool => (string) $row->{$axis} === (string) $key)->sum($value), 2))->all(),
            ])
            ->values()
            ->map(fn (array $dataset, int $index): array => $dataset + ['backgroundColor' => self::COLORS[$index % count(self::COLORS)]])
            ->all();
    }

    private function stats(ReportFilters $filters): Collection
    {
        $parts = $this->parts($filters);
        $count = fn (string $condition): string => "coalesce(sum(case when {$condition} then 1 else 0 end), 0)";
        $sum = fn (string $expression, string $condition = '1 = 1'): string => "coalesce(sum(case when {$condition} then {$expression} else 0 end), 0)";
        $p = $parts['predicates'];

        return $this->joined(BankProfile::query(), $filters)->toBase()
            ->selectRaw('bank_profiles.purchased_currency_id as pcur')
            ->selectRaw('count(*) as cnt')
            ->selectRaw($count($p['not_computable']).' as nc')
            ->selectRaw($count($p['resolved']).' as resolved_cnt')
            ->selectRaw($count($p['partial']).' as partial_cnt')
            ->selectRaw($count($p['open_remaining']).' as open_cnt')
            ->selectRaw($count($p['overdue']).' as overdue_cnt')
            ->selectRaw($count($p['due_soon']).' as due_cnt')
            ->selectRaw($count($p['no_date']).' as nodate_cnt')
            ->selectRaw($sum('bank_profiles.purchased_equivalent', $parts['computable']).' as pur')
            ->selectRaw($sum($parts['res']).' as resd')
            ->selectRaw($sum($parts['rem']).' as remn')
            ->selectRaw($sum($parts['excess']).' as exc')
            ->selectRaw($sum($parts['rem'], $p['overdue']).' as over_amt')
            ->groupBy('bank_profiles.purchased_currency_id')
            ->get();
    }

    private function moneyLines(Collection $stats, string $field, array $names, bool $eur, ?string $title = null): array
    {
        $rows = $stats->filter(fn (object $row): bool => $row->pcur !== null && (float) $row->{$field} != 0.0);

        if ($title !== null && $rows->isEmpty()) {
            return [];
        }

        $heading = $title === null ? '' : __(self::LANG.$title).' · ';
        $total = 0.0;
        $excluded = [];

        foreach ($rows as $row) {
            $converted = $this->fx->toEur($row->{$field}, (int) $row->pcur);

            if ($converted === null) {
                $excluded[] = ($names[$row->pcur] ?? '-').' '.delimiter($row->{$field});
            } else {
                $total += $converted;
            }
        }

        $lines = $eur ? [] : $rows->map(fn (object $row): array => ['label' => $heading.($names[$row->pcur] ?? '-'), 'value' => delimiter($row->{$field})])->values()->all();
        $lines[] = ['label' => $title === null ? __(self::LANG.'lines.eur_total') : $heading.__(self::LANG.'lines.eur_label'), 'value' => delimiter($total)];

        return $excluded === [] ? $lines : [...$lines, ['label' => __(self::LANG.'lines.excluded'), 'value' => implode(', ', $excluded)]];
    }

    private function applyQuick(Builder $query, ReportFilters $filters, array $parts): Builder
    {
        $predicate = $filters->quick === null ? null : ($parts['predicates'][$filters->quick] ?? null);

        if ($predicate !== null) {
            return $query->whereRaw($predicate);
        }

        return $filters->commitmentState === null
            ? $query->whereRaw("{$parts['state']} in ('overdue', 'open')")
            : $query;
    }

    private function parts(ReportFilters $filters): array
    {
        $ids = FxCommitmentChecks::countedIds();
        $counted = $ids === [] ? '(0 = 1)' : 'bank_profiles.status_id in ('.implode(',', $ids).')';
        $computable = "(ro.id is not null and {$counted} and po.purchased > 0 and bank_profiles.purchased_equivalent > 0 and bank_profiles.purchased_currency_id is not null and po.ccount = 1 and po.nullcur = 0)";
        $resolved = 'coalesce(led.resolved, 0)';
        $remaining = "greatest(po.purchased - {$resolved}, 0)";
        $share = 'bank_profiles.purchased_equivalent / po.purchased';
        $date = 'bank_profiles.commitment_payment_date';
        $today = SqlToday::literal();
        $cutoff = "date_sub({$today}, interval ".$filters->threshold('late_grace').' day)';
        $limit = "date_add({$today}, interval ".$filters->threshold('commit_due').' day)';
        $state = "(case when {$computable} then (case when {$remaining} = 0 then 'resolved' when {$date} < {$cutoff} then 'overdue' else 'open' end) else 'not_computable' end)";

        return [
            'computable' => $computable,
            'resolved' => $resolved,
            'remaining' => $remaining,
            'res' => "(case when {$computable} then {$resolved} * {$share} end)",
            'rem' => "(case when {$computable} then {$remaining} * {$share} end)",
            'excess' => "(case when {$computable} then greatest({$resolved} - po.purchased, 0) * {$share} end)",
            'date' => $date,
            'state' => $state,
            'predicates' => [
                'profiles' => '(1 = 1)',
                'not_computable' => "({$state} = 'not_computable')",
                'resolved' => "({$state} = 'resolved')",
                'overdue' => "({$state} = 'overdue')",
                'open_remaining' => "({$computable} and {$remaining} > 0)",
                'partial' => "({$computable} and {$resolved} > 0 and {$remaining} > 0)",
                'due_soon' => "({$state} = 'open' and {$date} is not null and {$date} >= {$today} and {$date} <= {$limit})",
                'no_date' => "({$state} = 'open' and {$date} is null)",
            ],
        ];
    }

    private function purchasedTotals(): QueryBuilder
    {
        return DB::table('bank_profiles')
            ->whereNull('deleted_at')
            ->whereIn('status_id', FxCommitmentChecks::countedIds())
            ->groupBy('registered_order_id')
            ->selectRaw('registered_order_id as oid, sum(purchased_equivalent) as purchased, count(distinct purchased_currency_id) as ccount, sum(case when purchased_currency_id is null then 1 else 0 end) as nullcur');
    }

    private function latestCustoms(): QueryBuilder
    {
        return DB::table('customs')
            ->whereNull('deleted_at')
            ->groupBy('registered_order_id')
            ->selectRaw('registered_order_id as oid, max(id) as cid');
    }

    private function eurRateSql(): string
    {
        return $this->eurRate ??= $this->buildEurRateSql();
    }

    private function buildEurRateSql(): string
    {
        $whens = DB::table('bank_profiles')->whereNull('deleted_at')->whereNotNull('purchased_currency_id')->distinct()->pluck('purchased_currency_id')
            ->map(fn ($id): ?string => ($rate = $this->fx->toEur(1.0, (int) $id)) === null ? null : 'when '.(int) $id.' then '.number_format($rate, 10, '.', ''))
            ->filter();

        return $whens->isEmpty() ? 'null' : '(case bank_profiles.purchased_currency_id '.$whens->implode(' ').' end)';
    }

    private function eurPurchased(BankProfile $profile): ?float
    {
        return $profile->eur_rate === null || $profile->purchased_equivalent === null ? null : round((float) $profile->purchased_equivalent * (float) $profile->eur_rate, 5);
    }

    private function eurRemaining(BankProfile $profile): ?float
    {
        return $profile->eur_rate === null || $profile->rem_share === null ? null : round((float) $profile->rem_share * (float) $profile->eur_rate, 5);
    }

    private function percent(BankProfile $profile): ?float
    {
        return $profile->res_share === null || (float) $profile->purchased_equivalent <= 0 ? null : round((float) $profile->res_share / (float) $profile->purchased_equivalent * 100, 2);
    }

    private function date(mixed $date): ?string
    {
        return $date ? adaptiveDate($date instanceof Carbon ? $date : Carbon::parse($date)) : null;
    }

    private function flagText(BankProfile $profile): ?string
    {
        $labels = collect(FxCommitmentLedger::FLAG_KEYS)
            ->filter(fn (string $key, int $bit): bool => ((int) $profile->led_flags & $bit) !== 0)
            ->map(fn (string $key): string => __(self::LANG.'flags.'.$key));

        if ($profile->is_mixed) {
            $labels->push(__(self::LANG.'flags.mixed'));
        }

        return $labels->isEmpty() ? null : $labels->implode(', ');
    }

    private function currencyNames(Collection $ids): array
    {
        return Currency::withTrashed()->whereIn('id', $ids->filter()->unique())->get()
            ->mapWithKeys(fn (Currency $currency): array => [$currency->id => (string) $currency->getLocalizedNameAttribute()])
            ->all();
    }

    private function card(string $key, array $lines, ?string $tone = null): array
    {
        return ['key' => $key, 'label' => __(self::LANG."cards.{$key}"), 'lines' => $lines, 'tone' => $tone];
    }

    private function line(string $label, mixed $value, string $quick): array
    {
        return ['label' => $label, 'value' => (string) (int) $value, 'quick' => $quick];
    }
}
