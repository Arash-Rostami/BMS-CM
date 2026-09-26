<?php

namespace App\Services;

use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class StatusWorkflow
{
    public static function initialFor(string $englishType): ?Status
    {
        return static::orderedStatuses($englishType)->first();
    }

    public static function nextFor(Status $current): ?Status
    {
        if (is_null($current->stage_order)) {
            return null;
        }

        return static::orderedStatuses($current->english_type)
            ->firstWhere('stage_order', $current->stage_order + 1);
    }

    public static function isTerminal(Status $status): bool
    {
        return ! is_null($status->stage_order) && is_null(static::nextFor($status));
    }

    public static function canSet(?User $user, Status $target, ?Status $current): bool
    {
        if ($target->approval_permission && (! $user || ! $user->can($target->approval_permission))) {
            return false;
        }

        if (is_null($target->stage_order)) {
            return true;
        }

        if (is_null($current)) {
            return static::initialFor($target->english_type)?->is($target) ?? false;
        }

        return ! is_null($current->stage_order) && $target->stage_order === $current->stage_order + 1;
    }

    public static function assertAllowed(?User $user, Status $target, ?Status $current, string $column = 'status_id'): void
    {
        if (! static::canSet($user, $target, $current)) {
            throw ValidationException::withMessages([
                $column => __('resources/general/strings.status_workflow.transition_not_allowed'),
            ]);
        }
    }

    public static function orderedStatuses(string $englishType): Collection
    {
        return SmartCacheManager::remember(
            'Status',
            ['english_type' => $englishType, 'type' => 'ordered_stage_list'],
            60,
            fn () => Status::where('english_type', $englishType)
                ->whereNotNull('stage_order')
                ->orderBy('stage_order')
                ->get()
        );
    }
}
