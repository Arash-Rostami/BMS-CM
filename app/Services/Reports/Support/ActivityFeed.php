<?php

namespace App\Services\Reports\Support;

use App\Models\RegisteredOrder;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditChanges;
use App\Services\Calendar\CalendarPathResolver;
use App\Services\PermissionLabeler;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityFeed
{
    private const WINDOWS = ['24h' => 1, '7d' => 7, '30d' => 30];

    public function __construct(private readonly AuditChanges $audit, private readonly CalendarPathResolver $paths) {}

    public function query(ReportFilters $filters): QueryBuilder
    {
        $types = $this->types($filters);
        $since = now()->subDays(self::WINDOWS[$filters->activityWindow]);

        $audit = DB::table('activity_log')
            ->where('log_name', AuditChanges::LOG_NAME)
            ->whereIn('subject_type', $types)
            ->where('created_at', '>=', $since)
            ->when($filters->causerId, fn (QueryBuilder $query, int $id) => $query->where('causer_id', $id))
            ->selectRaw("'a' as kind, id, created_at as at, causer_id as actor_id, subject_type, subject_id");
        $history = DB::table('status_histories')
            ->whereIn('statusable_type', $types)
            ->where('created_at', '>=', $since)
            ->when($filters->causerId, fn (QueryBuilder $query, int $id) => $query->where('user_id', $id))
            ->selectRaw("'s' as kind, id, created_at as at, user_id as actor_id, statusable_type as subject_type, statusable_id as subject_id");

        return DB::query()->fromSub($audit->unionAll($history), 'feed')->orderByDesc('at')->orderByDesc('kind')->orderByDesc('id');
    }

    /**
     * @param  Collection<int, object>  $slice
     * @return array<int, array{key: string, at: string, by: string, file: string, module: string, change: string}>
     */
    public function rows(Collection $slice): array
    {
        $activities = Activity::query()->whereIn('id', $this->ids($slice, 'a'))->get()->keyBy('id');
        $histories = StatusHistory::query()->with(['fromStatus', 'status'])->whereIn('id', $this->ids($slice, 's'))->get()->keyBy('id');
        $changes = $this->audit->fields($activities);
        $users = User::withTrashed()->whereIn('id', $slice->pluck('actor_id')->filter()->unique())->pluck('name', 'id');
        $files = $this->files($slice);

        return $slice->map(fn (object $row): array => [
            'key' => $row->kind.$row->id,
            'at' => $row->at,
            'by' => $users->get($row->actor_id) ?? __('resources/report/strings.operations.feed.system'),
            'file' => $files[$row->subject_type.':'.$row->subject_id] ?? '-',
            'module' => PermissionLabeler::getEntityLabel($row->subject_type),
            'change' => $row->kind === 'a' ? $this->auditText($changes[$row->id] ?? []) : $this->historyText($histories->get($row->id)),
        ])->values()->all();
    }

    private function types(ReportFilters $filters): array
    {
        return array_values(array_filter(
            $this->audit->viewableModels(),
            fn (string $class): bool => $filters->module === null || Str::snake(class_basename($class)) === $filters->module
        ));
    }

    private function ids(Collection $slice, string $kind): array
    {
        return $slice->where('kind', $kind)->pluck('id')->all();
    }

    private function files(Collection $slice): array
    {
        $subjects = $slice->groupBy('subject_type')->map(fn (Collection $rows, string $type) => $type::withTrashed()->whereIn('id', $rows->pluck('subject_id')->unique())->get()->keyBy('id'));
        $numbers = RegisteredOrder::withTrashed()
            ->whereIn('id', $subjects->flatMap(fn (Collection $models, string $type) => $models->map(fn ($model) => $type === RegisteredOrder::class ? $model->id : $model->getAttribute('registered_order_id'))->filter())->unique())
            ->pluck('ro_number', 'id');
        $files = [];

        foreach ($subjects as $type => $models) {
            foreach ($models as $id => $model) {
                $files["{$type}:{$id}"] = $numbers->get($type === RegisteredOrder::class ? $id : $model->getAttribute('registered_order_id'));
            }
        }

        return $files;
    }

    private function auditText(array $fields): string
    {
        return collect($fields)->map(fn (array $field): string => "{$field['label']}: {$field['old']} → {$field['new']}")->implode('; ');
    }

    private function historyText(?StatusHistory $history): string
    {
        if (! $history) {
            return '-';
        }

        return $this->paths->columnLabel($history->statusable_type, $history->field).': '
            .($history->fromStatus?->localized_name ?? '-').' → '.($history->status?->localized_name ?? '-');
    }
}
