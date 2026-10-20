<?php

namespace App\Services\Reports\Checks;

use App\Models\Correspondence;
use App\Models\StatusHistory;
use App\Services\AuditChanges;
use App\Services\Calendar\CalendarModules;
use App\Services\Reports\Builders\ExecutiveReportService;
use App\Services\Reports\Builders\FxCommitmentsReportService;
use App\Services\Reports\Support\FxCommitmentLedger;
use App\Services\Reports\Support\ReportFilters;
use App\Services\SmartCacheManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class CheckRegistry
{
    private const CACHE_MODEL = 'ReportCheck';

    private const TTL_MINUTES = 5;

    private const SEVERITIES = ['error', 'warning', 'info'];

    private const OPEN_TO_ALL = [Correspondence::class];

    public static function invalidate(): void
    {
        SmartCacheManager::invalidate(self::CACHE_MODEL);
        app()->forgetInstance(FxCommitmentLedger::class);
        app()->forgetInstance(ExecutiveReportService::class);
        app()->forgetInstance(FxCommitmentsReportService::class);
    }

    /**
     * @return Collection<int, Check>
     */
    public function all(): Collection
    {
        return collect([
            ...BankProfileChecks::all(),
            ...RegisteredOrderChecks::all(),
            ...ShipmentChecks::all(),
            ...CustomChecks::all(),
            ...CorrespondenceChecks::all(),
            ...InTransitChecks::all(),
            ...ExecutiveChecks::all(),
            ...RequesterChecks::all(),
            ...FxCommitmentChecks::all(),
        ]);
    }

    /**
     * @return Collection<int, Check>
     */
    public function forReport(string $report): Collection
    {
        return $this->all()
            ->filter(fn (Check $check) => in_array($report, $check->reports, true))
            ->filter(fn (Check $check) => $report === 'requester' || $this->permitted($check))
            ->values();
    }

    private function permitted(Check $check): bool
    {
        $user = auth()->user();

        return $user === null
            || in_array($check->model, self::OPEN_TO_ALL, true)
            || $user->can(Str::snake(class_basename($check->model)).'.view');
    }

    /**
     * @return Collection<int, Check>
     */
    public function forRecord(Model $record): Collection
    {
        return $this->all()
            ->filter(fn (Check $check) => $check->model === $record::class)
            ->filter(fn (Check $check) => ($check->query)($check->model::query()->whereKey($record->getKey()), new ReportFilters)->exists())
            ->values();
    }

    public function evaluate(Check $check, ReportFilters $filters): Collection
    {
        return SmartCacheManager::remember(self::CACHE_MODEL, $this->cacheKey('rows', $check, $filters), self::TTL_MINUTES, fn () => $this->rows($check, $filters));
    }

    /**
     * @return array{error: int, warning: int, info: int}
     */
    public function counts(string $report, ReportFilters $filters): array
    {
        $counts = array_fill_keys(self::SEVERITIES, 0);

        foreach ($this->forReport($report) as $check) {
            $counts[$check->severity] += SmartCacheManager::remember(self::CACHE_MODEL, $this->cacheKey('count', $check, $filters), self::TTL_MINUTES, fn () => $this->offenders($check, $filters)->count());
        }

        return $counts;
    }

    private function cacheKey(string $kind, Check $check, ReportFilters $filters): array
    {
        return ['kind' => $kind, 'check' => $check->key, 'filters' => $filters->cacheKey(), 'user' => auth()->id(), 'locale' => app()->getLocale(), 'jalali' => isJalaliCalendar()];
    }

    private function offenders(Check $check, ReportFilters $filters): Builder
    {
        return ($check->query)($check->model::query(), $filters);
    }

    private function rows(Check $check, ReportFilters $filters): Collection
    {
        $records = $this->offenders($check, $filters)->get();
        $changes = $this->latestChanges($check, $records->modelKeys());

        return $records->map(fn (Model $record) => $this->row($check, $record, $changes->get($record->getKey()), $filters))->values();
    }

    private function row(Check $check, Model $record, ?array $change, ReportFilters $filters): array
    {
        return [
            'id' => $record->getKey(),
            'label' => CalendarModules::itemLabel($record),
            'url' => $this->url($check, $record),
            'problem' => __($check->messageKey()),
            'offending' => ($check->offending)($record),
            'expected' => ($check->expected)($record, $filters),
            'severity' => $check->severity,
            'changed_by' => $change['by'] ?? null,
            'changed_at' => $change['at'] ?? null,
            'check' => $check->key,
        ];
    }

    private function url(Check $check, Model $record): ?string
    {
        return $check->fix['resource']::canEdit($record) ? CalendarModules::url($record) : null;
    }

    private function latestChanges(Check $check, array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return $this->tracksStatus($check)
            ? $this->latestStatusChanges($check, $ids)
            : $this->latestAuditChanges($check, $ids);
    }

    private function tracksStatus(Check $check): bool
    {
        return method_exists($check->model, 'statusHistoryColumns') && in_array($check->fix['field'], $check->model::statusHistoryColumns(), true);
    }

    private function latestStatusChanges(Check $check, array $ids): Collection
    {
        $latest = StatusHistory::query()
            ->toBase()
            ->selectRaw('max(id)')
            ->where('statusable_type', $check->model)
            ->where('field', $check->fix['field'])
            ->whereIn('statusable_id', $ids)
            ->groupBy('statusable_id');

        return StatusHistory::query()
            ->with('user:id,name')
            ->whereIn('id', $latest)
            ->get()
            ->mapWithKeys(fn (StatusHistory $history) => [$history->statusable_id => ['by' => $history->user?->name, 'at' => $history->created_at]]);
    }

    private function latestAuditChanges(Check $check, array $ids): Collection
    {
        $latest = Activity::query()
            ->toBase()
            ->selectRaw('max(id)')
            ->where('log_name', AuditChanges::LOG_NAME)
            ->where('subject_type', $check->model)
            ->whereIn('subject_id', $ids)
            ->groupBy('subject_id');

        return Activity::query()
            ->with('causer')
            ->whereIn('id', $latest)
            ->get()
            ->mapWithKeys(fn (Activity $activity) => [$activity->subject_id => ['by' => $activity->causer?->name, 'at' => $activity->created_at]]);
    }
}
