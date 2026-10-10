<?php

namespace Tests\Feature\Notifications;

use App\Filament\Resources\NotificationSettingResource;
use App\Models\Company;
use App\Models\Correspondence;
use App\Models\NotificationSetting;
use App\Models\Status;
use App\Models\User;
use App\Notifications\ModelEventEmail;
use App\Notifications\ModelEventNotification;
use App\Observers\CalendarTouchObserver;
use App\Observers\NotificationDispatcher;
use App\Services\Calendar\Sync\CalendarRouter;
use App\Services\NotificationEvaluator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use Mockery;
use RuntimeException;

class NotificationDeliveryTest extends NotificationTestCase
{
    public function test_a_recipient_without_view_access_to_the_module_receives_nothing_on_both_channels(): void
    {
        $blind = $this->recipient([], []);
        $other = $this->recipient([], ['payment.view']);
        $allowed = $this->recipient();
        $this->rule([$blind, $other, $allowed], [], 'all');

        $this->request()->update(['notes' => 'x']);

        $this->assertSame(0, $this->sentCount($blind) + $this->emailCount($blind));
        $this->assertSame(0, $this->sentCount($other) + $this->emailCount($other), 'A permission on another module is not enough.');
        $this->assertSame(1, $this->sentCount($allowed));
        $this->assertSame(1, $this->emailCount($allowed), 'Positive control: the permitted recipient gets both channels.');
    }

    public function test_an_inactive_user_receives_nothing(): void
    {
        $inactive = $this->recipient(['status' => 'inactive']);
        $active = $this->recipient();
        $this->rule([$inactive, $active], [], 'all');

        $this->request()->update(['notes' => 'x']);

        $this->assertSame(0, $this->sentCount($inactive) + $this->emailCount($inactive));
        $this->assertSame(2, $this->sentCount($active) + $this->emailCount($active));
    }

    public function test_a_restore_sends_nothing_but_a_normal_update_still_does(): void
    {
        $user = $this->recipient();
        $request = $this->request();
        $this->rule($user);

        $request->delete();
        $request->restore();
        $this->assertSame(0, $this->sentCount($user), 'There is no restore action.');

        $request->update(['notes' => 'x']);
        $this->assertSame(1, $this->sentCount($user));
    }

    public function test_every_operational_module_is_selectable_and_masters_are_not(): void
    {
        $tables = array_keys(NotificationSetting::scannableModels());

        foreach (['purchase_requests', 'proforma_invoices', 'registered_orders', 'bank_profiles', 'correspondences', 'purchase_orders', 'payments', 'shipments', 'customs'] as $table) {
            $this->assertContains($table, $tables);
        }

        $this->assertNotContains((new Company)->getTable(), $tables);
        $this->assertNotContains((new User)->getTable(), $tables);
        $this->assertCount(9, $tables);
    }

    public function test_correspondence_notifies_on_create_with_its_subject_as_identifier(): void
    {
        $user = $this->recipient([], ['correspondence.view']);
        $this->rule($user, ['tables' => ['correspondences'], 'actions' => ['create']]);

        $correspondence = Correspondence::factory()->create(['subject' => 'Contract renewal']);

        $this->assertSame(1, $this->sentCount($user));
        $payload = Notification::sent($user, ModelEventNotification::class)->first()->toDatabase($user);
        $this->assertSame('Contract renewal', $payload['body']['params']['identifier']);
        $this->assertSame("/dashboard/correspondences/{$correspondence->id}/edit", $payload['actions'][0]['url']);
    }

