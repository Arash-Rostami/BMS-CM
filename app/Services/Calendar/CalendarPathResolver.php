<?php

namespace App\Services\Calendar;

use App\Filament\Resources\Master\CalendarRuleResource\Components\AdaptiveDateConstraint;
use App\Filament\Resources\Master\CalendarRuleResource\Components\Operators\NamedIsRelatedToOperator;
use App\Models\Attributes\Indirect;
use App\Models\CalendarRule;
use App\Models\User;
use App\Services\Calendar\Sync\CalendarFilterTree;
use App\Services\NameSearch;
use App\Services\PredefinedOptions;
use Filament\QueryBuilder\Constraints\BooleanConstraint;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\RelationshipConstraint;
use Filament\QueryBuilder\Constraints\SelectConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;
use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class CalendarPathResolver
{
    private const BLOCKED_COLUMNS = ['password', 'remember_token', 'deleted_at'];

    private const SYSTEM_DATE_COLUMNS = ['created_at', 'updated_at', 'deleted_at', 'last_log_in', 'last_log_out', 'email_verified_at'];

    private const OWN_AUDIT_DATE_COLUMNS = ['created_at', 'updated_at'];

    public function __construct(private readonly CalendarFilterTree $filterTree) {}

    private const AUDIT_RELATIONS = ['creator', 'updater'];

    private const TECHNICAL_RELATIONS = ['attachments', 'customAttributes', 'extraAttributes', 'statusHistories'];

    private const OPTION_LIMIT = 100;

    private const VALUE_LIMIT = 300;

    private const TEXT_OPERATORS = ['contains', 'equals', 'startsWith', 'endsWith'];

    private const TABLE_HOPS = 2;

    public const OWN_TABLE = '_own';

    public const SEPARATOR = ' › ';

    private const USER_BLOCKED_COLUMNS = ['ip', 'phone', 'email', 'settings', 'image', 'role'];

    /**
     * @var array<string, array<string, string>>
     */
    private array $columnTypes = [];

    /**
     * @var array<string, array<string, array{related: string, single: bool, foreign_key: ?string}>>
     */
    private array $relationMeta = [];

    /**
     * @var array<string, array<string, array<string, string>>>
     */
    private array $tableMemo = [];

    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $schemaColumns = [];

    /**
     * @return array<string, string>
     */
    public function columns(string $class): array
    {
        return $this->columnTypes[$class] ??= Cache::remember('calendar_columns:'.$this->table($class), now()->addDay(), function () use ($class): array {
            $types = [];

            foreach (Schema::getColumns($this->table($class)) as $column) {
                if (! $this->isBlocked($class, $column['name'])) {
                    $types[$column['name']] = $this->typeOf($column);
                }
            }

            return $types;
        });
    }

    /**
     * @return array<string, array{related: string, single: bool, foreign_key: ?string}>
     */
    public function relations(string $class): array
    {
        return $this->relationMeta[$class] ??= Cache::remember('calendar_relations:'.$class, now()->addDay(), fn (): array => $this->reflectRelations($class));
    }

    /**
     * @return array<int, array{relation: string, class: string}>
     *
     * @throws ValidationException
     */
    public function validatePath(string $subject, string $path): array
    {
        $segments = collect(explode('.', $path));

        if ($segments->contains('')) {
            $this->throwInvalidPath();
        }

        $column = $segments->pop();
        $hops = $this->relationHops($subject, $segments);

        if (! isset($this->columns($hops === [] ? $subject : end($hops)['class'])[$column])) {
            $this->throwInvalidPath();
        }

        return $this->withinDepth($hops);
    }

    /**
     * @return array<int, array{relation: string, class: string}>
     *
     * @throws ValidationException
     */
    public function validateRelationPath(string $subject, string $path): array
    {
        $segments = collect(explode('.', $path));

        if ($segments->contains('')) {
            $this->throwInvalidPath();
        }

        return $this->withinDepth($this->relationHops($subject, $segments));
    }

    public function isRelationPath(string $subject, string $path): bool
    {
        try {
            $this->validateRelationPath($subject, $path);
        } catch (ValidationException) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function relationPathsFor(string $subject, mixed $extraPaths, mixed $filters = []): array
    {
        $paths = array_filter(is_array($extraPaths) ? $extraPaths : [], 'is_string');
        $relations = [];

        foreach ($paths as $path) {
            $relation = $this->relationOf($subject, $path);

            if ($relation !== null) {
                $relations[$relation] = true;
            }
        }

        $this->walkLeaves($filters, function (array $leaf) use ($subject, &$relations): void {
            if (is_string($leaf['type'] ?? null) && str_contains($leaf['type'], '.')) {
                $relation = $this->selectorParent($subject, $leaf['type']) ?? $this->relationOf($subject, $leaf['type']);

                if ($relation !== null) {
                    $relations[$relation] = true;
                }
            }
        });

        return array_keys($relations);
    }

    /**
     * @return array{direct: array<string, string>, far: array<string, string>}
     */
    public function tableOptions(string $subject, ?User $user = null): array
    {
        return $this->tableMemo[$subject.'|'.($user?->getKey() ?? '-')] ??= $this->reachableTables($subject, $user);
    }

    /**
     * @return array<int, string>
     */
    public function usedPaths(string $subject, mixed $filters): array
    {
        try {
            return $this->relationPathsFor($subject, [], blank($filters) ? [] : $filters);
        } catch (ValidationException) {
            return [];
        }
    }

    /**
     * @return array{direct: array<string, string>, far: array<string, string>}
     */
    private function reachableTables(string $subject, ?User $user): array
    {
        $seen = [$subject => true];
        $frontier = [['path' => '', 'class' => $subject, 'via' => []]];
        $groups = ['direct' => [], 'far' => []];

        for ($hop = 1; $hop <= self::TABLE_HOPS && $frontier !== []; $hop++) {
            $entries = [];

            foreach ($frontier as $node) {
                foreach ($this->offeredRelations($node['class'], $user) as $relation => $related) {
                    if (! isset($seen[$related])) {
                        $entries[] = ['node' => $node, 'relation' => $relation, 'related' => $related];
                    }
                }
            }

            $frontier = $this->placeEntries($entries, $hop, $groups);
            $seen += array_fill_keys(array_column($entries, 'related'), true);
        }

        asort($groups['direct'], SORT_NATURAL | SORT_FLAG_CASE);
        asort($groups['far'], SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'direct' => [self::OWN_TABLE => $this->tableLabel($subject)] + array_slice($groups['direct'], 0, self::OPTION_LIMIT, true),
            'far' => array_slice($groups['far'], 0, self::OPTION_LIMIT, true),
        ];
    }

    /**
     * @param  array<int, array{node: array, relation: string, related: string}>  $entries
     * @param  array{direct: array<string, string>, far: array<string, string>}  $groups
     * @return array<int, array{path: string, class: string, via: array<int, string>}>
     */
    private function placeEntries(array $entries, int $hop, array &$groups): array
    {
        $perTable = array_count_values(array_column($entries, 'related'));
        $labels = array_map(fn (array $entry): string => $perTable[$entry['related']] > 1
            ? $this->relationLabel($entry['node']['class'], $entry['relation'])
            : $this->tableLabel($entry['related']), $entries);
        $labelCounts = array_count_values($labels);
        $next = [];

        foreach ($entries as $index => $entry) {
            $label = $labelCounts[$labels[$index]] > 1
                ? $this->tableLabel($entry['related']).' — '.$this->humanize(Str::snake($entry['relation']))
                : $labels[$index];
            $path = ltrim("{$entry['node']['path']}.{$entry['relation']}", '.');
            $groups[$hop === 1 ? 'direct' : 'far'][$path] = $hop === 1
                ? $label
                : __('resources/calendarRule/strings.form.linked_via', ['module' => $label, 'relation' => implode(self::SEPARATOR, $entry['node']['via'])]);
            $next[] = ['path' => $path, 'class' => $entry['related'], 'via' => [...$entry['node']['via'], $label]];
        }

        return $next;
    }

    /**
     * @return array<string, string>
     */
    private function offeredRelations(string $class, ?User $user): array
    {
        return collect($this->relations($class))
            ->reject(fn (array $meta, string $relation): bool => in_array($relation, [...self::TECHNICAL_RELATIONS, ...self::AUDIT_RELATIONS], true)
                || str_ends_with($relation, 'Exclusive')
                || $meta['related'] === User::class
                || ! $this->viewable($meta['related'], $user))
            ->map(fn (array $meta): string => $meta['related'])
            ->all();
    }

    private function tableLabel(string $class): string
    {
        return CalendarModules::label($class);
    }

    private function fieldLabel(string $class, string $column): string
    {
        return $this->tableLabel($class).self::SEPARATOR.$this->columnLabel($class, $column);
    }

    /**
     * @return array<int, string>
     */
    public function leafNames(mixed $filters): array
    {
        $names = [];

        try {
            $this->walkLeaves(blank($filters) ? [] : $filters, function (array $leaf) use (&$names): void {
                if (is_string($leaf['type'] ?? null)) {
                    $names[$leaf['type']] = true;
                }
            });
        } catch (ValidationException) {
            return [];
        }

        return array_keys($names);
    }

    /**
     * @return array<int, string>
     */
    public function textLeafNames(mixed $filters): array
    {
        $names = [];

        try {
            $this->walkLeaves(blank($filters) ? [] : $filters, function (array $leaf) use (&$names): void {
                $operator = Str::beforeLast((string) ($leaf['data']['operator'] ?? ''), '.inverse');

                if (is_string($leaf['type'] ?? null) && in_array($operator, self::TEXT_OPERATORS, true)) {
                    $names[$leaf['type']] = true;
                }
            });
        } catch (ValidationException) {
            return [];
        }

        return array_keys($names);
    }

    private function selectorParent(string $subject, string $path): ?string
    {
        if (! str_contains($path, '.') || ! $this->isRelationPath($subject, $path)) {
            return null;
        }

        $parent = Str::beforeLast($path, '.');
        $last = Str::afterLast($path, '.');
        $class = $this->relationClass($subject, $parent);
        $meta = $this->relations($class)[$last] ?? null;

        return $meta !== null && $meta['foreign_key'] !== null && ! in_array($last, self::AUDIT_RELATIONS, true) && ! isset($this->columns($class)[$last])
            ? $parent
            : null;
    }

    private function relationOf(string $subject, string $path): ?string
    {
        if ($this->isRelationPath($subject, $path)) {
            return $path;
        }

        if (! str_contains($path, '.')) {
            return null;
        }

        $relation = Str::beforeLast($path, '.');

        return $this->isRelationPath($subject, $relation) && $this->isColumnPath($subject, $path) ? $relation : null;
    }

    private function isColumnPath(string $subject, string $path): bool
    {
        try {
            $this->validatePath($subject, $path);
        } catch (ValidationException) {
            return false;
        }

        return true;
    }

    private function relationClass(string $subject, string $path): string
    {
        $hops = $this->validateRelationPath($subject, $path);

        return end($hops)['class'];
    }

    public function relationViewable(string $subject, string $path, ?User $user): bool
    {
        return $this->isRelationPath($subject, $path)
            && collect($this->validateRelationPath($subject, $path))->every(fn (array $hop): bool => $this->viewable($hop['class'], $user));
    }

    /**
     * @param  array<int, array{relation: string, class: string}>  $hops
     * @return array<int, array{relation: string, class: string}>
     */
    private function withinDepth(array $hops): array
    {
        if (count($hops) > config('calendar.max_path_depth')) {
            $this->throwInvalidPath();
        }

        return $hops;
    }

    /**
     * @param  Collection<int, string>  $segments
     * @return array<int, array{relation: string, class: string}>
     */
    private function relationHops(string $subject, Collection $segments): array
    {
        $hops = [];
        $class = $subject;
        $visited = [$subject];

        foreach ($segments as $segment) {
            $relation = $this->relations($class)[$segment] ?? $this->throwInvalidPath();
            $class = $relation['related'];

            if (in_array($class, $visited)) {
                $this->throwInvalidPath();
            }

            $visited[] = $class;
            $hops[] = ['relation' => $segment, 'class' => $class];
        }

        return $hops;
    }

    /**
     * @return array<int, Constraint>
     */
    public function constraintsFor(string $subject, array $extraPaths = [], ?User $user = null, array $keep = [], array $textLeaves = []): array
    {
        $constraints = [];

        foreach ($this->columns($subject) as $column => $type) {
            if ($this->foreignKeyHidden($subject, $column, $user)) {
                continue;
            }

            $constraint = $this->belongsToConstraint($subject, $column);

            if ($constraint === null && $this->isHidden($column, $column, $user, $keep)) {
                continue;
            }

            $constraints[$column] = $constraint ?? $this->typedConstraint($column, $column, $type, $this->fieldLabel($subject, $column), $subject, in_array($column, $textLeaves, true));
        }

        $paths = $this->relationPathsFor($subject, $extraPaths);
        $repeated = array_filter(array_count_values(array_map(fn (string $path): string => $this->relationClass($subject, $path), $paths)), fn (int $count): bool => $count > 1);

        foreach ($paths as $path) {
            $this->addRelationConstraints($constraints, $subject, $path, $user, $keep, $repeated, $textLeaves);
        }

        return array_values($constraints);
    }

    /**
     * @param  array<string, Constraint>  $constraints
     */
    private function addRelationConstraints(array &$constraints, string $subject, string $path, ?User $user, array $keep, array $repeated, array $textLeaves): void
    {
        if (! $this->relationViewable($subject, $path, $user)) {
            return;
        }

        $class = $this->relationClass($subject, $path);
        $routed = $class === User::class || isset($repeated[$class]);
        $valueColumns = $this->isReferenceList($class) && $this->isLookupTarget($subject, $path) ? NameSearch::columns(new $class) : [];

        foreach ($this->columns($class) as $column => $type) {
            $name = "{$path}.{$column}";
            $selector = $this->linkedSelector($subject, $path, $class, $column, $user, $routed);

            if ($selector !== null) {
                $constraints[$selector->getName()] = $selector;
            }

            if ($this->foreignKeyHidden($class, $column, $user) || $this->isHidden($column, $name, $user, $keep) || ($selector !== null && $user !== null && ! in_array($name, $keep, true))) {
                continue;
            }

            $constraints[$name] = $this->typedConstraint($name, $name, $type, $routed ? $this->pathLabel($subject, $name) : $this->fieldLabel($class, $column), $class, in_array($name, $textLeaves, true), in_array($column, $valueColumns, true));
        }
    }

    private function isReferenceList(string $class): bool
    {
        return in_array($class, PredefinedOptions::LOOKUP_MODELS, true);
    }

    public function isLookupTarget(string $subject, string $path): bool
    {
        $parent = str_contains($path, '.') ? $this->relationClass($subject, Str::beforeLast($path, '.')) : $subject;
        $relation = Str::afterLast($path, '.');

        return ($this->relations($parent)[$relation]['foreign_key'] ?? null) !== null && ! in_array($relation, self::AUDIT_RELATIONS, true);
    }

    private function linkedSelector(string $subject, string $path, string $class, string $column, ?User $user, bool $routed): ?Constraint
    {
        if ($this->foreignKeyHidden($class, $column, $user)) {
            return null;
        }

        foreach ($this->relations($class) as $relation => $meta) {
            if ($meta['foreign_key'] === $column && ! in_array($relation, self::AUDIT_RELATIONS, true) && $this->isRelationPath($subject, "{$path}.{$relation}")) {
                $name = "{$path}.{$relation}";

                return $this->relationshipConstraint(
                    $subject,
                    $name,
                    $meta['related'],
                    $routed ? $this->pathLabel($subject, $name) : $this->tableLabel($class).self::SEPARATOR.$this->relationLabel($class, $relation)
                );
            }
        }

        return null;
    }

    private function isHidden(string $column, string $name, ?User $user, array $keep): bool
    {
        return $user !== null
            && ($column === 'id' || $column === 'path' || str_ends_with($column, '_id') || str_ends_with($column, '_type'))
            && ! in_array($name, $keep, true);
    }

    private function viewable(string $class, ?User $user): bool
    {
        return $user === null || $user->can(Str::snake(class_basename($class)).'.view');
    }

    private function foreignKeyHidden(string $subject, string $column, ?User $user): bool
    {
        foreach ($this->relations($subject) as $meta) {
            if ($meta['foreign_key'] === $column && ! $this->viewable($meta['related'], $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, Constraint>
     */
    public function constraintsForRule(CalendarRule $rule): array
    {
        $used = [];
        $this->walkLeaves($rule->filters['rules'] ?? [], function (array $leaf) use (&$used): void {
            if (is_string($leaf['type'] ?? null)) {
                $used[$leaf['type']] = true;
            }
        });

        return array_values(array_filter(
            $this->constraintsFor($rule->subject, $this->relationPathsFor($rule->subject, $rule->extra_paths, $rule->filters['rules'] ?? []), null, [], $this->textLeafNames($rule->filters['rules'] ?? [])),
            fn (Constraint $constraint): bool => isset($used[$constraint->getName()])
        ));
    }

    /**
     * @return array<string, string>
     */
    public function datePathOptions(string $subject, ?User $user = null): array
    {
        return collect($this->datePathEntries($subject, $user))
            ->map(fn (array $entry, string $path): string => $entry['chain'] === ''
                ? $this->columnLabel($subject, $entry['column'])
                : $this->pathLabel($subject, $path))
            ->all();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function groupedDatePathOptions(string $subject, ?User $user = null): array
    {
        $groups = [];

        foreach ($this->datePathEntries($subject, $user) as $path => $entry) {
            $groups[$entry['chain']]['class'] = $entry['class'];
            $groups[$entry['chain']]['options'][$path] = $this->hintedLabel($entry['class'], $entry['column']);
        }

        $grouped = [];

        foreach ($groups as $chain => $group) {
            $heading = CalendarModules::label($group['class']);
            $heading = isset($grouped[$heading]) ? $heading.' ('.$this->pathLabel($subject, (string) $chain).')' : $heading;
            $grouped[$heading] = $group['options'];
        }

        return $grouped;
    }

    /**
     * @return array{label: string, module: string}|null
     */
    public function describePath(string $subject, string $path): ?array
    {
        try {
            [$class, $column] = $this->resolvePathEnd($subject, $path);
        } catch (ValidationException) {
            return null;
        }

        return ['label' => $this->hintedLabel($class, $column), 'module' => CalendarModules::label($class)];
    }

    /**
     * @return array<string, array{class: string, column: string, chain: string}>
     */
    private function datePathEntries(string $subject, ?User $user): array
    {
        $entries = [];

        foreach ($this->dateColumns($subject, true) as $column) {
            $entries[$column] = ['class' => $subject, 'column' => $column, 'chain' => ''];
        }

        $this->collectDatePaths($subject, [], [$subject], $entries, $user);

        return $entries;
    }

    /**
     * @return array<int, string>
     */
    private function dateColumns(string $class, bool $own = false): array
    {
        return array_keys(array_filter(
            $this->columns($class),
            fn (string $type, string $column): bool => $type === 'date'
                && (! in_array($column, self::SYSTEM_DATE_COLUMNS, true) || ($own && in_array($column, self::OWN_AUDIT_DATE_COLUMNS, true))),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    private function hintedLabel(string $class, string $column): string
    {
        $label = $this->columnLabel($class, $column);
        $key = "resources/calendarRule/strings.date_hints.{$this->table($class)}.{$column}";

        return Lang::has($key) && mb_strtolower(__($key)) !== mb_strtolower($label) ? "{$label} — ".__($key) : $label;
    }

    /**
     * @return array<int, string>
     */
    public function relatedModels(CalendarRule $rule): array
    {
        $classes = [];
        $paths = [];
        $this->walkLeaves($rule->filters['rules'] ?? [], function (array $leaf) use (&$paths): void {
            if (is_string($leaf['type'] ?? null)) {
                $paths[] = $leaf['type'];
            }
        });

        if ($rule->date_path) {
            $paths[] = $rule->date_path;
        }

        foreach ($paths as $path) {
            foreach ($this->classesForType($rule->subject, $path) as $class) {
                if ($class !== $rule->subject) {
                    $classes[$class] = true;
                }
            }
        }

        return array_keys($classes);
    }

    /**
     * @return array<int, string>
     */
    public function summaries(CalendarRule $rule): array
    {
        $rules = $rule->filters['rules'] ?? [];

        if (! is_array($rules)) {
            return [];
        }

        try {
            return $this->sentenceParts($this->sentenceConstraints($rule->subject, $rule->extra_paths, $rules), $rules);
        } catch (ValidationException) {
            return [];
        }
    }

    public function sentence(string $subject, array $extraPaths, mixed $rules): string
    {
        return $this->joinParts($this->sentenceParts($this->sentenceConstraints($subject, $extraPaths, $rules), $rules), 'and');
    }

    /**
     * @return Collection<string, Constraint>
     */
    private function sentenceConstraints(string $subject, mixed $extraPaths, mixed $rules): Collection
    {
        return collect($this->constraintsFor($subject, $this->relationPathsFor($subject, $extraPaths, $rules), null, [], $this->textLeafNames($rules)))
            ->keyBy(fn (Constraint $constraint): string => $constraint->getName());
    }

    /**
     * @param  Collection<string, Constraint>  $constraints
     * @return array<int, string>
     */
    private function sentenceParts(Collection $constraints, mixed $rules): array
    {
        $parts = [];

        foreach (is_array($rules) ? $rules : [] as $leaf) {
            if (! is_array($leaf) || ! is_array($leaf['data'] ?? null)) {
                continue;
            }

            $part = ($leaf['type'] ?? null) === RuleBuilder::OR_BLOCK_NAME
                ? $this->orSentence($constraints, $leaf['data'])
                : $this->leafSentence($constraints[$leaf['type'] ?? ''] ?? null, $leaf['data']);

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    /**
     * @param  Collection<string, Constraint>  $constraints
     */
    private function orSentence(Collection $constraints, array $data): string
    {
        $groups = [];

        foreach (is_array($data[RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] ?? null) ? $data[RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] : [] as $group) {
            $text = $this->joinParts($this->sentenceParts($constraints, is_array($group) ? ($group['rules'] ?? []) : []), 'and');

            if ($text !== '') {
                $groups[] = '('.$text.')';
            }
        }

        return $this->joinParts($groups, 'or');
    }

    private function leafSentence(?Constraint $constraint, array $data): string
    {
        [$name, $inverse] = $constraint?->parseOperatorString((string) ($data['operator'] ?? '')) ?? ['', false];
        $operator = $constraint?->getOperator($name);

        if ($operator === null) {
            return '';
        }

        try {
            return $operator->constraint($constraint)->settings(is_array($data['settings'] ?? null) ? $data['settings'] : [])->inverse($inverse)->getSummary();
        } catch (Throwable) {
            return $constraint->getLabel();
        }
    }

    /**
     * @param  array<int, string>  $parts
     */
    private function joinParts(array $parts, string $word): string
    {
        return implode(' '.__("resources/calendarRule/strings.form.{$word}").' ', $parts);
    }

    private function table(string $class): string
    {
        return (new $class)->getTable();
    }

    private function typeOf(array $column): string
    {
        return match (true) {
            in_array($column['type_name'], ['date', 'datetime', 'timestamp']) => 'date',
            $column['type_name'] === 'boolean' || ($column['type_name'] === 'tinyint' && str_contains($column['type'], '(1)')) => 'boolean',
            in_array($column['type_name'], ['int', 'bigint', 'smallint', 'mediumint', 'tinyint', 'decimal', 'numeric', 'float', 'double', 'real']) => 'number',
            default => 'text',
        };
    }

    private function isBlocked(string $class, string $column): bool
    {
        return in_array($column, self::BLOCKED_COLUMNS, true)
            || str_starts_with($column, 'two_factor_')
            || ($class === User::class && in_array($column, self::USER_BLOCKED_COLUMNS, true));
    }

    /**
     * @return array<string, array{related: string, single: bool, foreign_key: ?string}>
     */
    private function reflectRelations(string $class): array
    {
        $model = new $class;
        $relations = [];
        $root = str_replace('\\', '/', strtolower((string) realpath(app_path('Models')))).'/';

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! $this->isAppDeclared($method, $root) || $method->getNumberOfParameters() !== 0 || ! $this->returnsRelation($method) || $method->getAttributes(Indirect::class) !== []) {
                continue;
            }

            try {
                $relation = $method->invoke($model);
            } catch (Throwable) {
                continue;
            }

            if ($relation instanceof Relation && ! $relation instanceof MorphTo && ! $relation instanceof HasManyThrough) {
                $relations[$method->name] = [
                    'related' => $relation->getRelated()::class,
                    'single' => $relation instanceof BelongsTo || $relation instanceof HasOne || $relation instanceof MorphOne,
                    'foreign_key' => $relation instanceof BelongsTo ? $relation->getForeignKeyName() : null,
                ];
            }
        }

        return $relations;
    }

    private function isAppDeclared(ReflectionMethod $method, string $root): bool
    {
        $file = $method->getFileName();

        return $file !== false && str_starts_with(str_replace('\\', '/', strtolower($file)), $root);
    }

    private function returnsRelation(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        return $type instanceof \ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), Relation::class);
    }

    private function throwInvalidPath(): never
    {
        throw ValidationException::withMessages([
            'path' => __('resources/calendarRule/strings.form.validation_deep_path'),
        ]);
    }

    private function belongsToConstraint(string $subject, string $column): ?Constraint
    {
        foreach ($this->relations($subject) as $relation => $meta) {
            if ($meta['foreign_key'] === $column) {
                return $this->relationshipConstraint($subject, $relation, $meta['related'], $this->tableLabel($subject).self::SEPARATOR.$this->relationLabel($subject, $relation));
            }
        }

        return null;
    }

    private function relationshipConstraint(string $subject, string $relation, string $related, string $label): Constraint
    {
        $title = $this->titleColumn($related);
        $operator = NamedIsRelatedToOperator::for(new $related)->titleAttribute($title);
        $operator->baseQuery(fn (Builder $query): Builder => $operator->apply($query, ''));

        return RelationshipConstraint::make($relation)
            ->model($subject)
            ->relationship($relation, $title)
            ->selectable($operator)
            ->label($label);
    }

    private function existingValuesConstraint(string $name, string $attribute, string $label, string $class, string $column): Constraint
    {
        $distinct = fn () => (new $class)->newQuery()->whereNotNull($column)->where($column, '!=', '')->distinct()->orderBy($column);

        return SelectConstraint::make($name)->attribute($attribute)->label($label)->multiple()->searchable()->nullable($this->isNullable($class, $column))
            ->optionsLimit(self::VALUE_LIMIT)
            ->options(fn (): array => $distinct()->limit(self::VALUE_LIMIT)->pluck($column, $column)->all())
            ->getSearchResultsUsing(fn (string $search): array => $distinct()->where($column, 'like', '%'.addcslashes($search, '%_\\').'%')->limit(NameSearch::LIMIT)->pluck($column, $column)->all())
            ->getOptionLabelsUsing(fn (array $values): array => array_combine($values, $values));
    }

    private function typedConstraint(string $name, string $attribute, string $type, string $label, string $class, bool $legacyText, bool $lookupValues = false): Constraint
    {
        $column = Str::afterLast($attribute, '.');

        if ($lookupValues && $type === 'text' && ! $legacyText) {
            return $this->existingValuesConstraint($name, $attribute, $label, $class, $column);
        }

        $options = $type === 'text' && ! $legacyText ? app(PredefinedOptions::class)->optionsFor($class, $column) : null;

        if ($options !== null) {
            return SelectConstraint::make($name)->attribute($attribute)->label($label)->options($options)->multiple()->searchable()->nullable($this->isNullable($class, $column));
        }

        return match ($type) {
            'date' => AdaptiveDateConstraint::make($name)->attribute($attribute)->label($label),
            'number' => NumberConstraint::make($name)->attribute($attribute)->label($label),
            'boolean' => BooleanConstraint::make($name)->attribute($attribute)->label($label),
            default => TextConstraint::make($name)->attribute($attribute)->label($label),
        };
    }

    private function isNullable(string $class, string $column): bool
    {
        $table = $this->table($class);
        $this->schemaColumns[$table] ??= collect(Schema::getColumns($table))->keyBy('name')->all();

        return (bool) ($this->schemaColumns[$table][$column]['nullable'] ?? false);
    }

    private function titleColumn(string $class): string
    {
        return NameSearch::display(new $class) ?? $this->table($class).'.id';
    }

    public function columnLabel(string $class, string $column): string
    {
        $resource = 'resources/'.Str::camel(class_basename($class));
        $base = Str::replaceEnd('_id', '', $column);
        $label = $this->translated([
            "{$resource}/strings.form.{$column}",
            "{$resource}/strings.table.{$column}",
            "resources/general/strings.columns.{$column}",
            ...($base === $column ? [] : ["{$resource}/strings.form.{$base}", 'resources/general/strings.relations.'.Str::camel($base)]),
        ]);

        return $label === null ? $this->humanize($column) : trim(Str::before($label, ' — '));
    }

    private function humanize(string $name): string
    {
        return $name === 'id' ? 'ID' : Str::headline(Str::replaceEnd('_id', '', $name));
    }

    private function relationLabel(string $class, string $relation): string
    {
        $prefix = 'resources/'.Str::camel(class_basename($class)).'/strings.form.';
        $label = $this->translated([
            $prefix.$relation,
            $prefix.Str::snake($relation),
            "resources/general/strings.relations.{$relation}",
        ]);

        return $label === null ? $this->humanize(Str::snake($relation)) : trim(Str::before($label, ' — '));
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function translated(array $keys): ?string
    {
        foreach ([false, true] as $fallback) {
            foreach ($keys as $key) {
                if (Lang::has($key, null, $fallback)) {
                    return __($key);
                }
            }
        }

        return null;
    }

    private function pathLabel(string $subject, string $path): string
    {
        $class = $subject;
        $segments = collect(explode('.', $path))->filter()->values();
        $labels = [];

        foreach ($segments as $index => $segment) {
            if ($index === $segments->keys()->last() && ! isset($this->relations($class)[$segment])) {
                $labels[] = $this->columnLabel($class, $segment);

                break;
            }

            $labels[] = $this->relationLabel($class, $segment);

            if (! isset($this->relations($class)[$segment])) {
                break;
            }

            $class = $this->relations($class)[$segment]['related'];
        }

        return implode(self::SEPARATOR, $labels);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePathEnd(string $subject, string $path): array
    {
        $hops = $this->validatePath($subject, $path);
        $segments = collect(explode('.', $path))->filter()->values();

        return [count($hops) > 0 ? end($hops)['class'] : $subject, $segments->last()];
    }

    private function collectDatePaths(string $class, array $chain, array $visited, array &$entries, ?User $user): void
    {
        if (count($chain) >= config('calendar.max_path_depth')) {
            return;
        }

        foreach ($this->relations($class) as $relation => $meta) {
            if (! $meta['single'] || in_array($meta['related'], $visited) || ! $this->viewable($meta['related'], $user)) {
                continue;
            }

            $path = implode('.', [...$chain, $relation]);

            foreach ($this->dateColumns($meta['related']) as $column) {
                $entries["{$path}.{$column}"] = ['class' => $meta['related'], 'column' => $column, 'chain' => $path];
            }

            $this->collectDatePaths($meta['related'], [...$chain, $relation], [...$visited, $meta['related']], $entries, $user);
        }
    }

    /**
     * @return array<int, string>
     */
    private function classesForType(string $subject, string $type): array
    {
        if (! str_contains($type, '.') && isset($this->relations($subject)[$type])) {
            return [$this->relations($subject)[$type]['related']];
        }

        if ($this->selectorParent($subject, $type) !== null) {
            return array_column($this->validateRelationPath($subject, $type), 'class');
        }

        try {
            return array_column($this->validatePath($subject, $type), 'class');
        } catch (ValidationException) {
            return [];
        }
    }

    private function walkLeaves(mixed $rules, callable $callback): void
    {
        $this->filterTree->walkLeaves($rules, $callback);
    }
}
