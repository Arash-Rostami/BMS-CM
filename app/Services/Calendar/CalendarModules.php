<?php

namespace App\Services\Calendar;

use App\Models\User;
use App\Services\PermissionLabeler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CalendarModules
{
    /**
     * @var array<class-string<Model>, array{route: string, identifier: ?string}>|null
     */
    private static ?array $registry = null;

    /**
     * @return array<class-string<Model>, array{label: string, route: string, identifier: ?string}>
     */
    public static function all(): array
    {
        $modules = [];

        foreach (self::registry() as $model => $meta) {
            $modules[$model] = ['label' => PermissionLabeler::getEntityLabel($model)] + $meta;
        }

        return $modules;
    }

    /**
     * @return array<int, class-string<Model>>
     */
    public static function viewableBy(User $user): array
    {
        return array_values(array_filter(
            array_keys(self::registry()),
            fn (string $module): bool => $user->can(Str::snake(class_basename($module)).'.view')
        ));
    }

    public static function has(string $class): bool
    {
        return isset(self::registry()[$class]);
    }

    public static function label(Model|string $module): string
    {
        return PermissionLabeler::getEntityLabel($module instanceof Model ? $module::class : $module);
    }

    public static function itemLabel(Model $record): string
    {
        $constant = $record::class.'::SCANNABLE_IDENTIFIER';
        $identifier = defined($constant) ? $record->{constant($constant)} : null;

        return filled($identifier) ? (string) $identifier : '#'.$record->getKey();
    }

    public static function url(Model $record): string
    {
        return route(self::registry()[$record::class]['route'], $record);
    }

    public static function flush(): void
    {
        self::$registry = null;
    }

    /**
     * @return array<class-string<Model>, array{route: string, identifier: ?string}>
     */
    private static function registry(): array
    {
        return self::$registry ??= collect(config('workspace.resources'))
            ->mapWithKeys(fn (array $config): array => [
                $config['model'] => [
                    'route' => $config['route'],
                    'identifier' => defined($config['model'].'::SCANNABLE_IDENTIFIER') ? constant($config['model'].'::SCANNABLE_IDENTIFIER') : null,
                ],
            ])
            ->all();
    }
}
