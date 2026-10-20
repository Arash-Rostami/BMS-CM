<?php

namespace App\Services\Reports\Support;

use App\Models\BankProfile;
use App\Models\Custom;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Services\AuditChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class FileActivity
{
    public const ALIAS = 'file_activity';

    private const CHILDREN = [BankProfile::class => 'bank_profiles', Shipment::class => 'shipments', Custom::class => 'customs'];

    public static function idleSql(): string
    {
        return 'DATEDIFF('.SqlToday::literal().', COALESCE(file_activity.last_at, registered_orders.updated_at, registered_orders.created_at))';
    }

    public static function join(Builder $orders): Builder
    {
        return $orders->leftJoinSub(self::latest(), self::ALIAS, self::ALIAS.'.registered_order_id', '=', 'registered_orders.id');
    }

    public static function latest(): QueryBuilder
    {
        return DB::query()
            ->fromSub(self::events(), 'events')
            ->select('events.registered_order_id')
            ->selectRaw('max(events.at) as last_at')
            ->selectRaw("substring_index(group_concat(events.ref order by events.at desc, events.ref desc separator ','), ',', 1) as last_ref")
            ->groupBy('events.registered_order_id');
    }

    private static function events(): QueryBuilder
    {
        $events = self::audit(RegisteredOrder::class)->unionAll(self::history(RegisteredOrder::class));

        foreach (self::CHILDREN as $class => $table) {
            $events->unionAll(self::audit($class, $table))->unionAll(self::history($class, $table));
        }

        return $events;
    }

    private static function audit(string $class, ?string $table = null): QueryBuilder
    {
        $query = DB::table('activity_log as a')
            ->where('a.log_name', AuditChanges::LOG_NAME)
            ->where('a.subject_type', $class);

        return self::project($query, $table, 'a.subject_id', 'a.id', 'a.causer_id', 'a.created_at', 'a');
    }

    private static function history(string $class, ?string $table = null): QueryBuilder
    {
        $query = DB::table('status_histories as h')->where('h.statusable_type', $class);

        return self::project($query, $table, 'h.statusable_id', 'h.id', 'h.user_id', 'h.created_at', 's');
    }

    private static function project(QueryBuilder $query, ?string $table, string $subject, string $id, string $actor, string $at, string $kind): QueryBuilder
    {
        $owner = $table ? 'c.registered_order_id' : $subject;

        return $query
            ->when($table, fn (QueryBuilder $join) => $join->join("{$table} as c", 'c.id', '=', $subject))
            ->selectRaw("{$owner} as registered_order_id, {$at} as at, concat_ws(':', '{$kind}', {$id}, coalesce({$actor}, 0)) as ref");
    }
}
