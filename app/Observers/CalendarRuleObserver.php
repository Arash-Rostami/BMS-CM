<?php

namespace App\Observers;

use App\Jobs\SyncCalendarRule;
use App\Models\CalendarRule;
use App\Services\Calendar\CalendarActivity;
use App\Services\Calendar\Sync\CalendarRouter;
use App\Services\SmartCacheManager;

class CalendarRuleObserver
{
    public function __construct(
        private CalendarRouter $router,
        private CalendarActivity $activity,
    ) {}

    public function saving(CalendarRule $rule): void
    {
        $rule->computeFingerprints();
        $rule->watched_columns = $this->router->computeWatchedColumns($rule);
    }

    public function saved(CalendarRule $rule): void
    {
        if (! $rule->wasRecentlyCreated && ! $rule->wasChanged()) {
            return;
        }

        $this->activity->log('rule_updated', $rule, $rule, ['active' => (bool) $rule->is_active]);
        $this->invalidate();

        if (! CalendarRouter::isSuspended()) {
            $job = SyncCalendarRule::dispatch($rule->id);
            if (! $rule->wasRecentlyCreated) {
                $job->delay(120);
            }
        }
    }

    public function deleted(CalendarRule $rule): void
    {
        $this->invalidate();

        if (! CalendarRouter::isSuspended()) {
            SyncCalendarRule::dispatch($rule->id);
        }
    }

    private function invalidate(): void
    {
        SmartCacheManager::invalidate('CalendarRule');
        CalendarRouter::flushRoutes();
    }
}
