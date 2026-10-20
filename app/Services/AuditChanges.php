<?php

namespace App\Services;

use App\Models\BankProfile;
use App\Models\Custom;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Services\Calendar\CalendarPathResolver;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class AuditChanges
{
    public const LOG_NAME = PurchaseRequest::AUDIT_LOG;

    public const MODELS = [
        PurchaseRequest::class,
        RegisteredOrder::class,
        PurchaseOrder::class,
        Payment::class,
        Shipment::class,
        Custom::class,
        BankProfile::class,
        ProformaInvoice::class,
    ];

    private const HIDDEN = ['deleted_at'];

    private static array $summaries = [];

    private const ISO_UTC = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/';

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array<int, array<int, array{label: string, old: string, new: string}>>
     */
    public function fields(Collection $activities): array
    {
        $labels = $this->foreignLabels($activities);
        $resolver = app(CalendarPathResolver::class);
        $result = [];

        foreach ($activities as $activity) {
            $result[$activity->id] = array_map(fn (string $column): array => [
                'label' => $resolver->columnLabel($activity->subject_type, $column),
                'old' => $this->format($activity, $column, $this->old($activity)[$column] ?? null, $labels),
                'new' => $this->format($activity, $column, $this->new($activity)[$column] ?? null, $labels),
            ], $this->columns($activity));
        }

        return $result;
    }

    /**
     * @return array<int, class-string<Model>>
     */
    public function viewableModels(): array
    {
        $user = auth()->user();

        return array_values(array_filter(
            self::MODELS,
            fn (string $class): bool => $user?->can(Str::snake(class_basename($class)).'.view') ?? false
        ));
    }

    public function summary(Activity $activity): string
    {
        if (! $activity->exists) {
            return $this->buildSummary($activity);
        }

        return self::$summaries[$activity->id.':'.app()->getLocale()] ??= $this->buildSummary($activity);
    }

    private function buildSummary(Activity $activity): string
    {
        $resolver = app(CalendarPathResolver::class);
        $labels = array_map(fn (string $column): string => $resolver->columnLabel($activity->subject_type, $column), $this->columns($activity));

        return implode(', ', array_slice($labels, 0, 3)).(count($labels) > 3 ? ' +'.(count($labels) - 3) : '');
    }

    public function record(Activity $activity): string
    {
        $identifier = $this->isAudited($activity->subject_type) ? $activity->subject?->{$activity->subject_type::SCANNABLE_IDENTIFIER} : null;

        return filled($identifier) ? Str::limit((string) $identifier, 40) : '#'.$activity->subject_id;
    }

    public function url(Activity $activity): ?string
    {
        $subject = $activity->subject;
        $route = collect(config('workspace.resources'))->pluck('route', 'model')->get($activity->subject_type);

        if (! $subject || $subject->trashed() || ! $route || ! $this->isAudited($activity->subject_type)) {
            return null;
        }

        $resource = Filament::getModelResource($activity->subject_type);

        return $resource && $resource::canEdit($subject) ? route($route, ['record' => $subject->getKey()]) : null;
    }

    private function isAudited(?string $class): bool
    {
        return in_array($class, self::MODELS, true);
    }

    /**
     * @return array<int, string>
     */
    private function columns(Activity $activity): array
    {
        return array_values(array_diff(array_keys($this->new($activity) + $this->old($activity)), self::HIDDEN));
    }

    private function new(Activity $activity): array
    {
        return (array) $activity->properties->get('attributes', []);
    }

    private function old(Activity $activity): array
    {
        return (array) $activity->properties->get('old', []);
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array<string, array<int|string, string>>
     */
    private function foreignLabels(Collection $activities): array
    {
        $ids = [];

        foreach ($activities as $activity) {
            foreach ($this->columns($activity) as $column) {
                if (str_ends_with($column, '_id')) {
                    $found = array_filter([$this->old($activity)[$column] ?? null, $this->new($activity)[$column] ?? null], 'is_scalar');
                    $ids[$activity->subject_type][$column] = array_merge($ids[$activity->subject_type][$column] ?? [], $found);
                }
            }
        }

        $labels = [];

        foreach ($ids as $class => $columns) {
            foreach ($columns as $column => $values) {
                $related = $this->isAudited($class) ? $this->relatedModel($class, $column) : null;

                $labels[$class.':'.$column] = $related ? NameSearch::labels($related, array_values(array_unique($values))) : [];
            }
        }

        return $labels;
    }

    private function relatedModel(string $class, string $column): ?Model
    {
        foreach (app(CalendarPathResolver::class)->relations($class) as $meta) {
            if ($meta['foreign_key'] === $column) {
                return new $meta['related'];
            }
        }

        return null;
    }

    private function format(Activity $activity, string $column, mixed $value, array $labels): string
    {
        return match (true) {
            $value === null => '-',
            is_bool($value) => __('resources/general/strings.'.($value ? 'yes' : 'no')),
            is_array($value) => Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE), 200),
            isset($labels[$activity->subject_type.':'.$column][$value]) => $labels[$activity->subject_type.':'.$column][$value],
            is_string($value) && preg_match(self::ISO_UTC, $value) === 1 => $this->date($value),
            is_string($value) && str_contains($value, '\\') && is_subclass_of($value, Model::class) => PermissionLabeler::getEntityLabel($value),
            is_numeric($value) && $this->isDecimal($activity->subject_type, $column) => preciseNumber($value),
            default => Str::limit((string) $value, 200),
        };
    }

    private function isDecimal(?string $class, string $column): bool
    {
        return $this->isAudited($class) && str_starts_with((string) ((new $class)->getCasts()[$column] ?? ''), 'decimal');
    }

    private function date(string $value): string
    {
        $date = Carbon::parse($value)->timezone(config('app.timezone'));

        return adaptiveDate($date, ! $date->isStartOfDay());
    }
}
