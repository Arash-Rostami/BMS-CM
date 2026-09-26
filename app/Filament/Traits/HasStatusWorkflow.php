<?php

namespace App\Filament\Traits;

use App\Models\Permission;
use App\Models\Status;
use App\Services\StatusWorkflow;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait HasStatusWorkflow
{
    public static function statusWorkflowColumns(): array
    {
        return ['status_id'];
    }

    public static function getStatusWorkflowField(string $column, ?Closure $extra = null, ?string $type = null, ?Closure $fallbackDefault = null): Select
    {
        $relation = static::statusWorkflowRelation($column);
        $type ??= static::statusWorkflowType();
        $hasWorkflow = (bool) StatusWorkflow::initialFor($type);

        $field = Select::make($column)
            ->relationship(
                name: $relation,
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn (Builder $query, ?Model $record) => $query->whereIn('id', static::availableStatusWorkflowIds($column, $record, $type)),
            )
            ->default(fn ($operation): ?int => match (true) {
                $operation !== 'create' => null,
                $hasWorkflow => StatusWorkflow::initialFor($type)?->id,
                (bool) $fallbackDefault => $fallbackDefault(),
                default => null,
            })
            ->disabled(fn ($operation): bool => $operation === 'create' && $hasWorkflow)
            ->live()
            ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->getLocalizedNameAttribute() ?? '--')
            ->disableOptionWhen(fn ($value, ?Model $record): bool => ! ($target = Status::find($value))
                || ! StatusWorkflow::canSet(auth()->user(), $target, $record?->{$relation}))
            ->helperText(fn (?Model $record) => static::statusWorkflowLockedHelperText($column, $record))
            ->required();

        return $extra ? $extra($field) : $field;
    }

    public static function getStatusWorkflowPipelineAction(): Action
    {
        return Action::make('statusWorkflowPipeline')
            ->label(__('resources/general/strings.status_workflow.pipeline_label'))
            ->icon('heroicon-o-information-circle')
            ->color('gray')
            ->modalHeading(__('resources/general/strings.status_workflow.pipeline_label'))
            ->modalContent(fn (?Model $record) => view('filament.status-workflow.pipeline', [
                'pipeline' => static::statusWorkflowPipelineData($record),
            ]))
            ->modalSubmitAction(false)
            ->modalWidth('xl');
    }

    public static function getStatusWorkflowProgressColumn(string $column = 'status_id', ?string $type = null, bool $toggledHiddenByDefault = false): TextColumn
    {
        $relation = static::statusWorkflowRelation($column);
        $type ??= static::statusWorkflowType();
        $toggledHiddenByDefault = $toggledHiddenByDefault || ! StatusWorkflow::initialFor($type);

        return TextColumn::make($column.'_progress')
            ->label(__('resources/general/strings.status_workflow.progress_label'))
            ->state(fn (Model $record) => static::statusWorkflowProgress($record, $relation, $type)['percent'])
            ->formatStateUsing(fn (?int $state): string => is_null($state) ? '—' : "{$state}%")
            ->badge()
            ->color(fn (?int $state): string => match (true) {
                is_null($state) => 'gray',
                $state >= 100 => 'success',
                $state <= 0 => 'gray',
                default => 'warning',
            })
            ->tooltip(fn (Model $record): ?string => static::statusWorkflowProgress($record, $relation, $type)['fraction'])
            ->alignEnd()
            ->toggleable(isToggledHiddenByDefault: $toggledHiddenByDefault);
    }

    /**
     * @return array{percent: ?int, fraction: ?string}
     */
    protected static function statusWorkflowProgress(Model $record, string $relation, string $type): array
    {
        $ordered = StatusWorkflow::orderedStatuses($type);

        if ($ordered->isEmpty()) {
            return ['percent' => null, 'fraction' => null];
        }

        $current = $record->{$relation};

        if (! $current || is_null($current->stage_order)) {
            return ['percent' => null, 'fraction' => null];
        }

        $position = $ordered->search(fn (Status $status) => $status->is($current));

        if ($position === false) {
            return ['percent' => null, 'fraction' => null];
        }

        $completed = $position + 1;
        $total = $ordered->count();

        return [
            'percent' => (int) round(($completed / $total) * 100),
            'fraction' => "{$completed}/{$total}",
        ];
    }

    public static function applyInitialStatusOnCreate(array $data, string $column = 'status_id', ?string $type = null): array
    {
        if ($initial = StatusWorkflow::initialFor($type ?? static::statusWorkflowType())) {
            $data[$column] = $initial->id;
        }

        return $data;
    }

    public static function assertStatusTransitionAllowed(?Model $record, string $column, $newStatusId): void
    {
        $target = Status::find($newStatusId);

        if (! $target) {
            return;
        }

        $relation = static::statusWorkflowRelation($column);

        StatusWorkflow::assertAllowed(auth()->user(), $target, $record?->{$relation}, $column);
    }

    protected static function statusWorkflowRelation(string $column): string
    {
        return Str::camel(Str::beforeLast($column, '_id'));
    }

    protected static function availableStatusWorkflowIds(string $column, ?Model $record, ?string $type = null): array
    {
        $relation = static::statusWorkflowRelation($column);
        $type ??= static::statusWorkflowType();
        $current = $record?->{$relation};

        $ids = collect();

        if ($current) {
            $ids->push($current->id);
            $ids->push(StatusWorkflow::nextFor($current)?->id);
        } else {
            $ids->push(StatusWorkflow::initialFor($type)?->id);
        }

        return $ids->merge(static::statusWorkflowStatuses($type)->whereNull('stage_order')->pluck('id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected static function statusWorkflowStatuses(string $type): Collection
    {
        return Status::where('english_type', $type)
            ->get(['id', 'name', 'english_name', 'stage_order', 'approval_permission']);
    }

    protected static function statusWorkflowPipelineData(?Model $record): array
    {
        $relation = static::statusWorkflowRelation(static::statusWorkflowColumns()[0]);
        $current = $record?->{$relation};
        $statuses = static::statusWorkflowStatuses(static::statusWorkflowType());
        $ordered = $statuses->whereNotNull('stage_order')->sortBy('stage_order');
        $firstOrder = $ordered->first()?->stage_order;

        return [
            'ordered' => $ordered->map(fn (Status $status) => [
                'icon' => static::statusWorkflowStageIcon($status, $current, $firstOrder),
                'order' => $status->stage_order,
                'name' => $status->getLocalizedNameAttribute(),
                'responsible' => static::statusWorkflowResponsible($status, $firstOrder),
            ])->values()->all(),
            'unordered' => $statuses->whereNull('stage_order')->map(fn (Status $status) => [
                'icon' => '🔓',
                'name' => $status->getLocalizedNameAttribute(),
                'available_text' => __('resources/general/strings.status_workflow.pipeline_available_anytime'),
            ])->values()->all(),
        ];
    }

    protected static function statusWorkflowStageIcon(Status $status, ?Status $current, ?int $firstOrder): string
    {
        if (! $current) {
            return $status->stage_order === $firstOrder ? '' : '🔒';
        }

        return match (true) {
            $status->stage_order <= $current->stage_order => '✅',
            default => '🔒',
        };
    }

    protected static function statusWorkflowResponsible(Status $status, ?int $firstOrder): string
    {
        if (! $status->approval_permission) {
            return $status->stage_order === $firstOrder
                ? __('resources/general/strings.status_workflow.pipeline_automatic')
                : __('resources/general/strings.status_workflow.pipeline_anyone');
        }

        $names = static::statusWorkflowApprovers($status);

        if ($names->isEmpty() || $names->count() > 3) {
            return __('resources/general/strings.status_workflow.pipeline_requires_generic');
        }

        return __('resources/general/strings.status_workflow.pipeline_requires', ['names' => $names->implode(', ')]);
    }

    protected static function statusWorkflowLockedHelperText(string $column, ?Model $record): ?string
    {
        $relation = static::statusWorkflowRelation($column);
        $current = $record?->{$relation};
        $next = $current ? StatusWorkflow::nextFor($current) : null;

        if (! $next || StatusWorkflow::canSet(auth()->user(), $next, $current)) {
            return null;
        }

        $names = static::statusWorkflowApprovers($next);

        if ($names->isEmpty() || $names->count() > 3) {
            return __('resources/general/strings.status_workflow.locked_helper', ['status' => $next->getLocalizedNameAttribute()]);
        }

        return __('resources/general/strings.status_workflow.locked_helper_with_names', [
            'status' => $next->getLocalizedNameAttribute(),
            'names' => $names->implode(', '),
        ]);
    }

    protected static function statusWorkflowApprovers(Status $status): Collection
    {
        if (! $status->approval_permission) {
            return collect();
        }

        return Permission::where('name', $status->approval_permission)->first()?->users->pluck('name') ?? collect();
    }
}
