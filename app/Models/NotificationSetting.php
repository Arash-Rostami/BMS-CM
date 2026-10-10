<?php

namespace App\Models;

use App\Models\Traits\General\ModelInspector;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\NotificationSetting\HasNotificationChannel;
use App\Models\Traits\NotificationSetting\HasRecipient;
use App\Models\Traits\NotificationSetting\Setting;
use App\Services\SmartCacheManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class NotificationSetting extends Model
{
    use HasFactory,
        HasNotificationChannel,
        HasRecipient,
        ModelInspector,
        Relationships,
        Setting,
        SoftDeletes,
        UserStamps;

    public const ACTIONS = ['create', 'update', 'delete'];

    protected $fillable = [
        'settings',
        'notification_type',
        'notes',
        'user_id',
        'updated_by_id',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (blank($model->user_id)) {
                throw new \RuntimeException('NotificationSetting requires an explicit user_id when created outside an authenticated context — UserStamps only auto-fills it when auth()->check() is true.');
            }
        });

        foreach (['saved', 'deleted', 'restored'] as $event) {
            static::$event(fn () => static::flushCache());
        }
    }

    /**
     * @return Collection<int, static>
     */
    public function scopeOwnedOrReceivedBy(Builder $query, ?int $userId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('user_id', $userId)
            ->orWhereJsonContains('settings->users', $userId)
            ->orWhereJsonContains('settings->users', (string) $userId));
    }

    public static function activeRules(): Collection
    {
        return SmartCacheManager::remember(
            'NotificationSetting',
            ['type' => 'active_rules'],
            600,
            fn () => static::query()
                ->select(['id', 'settings', 'notification_type', 'notes', 'user_id'])
                ->get()
                ->filter(fn (self $rule) => $rule->isActive())
                ->values()
        );
    }

    public static function flushCache(): void
    {
        SmartCacheManager::invalidate('NotificationSetting');
        DB::afterCommit(fn () => SmartCacheManager::invalidate('NotificationSetting'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function sanitizeSettings(mixed $settings): array
    {
        $settings = is_array($settings) ? $settings : [];
        $tables = array_values(array_intersect(self::scalarStrings($settings['tables'] ?? []), array_keys(self::scannableModels())));
        $actions = array_values(array_intersect(self::scalarStrings($settings['actions'] ?? []), self::ACTIONS));

        $clean = [
            'tables' => $tables,
            'actions' => $actions,
            'users' => array_values(array_unique(array_map('intval', array_filter((array) ($settings['users'] ?? []), 'is_numeric')))),
            'is_active' => in_array($settings['is_active'] ?? false, [true, 1, '1', 'true'], true),
        ];

        if (! in_array('update', $actions, true)) {
            return $clean;
        }

        $clean['columns'] = self::knownColumns($settings['columns'] ?? [], $tables);
        $clean['values'] = self::columnValueMap($settings['values'] ?? [], $clean['columns'], $tables);

        return $clean;
    }

    private static function knownColumns(mixed $columns, array $tables): array
    {
        $known = array_merge(...array_map(fn (string $table) => self::selectableColumns($table), $tables) ?: [[]]);

        return array_values(array_unique(array_intersect(self::scalarStrings($columns), $known)));
    }

    private static function columnValueMap(mixed $values, array $columns, array $tables): array
    {
        if (! is_array($values)) {
            return [];
        }

        $map = [];

        foreach ($columns as $column) {
            $list = is_array($values[$column] ?? null) ? self::cleanValueList($values[$column], $tables, $column) : [];

            if ($list !== [] && ! self::isSensitiveColumn($column)) {
                $map[$column] = $list;
            }
        }

        return $map;
    }

    private static function cleanValueList(array $values, array $tables, string $column): array
    {
        $values = array_map(fn ($value) => is_string($value) ? trim($value) : $value, array_filter($values, 'is_scalar'));
        $allowed = self::predefinedOptions($tables, $column);
        $values = $allowed === null ? $values : array_filter($values, fn ($value) => isset($allowed[(string) $value]));
        $values = array_values(array_unique(array_filter($values, fn ($value) => self::isValidTypedValue($tables, $column, $value)), SORT_REGULAR));
        $values = str_ends_with($column, '_id') ? self::existingForeignIds($tables, $column, $values) : $values;

        return array_slice($values, 0, self::VALUES_PER_COLUMN_LIMIT);
    }

    private static function scalarStrings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