    public function test_one_failing_recipient_does_not_stop_the_others(): void
    {
        Exceptions::fake();
        $broken = $this->recipient();
        $fine = $this->recipient();
        Notification::swap($fake = new class extends NotificationFake
        {
            public array $failFor = [];

            public function send($notifiables, $notification)
            {
                if (in_array($notifiables->id, $this->failFor, true)) {
                    throw new RuntimeException('mail down');
                }

                parent::send($notifiables, $notification);
            }
        });
        $fake->failFor = [$broken->id];
        $this->rule([$broken, $fine]);

        $request = $this->request();
        $request->update(['notes' => 'x']);

        $this->assertSame('x', $request->fresh()->notes, 'The business save is unaffected.');
        $this->assertSame(1, $this->sentCount($fine));
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_a_hostile_rule_does_not_stop_the_others(): void
    {
        Exceptions::fake();
        $fine = $this->recipient();
        $this->rule($fine, ['columns' => ['urgency_level'], 'values' => ['urgency_level' => ['high']]]);
        $this->rule($fine, ['users' => 'not-a-list']);
        $this->rule($fine);

        $this->request(['urgency_level' => 'low'])->update(['urgency_level' => 'high']);

        $this->assertSame(2, $this->sentCount($fine));
    }

    public function test_the_dispatcher_isolates_an_evaluator_failure_from_the_save(): void
    {
        Exceptions::fake();
        $evaluator = Mockery::mock(NotificationEvaluator::class);
        $evaluator->shouldReceive('evaluate')->times(3)->andThrow(new RuntimeException('boom'));
        $dispatcher = new NotificationDispatcher($evaluator);
        $request = $this->request();

        $dispatcher->created($request);
        $dispatcher->updated($request);
        $dispatcher->deleted($request);

        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_the_calendar_observer_isolates_a_router_failure(): void
    {
        Exceptions::fake();
        $router = Mockery::mock(CalendarRouter::class);
        $router->shouldReceive('touch')->times(5)->andThrow(new RuntimeException('calendar boom'));
        $observer = new CalendarTouchObserver($router);
        $request = $this->request();

        $observer->created($request);
        $observer->saved($request);
        $observer->deleted($request);
        $observer->restored($request);
        $observer->forceDeleted($request);

        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_active_rules_are_cached_and_refreshed_when_a_rule_changes(): void
    {
        $user = $this->recipient();
        $rule = $this->rule($user);
        $request = $this->request();

        $queries = 0;
        DB::listen(function ($query) use (&$queries) {
            $queries += str_contains($query->sql, 'from `notification_settings`') ? 1 : 0;
        });

        $request->update(['notes' => 'a']);
        $request->update(['notes' => 'b']);
        $this->assertLessThanOrEqual(1, $queries, 'Two saves load the rules at most once.');
        $this->assertSame(2, $this->sentCount($user));

        $rule->update(['settings' => array_merge($rule->settings, ['is_active' => false])]);
        $request->update(['notes' => 'c']);
        $this->assertSame(2, $this->sentCount($user), 'A deactivated rule stops firing immediately.');

        $rule->update(['settings' => array_merge($rule->settings, ['is_active' => true])]);
        $request->update(['notes' => 'd']);
        $this->assertSame(3, $this->sentCount($user), 'Positive control: reactivating takes effect immediately.');

        $rule->delete();
        $request->update(['notes' => 'e']);
        $this->assertSame(3, $this->sentCount($user), 'Deleting stops it immediately.');

        $rule->restore();
        $request->update(['notes' => 'f']);
        $this->assertSame(4, $this->sentCount($user), 'Restoring brings it back immediately.');
    }

    public function test_recipients_are_loaded_once_per_save_for_all_matching_rules(): void
    {
        $users = [$this->recipient(), $this->recipient(), $this->recipient()];
        foreach ($users as $user) {
            $this->rule($user);
        }
        $request = $this->request();
        $request->update(['notes' => 'warm the rule cache']);

        $userQueries = 0;
        DB::listen(function ($query) use (&$userQueries) {
            $userQueries += preg_match('/from `users` where `id` in/', $query->sql) ? 1 : 0;
        });

        $request->update(['notes' => 'measured']);

        $this->assertSame(1, $userQueries);
    }

    public function test_queries_per_save_do_not_grow_with_the_number_of_matching_rules(): void
    {
        $user = $this->recipient();
        [$first, $second] = Status::factory()->count(2)->create();
        $request = $this->request(['status_id' => $first->id]);
        $this->rule($user);
        $request->update(['notes' => 'warm']);

        $measure = function (Status $status) use ($request): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $request->update(['status_id' => $status->id]);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $measure($second);
        $single = $measure($first);

        foreach (range(1, 5) as $unused) {
            $this->rule($user);
        }
        $request->update(['notes' => 'warm again']);

        $this->assertSame($single, $measure($second), 'Six matching rules cost the same queries as one.');
        $this->assertSame(15, $this->sentCount($user), 'Positive control: every rule still fired.');
    }

    public function test_the_scannable_model_list_is_cached_and_the_resource_has_no_navigation_badge(): void
    {
        NotificationSetting::flushScannableModels();
        NotificationSetting::scannableModels();
        $this->assertTrue(Cache::has('notification_scannable_models'));

        $this->assertNull(NotificationSettingResource::getNavigationBadge());
    }

    public function test_the_stored_in_app_payload_uses_keys_and_never_a_baked_language(): void
    {
        $user = $this->recipient();
        $this->rule($user, ['actions' => ['create']], 'all');

        $this->request();

        $payload = Notification::sent($user, ModelEventNotification::class)->first()->toDatabase($user);
        $this->assertIsArray($payload['title']);
        $this->assertIsArray($payload['body']);
        $this->assertSame('resources/notificationSetting/strings.notification.title.create', $payload['title']['key']);
        $this->assertSame(1, $this->emailCount($user));
        $this->assertInstanceOf(ModelEventEmail::class, Notification::sent($user, ModelEventEmail::class)->first());
    }

    public function test_rules_with_hostile_stored_shapes_are_inert_and_never_crash_a_save(): void
    {
        $user = $this->recipient();

        foreach ([
            ['tables' => 'purchase_requests'],
            ['tables' => null],
            ['actions' => 'update'],
            ['tables' => [['purchase_requests']]],
            ['users' => 'abc'],
            ['users' => null],
        ] as $override) {
            $this->rule($user, $override);
        }

        $request = $this->request();
        $request->update(['notes' => 'x']);

        $this->assertSame('x', $request->fresh()->notes);
        $this->assertSame(0, $this->sentCount($user), 'Hostile shapes fire nothing.');

        $this->rule($user);
        $request->update(['notes' => 'y']);
        $this->assertSame(1, $this->sentCount($user), 'Positive control: a well-formed rule fires.');
    }

    public function test_setting_getters_survive_a_non_array_settings_payload(): void
    {
        foreach (['junk', 5, null, true] as $payload) {
            $rule = new NotificationSetting(['settings' => $payload]);

            $this->assertSame([], $rule->getTables());
            $this->assertSame([], $rule->getUsers());
            $this->assertSame([], $rule->getColumnValues());
            $this->assertFalse($rule->isActive());
        }
    }
}
