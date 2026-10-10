<?php

namespace App\Services\Calendar\Sync;

use App\Jobs\SyncCalendarRule;
use App\Jobs\SyncCalendarSubject;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Services\Calendar\CalendarPathResolver;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CalendarRouter
{
    private const RELATED_SUBJECT_CAP = 50;

    private const STRUCTURAL_EVENTS = ['created', 'deleted', 'restored', 'forceDeleted'];

    private const ROUTES_VERSION_KEY = 'calendar_routes_version';

    private static bool $suspended = false;

    /**
     * @var array<string, array{subject: bool, rules: array<int, string>, columns: array<int, string>}>|null
     */
    private static ?array $routes = null;

    private static ?string $routesVersion = null;

    public function __construct(private CalendarPathResolver $resolver, private CalendarFilterTree $filterTree) {}

    public static function suspended(Closure $callback): mixed
    {
        $previous = self::$suspended;
        self::$suspended = true;

        try {
            return $callback();
        } finally {
            self::$suspended = $previous;
        }
    }

    public static function isSuspended(): bool
    {
        return self::$suspended;
    }

    public static function flushRoutes(): void
    {
        self::$routes = null;
        self::$routesVersion = null;
        self::bumpRoutesVersion();
    }

    public static function bumpRoutesVersion(): void
    {
        Cache::forever(self::ROUTES_VERSION_KEY, (string) Str::uuid());
    }

    public function touch(Model $model, string $event): void
    {
        if (self::$suspended || $model instanceof CalendarRule || $model instanceof CalendarHit || $model instanceof Pivot) {
            return;
        }

        $entry = $this->routes()[$model::class] ?? null;

        if ($entry === null || ! $this->eventTouches($event, $model, $entry)) {
            return;
        }

        if ($entry['subject']) {
            SyncCalendarSubject::dispatch($model::class, $model->getKey())->delay(30);
        }

        foreach ($entry['rules'] as $ruleId => $subjectClass) {
            if ($subjectClass !== $model::class) {
                $this->routeRelated($ruleId, $model::class, $subjectClass, $model->getKey());
            }
        }
    }

    /**
     * Routing map {FQCN: [column, ...]} — the columns the filters reference, the
     * date column, each hop's foreign keys, and deleted_at along every path.
     *
     * @return array<string, array<int, string>>
     */
    public function computeWatchedColumns(CalendarRule $rule): array
    {
        if (! is_string($rule->subject) || ! class_exists($rule->subject)) {
            return [];
        }

        $watched = [];

        foreach ($this->rulePaths($rule) as $path) {
            $this->watchPath($watched, $rule->subject, $path);
        }

        return $watched;
    }

    private function eventTouches(string $event, Model $model, array $entry): bool
    {
        return in_array($event, self::STRUCTURAL_EVENTS, true) || $model->wasChanged($entry['columns']);
    }

    /**
     * @return array<string, array{subject: bool, rules: array<int, string>, columns: array<int, string>}>
     */
    private function routes(): array
    {
        $version = Cache::get(self::ROUTES_VERSION_KEY);

        if (self::$routes !== null && self::$routesVersion === $version) {
            return self::$routes;
        }

        self::$routesVersion = $version;

        return self::$routes = $this->buildRoutes();
    }

    private function buildRoutes(): array
    {
        $map = [];

        foreach (CalendarRule::query()->where('is_active', true)->get() as $rule) {
            foreach ($rule->watched_columns ?? [] as $class => $columns) {
                $map[$class] ??= ['subject' => false, 'rules' => [], 'columns' => []];
                $map[$class]['rules'][$rule->id] = $rule->subject;
                $map[$class]['columns'] = array_values(array_unique([...$map[$class]['columns'], ...$columns]));

                if ($rule->subject === $class) {
                    $map[$class]['subject'] = true;
                }
            }
        }

        return $map;
    }

    private function routeRelated(int $ruleId, string $touchedClass, string $subjectClass, int $id): void
    {
        $subjectIds = $this->subjectIdsTouching($ruleId, $touchedClass, $id);

        if ($subjectIds === null) {
            SyncCalendarRule::dispatch($ruleId)->delay(120);

            return;
        }

        foreach ($subjectIds as $subjectId) {
            SyncCalendarSubject::dispatch($subjectClass, $subjectId)->delay(30);
        }
    }

    /**
     * @return array<int, int>|null null when the reverse lookup is not expressible or over the cap (full rule resync)
     */
    private function subjectIdsTouching(int $ruleId, string $class, int $id): ?array
    {
        $rule = CalendarRule::query()->find($ruleId);

        if ($rule === null) {
            return [];
        }

        $ids = [];
        $expressible = false;

        foreach ($this->rulePaths($rule) as $path) {
            $relationPath = $this->pathThrough($rule->subject, $path, $class);

            if ($relationPath === null) {
                continue;
            }

            $expressible = true;
            $ids = [...$ids, ...($rule->subject)::query()
                ->whereHas($relationPath, fn (Builder $query): Builder => $query->withoutGlobalScopes()->whereKey($id))
                ->limit(self::RELATED_SUBJECT_CAP + 1)
                ->pluck('id')
                ->all()];
        }

        $ids = array_values(array_unique($ids));

        return $expressible && ! $this->overCap($ids) ? $ids : null;
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function overCap(array $ids): bool
    {
        return count($ids) > self::RELATED_SUBJECT_CAP;
    }

    private function pathThrough(string $subject, string $path, string $class): ?string
    {
        $relationPath = null;

        foreach ($this->pathHops($subject, $path) as $hop) {
            $relationPath = $relationPath === null ? $hop['relation'] : $relationPath.'.'.$hop['relation'];

            if ($hop['class'] === $class) {
                return $relationPath;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{relation: string, class: string}>
     */
    private function pathHops(string $subject, string $path): array
    {
        $hops = [];
        $class = $subject;

        foreach (explode('.', $path) as $segment) {
            $meta = $this->resolver->relations($class)[$segment] ?? null;

            if ($meta === null) {
                break;
            }

            $hops[] = ['relation' => $segment, 'class' => $meta['related']];
            $class = $meta['related'];
        }

        return $hops;
    }

    /**
     * @return array<int, string>
     */
    private function rulePaths(CalendarRule $rule): array
    {
        $paths = $this->leafPaths($rule);

        if ($rule->date_path) {
            $paths[$rule->date_path] = true;
        }

        return array_keys($paths);
    }

    /**
     * @return array<string, true>
     */
    private function leafPaths(CalendarRule $rule): array
    {
        $paths = [];

        try {
            $this->filterTree->walkLeaves($rule->filters['rules'] ?? [], function (array $leaf) use (&$paths): void {
                if (is_string($leaf['type'] ?? null)) {
                    $paths[$leaf['type']] = true;
                }
            });
        } catch (ValidationException) {
            return $paths;
        }

        return $paths;
    }

    private function watchPath(array &$watched, string $class, string $path): void
    {
        $this->watch($watched, $class, ['deleted_at']);
        $segments = explode('.', $path);

        foreach ($segments as $index => $segment) {
            $meta = $this->resolver->relations($class)[$segment] ?? null;

            if ($meta === null) {
                if ($index === count($segments) - 1 && isset($this->resolver->columns($class)[$segment])) {
                    $this->watch($watched, $class, [$segment]);
                }

                return;
            }

            if ($index === count($segments) - 1) {
                $this->watchTerminalRelation($watched, $class, $segment, $meta);

                return;
            }

            $this->watchHop($watched, $class, $segment, $meta);
            $class = $meta['related'];
            $this->watch($watched, $class, ['deleted_at']);
        }
    }

    /**
     * @param  array{related: string, single: bool, foreign_key: ?string}  $meta
     */
    private function watchTerminalRelation(array &$watched, string $class, string $relation, array $meta): void
    {
        if ($meta['foreign_key'] !== null) {
            $this->watch($watched, $class, [$meta['foreign_key']]);
        } else {
            $instance = (new $class)->{$relation}();

            if ($instance instanceof HasOneOrMany) {
                $this->watch($watched, $meta['related'], [$instance->getForeignKeyName()]);
            }
        }

        $this->watch($watched, $meta['related'], ['deleted_at']);
    }

    private function watchHop(array &$watched, string $class, string $relation, array $meta): void
    {
        if ($meta['foreign_key'] !== null) {
            $this->watch($watched, $class, [$meta['foreign_key']]);

            return;
        }

        $instance = (new $class)->{$relation}();

        if ($instance instanceof HasOneOrMany) {
            $this->watch($watched, $meta['related'], [$instance->getForeignKeyName()]);
        }
    }

    private function watch(array &$watched, string $class, array $columns): void
    {
        $watched[$class] = array_values(array_unique([...($watched[$class] ?? []), ...$columns]));
    }
}
