<?php

namespace App\Services;

use Illuminate\Support\Facades\Lang;

class DeskReferenceGroups
{
    private const GROUPS = ['request_approval', 'order_processing', 'procurement_payment', 'logistics'];

    public static function all(): array
    {
        $groups = [];

        foreach (self::GROUPS as $group) {
            if (! Lang::has("deskReference/{$group}")) {
                continue;
            }

            $content = __("deskReference/{$group}");

            if (empty($content['terms']) && empty($content['process']) && empty($content['dos']) && empty($content['donts']) && empty($content['tips'])) {
                continue;
            }

            $groups[$group] = $content;
        }

        return $groups;
    }
}
