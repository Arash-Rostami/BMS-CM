<?php

namespace App\Services\Calendar\Sync;

use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\User;
use App\Services\Calendar\CalendarActivity;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\CalendarPathResolver;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Filament\QueryBuilder\Models\Scopes\QueryBuilderScope;
use Illuminate\Cache\Lock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CalendarEngine
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $filterProblemsCache = [];

    public function __construct(private CalendarPathResolver $resolver, private CalendarActivity $activity, private CalendarFilterTree $filterTree) {}

    /**
     * False when another run holds the rule lock — the job must retry, not drop the edit.
     */
    public function syncRule(CalendarRule $rule): bool
    {
        $lock = Cache::lock('calendar_rule_sync:'.$rule->id, 900);

        if (! $lock->get()) {
            return false;
        }

        try {
            $this->syncLocked($rule, $lock);

            return true;
        } finally {
            $lock->release();
        }
    }

    private function syncLocked(CalendarRule $rule, Lock $lock): void
    {
        if (! $this->ruleIsActive($rule)) {
            $this->deleteHits($rule, CalendarHit::query()->where('calendar_rule_id', $rule->id));

            return;
        }

        $problems = $this->filterProblems($rule);

        if ($problems !== []) {
            $this->purgeInvalid($rule, $problems);

            return;
        }

        $matchedIds = [];
        $scanStartedAt = now();
        $this->scanMatches($rule, $matchedIds, $lock);
        $this->sweepUnmatched($rule, $matchedIds, $scanStartedAt);
    }

    public function syncSubject(string $class, int $id): void
    {
        if (! isset(CalendarModules::all()[$class])) {
            return;
        }

        $rules = CalendarRule::query()->where('subject', $class)->where('is_active', true)->get();

        foreach ($rules as $rule) {
            $this->syncSubjectForRule($rule, $class, $id);
        }
    }

    private function syncSubjectForRule(CalendarRule $rule, string $class, int $id): void
    {
        if (! $this->ruleIsSyncable($rule)) {
            return;
        }

        $record = $this->findRecord($class, $id, $rule->date_path);
        $hit = CalendarHit::query()
            ->where('calendar_rule_id', $rule->id)
            ->where('subject_type', (new $class)->getMorphClass())
            ->where('subject_id', $id)
            ->first();

        if ($record === null) {
            $this->clearHit($rule, $hit);

            return;
        }

        $date = $this->eventDateFor($record, $rule->date_path, $rule->day_shift);

        if ($date === null || ! $this->matches($rule, $id)) {
            $this->clearHit($rule, $hit);

            return;
        }

        $this->storeHit($rule, $record, $date, $hit);
    }

    /**
     * Leaf problems found in the stored filter tree — an unknown leaf type,
     * operator or malformed OR block must never silently mean "match everything".
     * Memoized per persisted rule so a syncSubject fan-out validates each rule once.
     *
     * @return array<int, string>
     */
    public function filterProblems(CalendarRule $rule): array
    {
        if (! $rule->exists) {
            return $this->computeFilterProblems($rule);
        }

        return $this->filterProblemsCache[$rule->id.':'.$rule->updated_at?->timestamp] ??= $this->computeFilterProblems($rule);
    }

    /**
     * @return array<int, string>
     */
    private function computeFilterProblems(CalendarRule $rule): array
    {
        $problems = [];

        try {
            $constraints = collect($this->resolver->constraintsForRule($rule))
                ->keyBy(fn (Constraint $constraint): string => $constraint->getName());

            $this->filterTree->walkLeaves($rule->filters['rules'] ?? [], function (array $leaf) use ($constraints, &$problems): void {
                $this->leafProblems($leaf, $constraints, $problems);
            });
        } catch (ValidationException) {
            return ['malformed_filters'];
        }

        return $problems;
    }

    private function leafProblems(array $leaf, Collection $constraints, array &$problems): void
    {
        $type = $leaf['type'] ?? null;
        $constraint = is_string($type) ? $constraints->get($type) : null;

        if (! $constraint instanceof Constraint) {
            $problems[] = is_string($type) ? 'unknown_type:'.$type : 'missing_type';

            return;
        }

        $operator = $leaf['data'][$constraint::OPERATOR_SELECT_NAME] ?? null;

        if (! is_string($operator) || ! $this->operatorExists($constraint, $operator)) {
            $problems[] = 'invalid_operator:'.$type;
        }
    }

    /**
     * Matches for a not-yet-saved payload — same rules as syncRule, gated on the
     * caller's module access and hostile-payload-safe.
     *
     * @param  array<string, mixed>  $filters
     * @return array{count: int, items: array<int, array{label: string, date: string}>}
     *
     * @throws ValidationException
     */
    public function preview(User $user, string $subject, array $filters, array $extraPaths, string $datePath, int $shift): array
    {
        $transient = $this->validatedPreviewRule($user, $subject, $filters, $extraPaths, $datePath, $shift);
        $query = $this->constrainDateNotNull($this->ruleQuery($transient), $datePath);

        return ['count' => (clone $query)->count(), 'items' => $this->previewItems($query, $datePath, $shift)];
    }

    /**
     * @param  array<string, mixed>  $filters
     *
     * @throws ValidationException
     */
    private function validatedPreviewRule(User $user, string $subject, array $filters, array $extraPaths, string $datePath, int $shift): CalendarRule
    {
        if (! in_array($subject, CalendarModules::viewableBy($user), true)) {
            $this->filterTree->throwPreviewValidation('subject');
        }

        $this->validateFilterShape($filters['rules'] ?? null);

        $transient = new CalendarRule;
        $transient->forceFill([
            'subject' => $subject,
            'filters' => $filters,
            'extra_paths' => $extraPaths,
            'date_path' => $datePath,
            'day_shift' => $shift,
        ]);

        if ($this->filterProblems($transient) !== []) {
            $this->filterTree->throwPreviewValidation('filters');
        }

        $this->assertDatePath($user, $subject, $datePath);

        return $transient;
    }

    /**
     * @throws ValidationException
     */
    private function assertDatePath(User $user, string $subject, string $datePath): void
    {
        if (trim($datePath) === '') {
            $this->filterTree->throwPreviewValidation('date_path');
        }

        $this->resolver->validatePath($subject, $datePath);

        if (! isset($this->resolver->datePathOptions($subject, $user)[$datePath])) {
            $this->filterTree->throwPreviewValidation('date_path');
        }
    }

    /**
     * @return array<int, array{label: string, date: string}>
     */
    private function previewItems(Builder $query, string $datePath, int $shift): array
    {
        $items = [];

        foreach ($query->limit((int) config('calendar.preview_limit'))->get() as $record) {
            $date = $this->eventDateFor($record, $datePath, $shift);

            if ($date !== null) {
                $items[] = ['label' => Str::limit(CalendarModules::itemLabel($record), 255, ''), 'date' => $date->toDateString()];
            }
        }

        return $items;
    }

    public function eventDateFor(Model $record, string $datePath, int $shift): ?Carbon
    {
        $relationPath = $this->relationPathFor($datePath);

        if ($relationPath !== null) {
            foreach (explode('.', $relationPath) as $hop) {
                if (! $record instanceof Model) {
                    return null;
                }

                $record = $record->getRelationValue($hop);
            }
        }

        if (! $record instanceof Model) {
            return null;
        }

        $raw = $record->getAttribute($this->lastSegment($datePath));

        if (blank($raw)) {
            return null;
        }

        try {
            return ($raw instanceof Carbon ? $raw->copy() : Carbon::parse($raw))->startOfDay()->addDays($shift);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * Landing-page "Needs attention" rows: overdue Action-required hits first
     * (oldest overdue first), then due today, then due within the next 7 days
     * (soonest first) — ascending event_date already yields exactly that order.
     * Heads-up hits past their date are excluded; nothing is cached.
     *
     * @return Collection<int, CalendarHit>
     */
    public function attention(User $user, int $limit = 10): Collection
    {
        if (CalendarModules::viewableBy($user) === []) {
            return collect();
        }

        $today = today();

        return CalendarHit::query()
            ->visibleTo($user)
            ->notPastHeadsUp()
            ->with(['rule:id,name,color,type', 'subject'])
            ->where(fn (Builder $query): Builder => $query
                ->overdue()
                ->orWhereBetween('event_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()]))
            ->orderBy('event_date')
            ->limit($limit)
            ->get(['id', 'calendar_rule_id', 'subject_type', 'subject_id', 'label', 'event_date']);
    }

    private function ruleIsSyncable(CalendarRule $rule): bool
    {
        return $this->ruleIsActive($rule) && $this->filterProblems($rule) === [];
    }

    private function ruleIsActive(CalendarRule $rule): bool
    {
        return $rule->is_active
            && ! $rule->trashed()
            && isset(CalendarModules::all()[$rule->subject]);
    }

    /**
     * @param  array<int, string>  $problems
     */
    private function purgeInvalid(CalendarRule $rule, array $problems): void
    {
        $deleted = $this->deleteHits($rule, CalendarHit::query()->where('calendar_rule_id', $rule->id));

        if ($deleted > 0 || $this->problemsChanged($rule, $problems)) {
            $this->activity->log('rule_invalid', $rule, $rule, ['problems' => implode(', ', $problems)]);
        }
    }

    /**
     * @param  array<int, string>  $problems
     */
    private function problemsChanged(CalendarRule $rule, array $problems): bool
    {
        $last = DB::table(config('activitylog.table_name'))
            ->where('log_name', CalendarActivity::LOG_NAME)
            ->where('description', 'rule_invalid')
            ->where('subject_type', (new CalendarRule)->getMorphClass())
            ->where('subject_id', $rule->id)
            ->orderByDesc('id')
            ->first();

        $logged = $last === null ? null : (json_decode($last->properties, true)['problems'] ?? null);

        return $logged !== implode(', ', $problems);
    }

    private function operatorExists(Constraint $constraint, string $operator): bool
    {
        [$name] = $constraint->parseOperatorString($operator);

        return array_key_exists($name, $constraint->getOperators());
    }

    private function validateFilterShape(mixed $rules): void
    {
        if (! is_array($rules)) {
            $this->filterTree->throwPreviewValidation('filters');
        }

        foreach ($rules as $leaf) {
            if (! is_array($leaf) || ! is_array($leaf['data'] ?? [])) {
                $this->filterTree->throwPreviewValidation('filters');
            }

            if (($leaf['type'] ?? null) === RuleBuilder::OR_BLOCK_NAME) {
                $groups = $leaf['data'][RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] ?? null;

                if (! is_array($groups)) {
                    $this->filterTree->throwPreviewValidation('filters');
                }

                foreach ($groups as $group) {
                    $this->validateFilterShape(is_array($group) ? ($group['rules'] ?? null) : null);
                }

                continue;
            }

            if (! is_string($leaf['type'] ?? null)) {
                $this->filterTree->throwPreviewValidation('filters');
            }
        }
    }

    private function scanMatches(CalendarRule $rule, array &$matchedIds, Lock $lock): void
    {
        $this->ruleQuery($rule)->chunkById($this->chunkSize(), function (Collection $records) use ($rule, &$matchedIds, $lock): void {
            $lock->refresh();

            ['reset' => $reset, 'keep' => $keep, 'ids' => $ids] = $batch = $this->classifyChunk($rule, $records);

            DB::transaction(function () use ($rule, $batch, $reset, $keep): void {
                $this->upsertHits($reset, ['label', 'event_date', 'alerts_sent', 'overdue_count', 'synced_at', 'updated_at']);
                $this->upsertHits($keep, ['label', 'synced_at', 'updated_at']);
                $this->activity->logMany('matched', $rule, $batch['matched']);
                $this->activity->logMany('date_changed', $rule, $batch['changed']);
            });

            array_push($matchedIds, ...$ids);
        });
    }

    /**
     * @return array{reset: array<int, array<string, mixed>>, keep: array<int, array<string, mixed>>, matched: array<int, array<string, mixed>>, changed: array<int, array<string, mixed>>, ids: array<int, int>}
     */
    private function classifyChunk(CalendarRule $rule, Collection $records): array
    {
        $existing = DB::table('calendar_hits')
            ->where('calendar_rule_id', $rule->id)
            ->whereIn('subject_id', $records->pluck('id')->all())
            ->get()
            ->keyBy('subject_id');

        $batch = ['reset' => [], 'keep' => [], 'matched' => [], 'changed' => [], 'ids' => []];

        foreach ($records as $record) {
            $this->classifyRecord($rule, $record, $existing, $batch);
        }

        return $batch;
    }

    /**
     * @param  array<int, \stdClass>  $existing
     * @param  array{reset: array<int, array<string, mixed>>, keep: array<int, array<string, mixed>>, matched: array<int, array<string, mixed>>, changed: array<int, array<string, mixed>>, ids: array<int, int>}  $batch
     */
    private function classifyRecord(CalendarRule $rule, Model $record, Collection $existing, array &$batch): void
    {
        $date = $this->eventDateFor($record, $rule->date_path, $rule->day_shift);

        if ($date === null) {
            return;
        }

        $iso = $date->toDateString();
        $id = $record->getKey();
        $row = $this->hitRow($rule, $record, $iso);
        $batch['ids'][] = $id;

        $current = $existing[$id] ?? null;

        if ($current === null) {
            $batch['matched'][] = $this->transitionEntry($row, []);
            $batch['reset'][] = $row + ['alerts_sent' => '[]', 'overdue_count' => 0];
        } elseif ($current->event_date !== $iso) {
            $batch['changed'][] = $this->transitionEntry($row, ['old_date' => $current->event_date]);
            $batch['reset'][] = $row + ['alerts_sent' => '[]', 'overdue_count' => 0];
        } else {
            $batch['keep'][] = $row + ['alerts_sent' => $current->alerts_sent ?? '[]', 'overdue_count' => $current->overdue_count ?? 0];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function hitRow(CalendarRule $rule, Model $record, string $iso): array
    {
        return [
            'calendar_rule_id' => $rule->id,
            'subject_type' => $record->getMorphClass(),
            'subject_id' => $record->getKey(),
            'label' => Str::limit(CalendarModules::itemLabel($record), 255, ''),
            'event_date' => $iso,
            'synced_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{subject_type: string, subject_id: int, properties: array<string, mixed>}
     */
    private function transitionEntry(array $row, array $extra): array
    {
        return [
            'subject_type' => $row['subject_type'],
            'subject_id' => $row['subject_id'],
            'properties' => ['label' => $row['label'], 'event_date' => $row['event_date']] + $extra,
        ];
    }

    /**
     * @param  array<int, int>  $matchedIds
     */
    private function sweepUnmatched(CalendarRule $rule, array $matchedIds, Carbon $scanStartedAt): void
    {
        $matched = array_flip($matchedIds);

        CalendarHit::query()
            ->where('calendar_rule_id', $rule->id)
            ->where(fn (Builder $query): Builder => $query
                ->where('updated_at', '<', $scanStartedAt)
                ->orWhereNull('updated_at'))
            ->chunkById($this->chunkSize(), function (Collection $hits) use ($rule, $matched): void {
                $staleIds = $hits->filter(fn (CalendarHit $hit): bool => ! isset($matched[$hit->subject_id]))->pluck('id');

                if ($staleIds->isNotEmpty()) {
                    $this->deleteHits($rule, CalendarHit::query()->whereIn('id', $staleIds));
                }
            });
    }

    private function deleteHits(CalendarRule $rule, Builder $query): int
    {
        $deleted = 0;

        $query->chunkById($this->chunkSize(), function (Collection $hits) use ($rule, &$deleted): void {
            $deleted += $hits->count();
            $this->activity->logMany('cleared', $rule, $hits
                ->map(fn (CalendarHit $hit): array => [
                    'subject_type' => $hit->subject_type,
                    'subject_id' => $hit->subject_id,
                    'properties' => ['label' => $hit->label, 'event_date' => $hit->event_date->toDateString()],
                ])
                ->all());

            CalendarHit::query()->whereIn('id', $hits->pluck('id'))->delete();
        });

        return $deleted;
    }

    private function upsertHits(array $rows, array $update): void
    {
        if ($rows !== []) {
            CalendarHit::query()->upsert($rows, ['calendar_rule_id', 'subject_type', 'subject_id'], $update);
        }
    }

    private function findRecord(string $class, int $id, string $datePath): ?Model
    {
        $query = $class::query();
        $relationPath = $this->relationPathFor($datePath);

        if ($relationPath !== null) {
            $query->with($relationPath);
        }

        return $query->find($id);
    }

    private function storeHit(CalendarRule $rule, Model $record, Carbon $date, ?CalendarHit $hit): void
    {
        $iso = $date->toDateString();
        $label = Str::limit(CalendarModules::itemLabel($record), 255, '');

        if ($hit === null) {
            $row = $this->hitRow($rule, $record, $iso) + ['alerts_sent' => '[]', 'overdue_count' => 0];
            CalendarHit::query()->upsert([$row], ['calendar_rule_id', 'subject_type', 'subject_id'], ['label', 'event_date', 'synced_at', 'updated_at']);
            $this->activity->log('matched', $record, $rule, ['label' => $label, 'event_date' => $iso]);

            return;
        }

        if ($hit->event_date->toDateString() === $iso) {
            $hit->update(['label' => $label, 'synced_at' => now()]);

            return;
        }

        $this->activity->log('date_changed', $record, $rule, ['label' => $label, 'event_date' => $iso, 'old_date' => $hit->event_date->toDateString()]);
        $hit->update(['label' => $label, 'event_date' => $iso, 'alerts_sent' => [], 'overdue_count' => 0, 'synced_at' => now()]);
    }

    private function clearHit(CalendarRule $rule, ?CalendarHit $hit): void
    {
        if ($hit === null) {
            return;
        }

        $this->activity->logMany('cleared', $rule, [[
            'subject_type' => $hit->subject_type,
            'subject_id' => $hit->subject_id,
            'properties' => ['label' => $hit->label, 'event_date' => $hit->event_date->toDateString()],
        ]]);
        $hit->delete();
    }

    private function matches(CalendarRule $rule, int $id): bool
    {
        return $this->scopedQuery($rule)->whereKey($id)->exists();
    }

    private function scopedQuery(CalendarRule $rule): Builder
    {
        return ($rule->subject)::query()->tap(
            QueryBuilderScope::make($rule->filters['rules'] ?? [], $this->resolver->constraintsForRule($rule))
        );
    }

    private function ruleQuery(CalendarRule $rule): Builder
    {
        $query = $this->scopedQuery($rule);
        $relationPath = $this->relationPathFor($rule->date_path);

        return $relationPath === null ? $query : $query->with($relationPath);
    }

    private function constrainDateNotNull(Builder $query, string $datePath): Builder
    {
        $column = $this->lastSegment($datePath);
        $relationPath = $this->relationPathFor($datePath);

        return $relationPath === null
            ? $query->whereNotNull($column)
            : $query->whereHas($relationPath, fn (Builder $inner): Builder => $inner->whereNotNull($column));
    }

    private function relationPathFor(string $path): ?string
    {
        if (! str_contains($path, '.')) {
            return null;
        }

        $segments = explode('.', $path);
        array_pop($segments);

        return implode('.', $segments);
    }

    private function lastSegment(string $path): string
    {
        $segments = explode('.', $path);

        return $segments[count($segments) - 1];
    }

    private function chunkSize(): int
    {
        return (int) config('calendar.sync_chunk', 500);
    }
}
