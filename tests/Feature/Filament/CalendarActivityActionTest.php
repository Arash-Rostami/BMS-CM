<?php

namespace Tests\Feature\Filament;

use App\Filament\Actions\CalendarActivityAction;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CalendarGridWidget;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\CalendarAlertNotification;
use App\Services\Calendar\CalendarActivity;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CalendarActivityActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        DB::table('calendar_hits')->delete();
        DB::table('calendar_rules')->delete();
        app()->setLocale('en');

        Carbon::setTestNow(Carbon::parse('2026-10-08'));
        CalendarHit::flushVisibleRuleIds();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        CalendarHit::flushVisibleRuleIds();
        Carbon::setTestNow();
        DB::rollBack();
        parent::tearDown();
    }

    private function useMysql(): void
    {
        $env = base_path('.env');
        if (is_file($env)) {
            $vals = [];
            foreach (explode("\n", (string) file_get_contents($env)) as $line) {
                if (preg_match('/^\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$/', $line, $m)) {
                    $vals[$m[1]] = trim(preg_replace('/\s+#.*$/', '', trim($m[2])), "\"' \t");
                }
            }
            $map = ['DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_DATABASE' => 'database', 'DB_USERNAME' => 'username', 'DB_PASSWORD' => 'password'];
            foreach ($map as $envKey => $cfgKey) {
                if (isset($vals[$envKey])) {
                    config(['database.connections.mysql.'.$cfgKey => $vals[$envKey]]);
                }
            }
        }
        DB::purge('mysql');
        config(['database.default' => 'mysql']);
    }

    private function actingAsUserWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create();

        $role = Role::create(['name' => 'calendar_activity_test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function rule(User $owner, array $overrides = []): CalendarRule
    {
        $acting = auth()->user();
        $this->actingAs($owner);
        $rule = CalendarRule::factory()->create(array_merge(['user_id' => $owner->id], $overrides));
        $this->actingAs($acting);

        return $rule;
    }

    private function readNotification(User $user, CalendarRule $rule, CalendarHit $hit): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => CalendarAlertNotification::class,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode(['rule_id' => $rule->id, 'rule_name' => $rule->name, 'alert_date' => '2026-10-08', 'hit_ids' => [$hit->id]]),
            'read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function texts(array $history): array
    {
        return array_column($history['rows'], 'text');
    }

    public function test_default_tier_lists_subject_events_newest_first_and_show_all_adds_rule_and_seen_rows(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['name' => 'watch rule']);
        $record = PurchaseRequest::factory()->create();
        $activity = app(CalendarActivity::class);

        $activity->log('matched', $record, $rule, ['label' => 'pr label', 'event_date' => '2026-10-15']);
        $activity->log('alert_sent', $record, $rule, ['label' => 'pr label', 'event_date' => '2026-10-08', 'kind' => 'lead', 'lead' => 7]);

        $default = CalendarActivityAction::history($user, PurchaseRequest::class, $record->id, false);

        $this->assertSame([
            __('resources/calendarRule/strings.activity.alert_sent', [
                'rule' => 'watch rule',
                'label' => 'pr label',
                'date' => adaptiveDate('2026-10-08'),
                'status' => __('resources/calendarRule/strings.alerts.kinds.lead'),
                'lead' => '7',
            ]),
            __('resources/calendarRule/strings.activity.matched', [
                'rule' => 'watch rule',
                'label' => 'pr label',
                'date' => adaptiveDate('2026-10-15'),
            ]),
        ], $this->texts($default));
    }

    public function test_show_all_adds_rule_edits_and_seen_events_of_the_records_rules_only(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['name' => 'watch rule']);
        $this->rule(User::factory()->create(), ['name' => 'other rule']);
        $record = PurchaseRequest::factory()->create();

        app(CalendarActivity::class)->log('matched', $record, $rule, ['label' => 'pr label', 'event_date' => '2026-10-15']);

        $hit = CalendarHit::factory()->create([
            'calendar_rule_id' => $rule->id,
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $record->id,
            'event_date' => '2026-10-15',
        ]);
        $this->readNotification($user, $rule, $hit);

        $texts = $this->texts(CalendarActivityAction::history($user, PurchaseRequest::class, $record->id, true));

        $this->assertContains(__('resources/calendarRule/strings.activity.rule_updated', [
            'rule' => 'watch rule',
            'status' => __('resources/calendarRule/strings.activity.active'),
        ]), $texts);
        $this->assertContains(__('resources/calendarRule/strings.activity.seen', [
            'rule' => 'watch rule',
            'date' => adaptiveDate('2026-10-08'),
        ]), $texts);
        $this->assertNotContains('other rule', $texts);
    }

    public function test_a_private_rule_leaves_no_trace_in_another_viewers_history(): void
    {
        $owner = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $private = $this->rule($owner, ['name' => 'secret rule', 'visibility' => \App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility::ME]);
        $shared = $this->rule($owner, ['name' => 'shared rule', 'visibility' => \App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility::EVERYONE]);
        $record = PurchaseRequest::factory()->create();
        $activity = app(CalendarActivity::class);

        $activity->log('matched', $record, $private, ['label' => 'secret label', 'event_date' => '2026-10-15']);
        $activity->log('matched', $record, $shared, ['label' => 'open label', 'event_date' => '2026-10-16']);
        $hit = CalendarHit::factory()->create([
            'calendar_rule_id' => $private->id,
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $record->id,
            'event_date' => '2026-10-15',
        ]);
        $this->readNotification($owner, $private, $hit);

        $viewer = $this->actingAsUserWithPermissions(['purchase_request.view']);

        foreach ([false, true] as $all) {
            $texts = implode(' | ', $this->texts(CalendarActivityAction::history($viewer, PurchaseRequest::class, $record->id, $all)));

            $this->assertStringNotContainsString('secret', $texts);
            $this->assertStringContainsString('shared rule', $texts);
        }

        $ownerTexts = implode(' | ', $this->texts(CalendarActivityAction::history($owner, PurchaseRequest::class, $record->id, true)));

        $this->assertStringContainsString('secret rule', $ownerTexts);
    }

    public function test_descriptions_render_at_read_time_in_the_active_locale(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['name' => 'watch rule']);
        $record = PurchaseRequest::factory()->create();
        app(CalendarActivity::class)->log('date_changed', $record, $rule, [
            'label' => 'pr label',
            'event_date' => '2026-10-20',
            'old_date' => '2026-10-15',
        ]);

        $params = ['rule' => 'watch rule', 'label' => 'pr label', 'date' => adaptiveDate('2026-10-20'), 'old_date' => adaptiveDate('2026-10-15')];

        $this->assertSame(
            __('resources/calendarRule/strings.activity.date_changed', $params, 'en'),
            CalendarActivityAction::history($user, PurchaseRequest::class, $record->id, false)['rows'][0]['text'],
        );

        app()->setLocale('fa');
        $fa = CalendarActivityAction::history($user, PurchaseRequest::class, $record->id, false);
        $faParams = ['date' => adaptiveDate('2026-10-20'), 'old_date' => adaptiveDate('2026-10-15')] + $params;
        app()->setLocale('en');
        $this->assertSame(
            __('resources/calendarRule/strings.activity.date_changed', $faParams, 'fa'),
            $fa['rows'][0]['text'],
        );
        $this->assertNotSame(
            __('resources/calendarRule/strings.activity.date_changed', $params, 'en'),
            $fa['rows'][0]['text'],
        );

        $this->assertNotSame(
            CalendarActivityAction::history($user, PurchaseRequest::class, $record->id, false)['rows'][0]['when'],
            $this->withJalaliSession(
                fn (): string => CalendarActivityAction::history($user, PurchaseRequest::class, $record->id, false)['rows'][0]['when'],
            ),
        );
    }

    private function withJalaliSession(Closure $callback): mixed
    {
        session(['calendar_type' => 'jalali']);
        try {
            return $callback();
        } finally {
            session()->forget('calendar_type');
        }
    }

    public function test_forged_module_or_record_id_returns_nothing(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['name' => 'watch rule']);
        $record = PurchaseRequest::factory()->create();
        app(CalendarActivity::class)->log('matched', $record, $rule, ['label' => 'pr label', 'event_date' => '2026-10-15']);
        $shipment = Shipment::factory()->create();

        $positive = CalendarActivityAction::history($user, PurchaseRequest::class, $record->id, false);
        $this->assertNotEmpty($positive['rows']);

        $unviewable = CalendarActivityAction::history($user, Shipment::class, $shipment->id, false);
        $this->assertSame([], $unviewable['rows']);
        $this->assertSame(__('resources/calendarRule/strings.activity.empty'), $unviewable['empty']);

        $this->assertSame([], CalendarActivityAction::history($user, 'App\\Models\\Nope', $record->id, false)['rows']);
        $this->assertSame([], CalendarActivityAction::history($user, PurchaseRequest::class, 999999999, false)['rows']);
        $this->assertSame([], CalendarActivityAction::history($user, PurchaseRequest::class, 'abc', false)['rows']);

        $this->assertArrayHasKey($record->id, CalendarActivityAction::searchRecords($user, PurchaseRequest::class, (string) $record->pr_number));
        $this->assertSame([], CalendarActivityAction::searchRecords($user, Shipment::class, (string) $shipment->shipment_no));
    }

    public function test_record_search_is_capped_at_25_results(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $this->rule($user);
        collect(range(1, 27))->each(fn () => PurchaseRequest::factory()->create());

        $prefix = (string) PurchaseRequest::query()->latest('id')->value('pr_number');
        $results = CalendarActivityAction::searchRecords($user, PurchaseRequest::class, substr($prefix, 0, 10));

        $this->assertCount(CalendarActivityAction::SEARCH_LIMIT, $results);
    }

    public function test_modal_has_no_submit_button_4xl_width_and_the_expected_fields(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $action = CalendarActivityAction::make();

        $this->assertNull($action->getModalSubmitAction());
        $this->assertSame('4xl', $action->getModalWidth());
        $this->assertSame(__('resources/calendarRule/strings.activity.label'), $action->getLabel());
        $this->assertSame(__('resources/calendarRule/strings.activity.modal_heading'), $action->getModalHeading());
        $this->assertSame('module', CalendarActivityAction::getModuleField()->getName());
        $this->assertSame('record', CalendarActivityAction::getRecordField()->getName());
        $this->assertSame('show_all', CalendarActivityAction::getShowAllField()->getName());
        $this->assertSame('history', CalendarActivityAction::getHistoryField()->getName());
    }

    public function test_activity_event_keys_exist_in_all_three_locales(): void
    {
        $events = ['matched', 'cleared', 'date_changed', 'alert_sent', 'rule_updated', 'rule_invalid', 'seen'];

        foreach (['en', 'fa', 'fr'] as $locale) {
            foreach ($events as $event) {
                $key = "resources/calendarRule/strings.activity.{$event}";
                $this->assertNotSame($key, __($key, [], $locale), "Missing [{$key}] in [{$locale}].");
            }
        }
    }

    public function test_translation_keys_never_use_backslash_separators(): void
    {
        $offenders = [];

        foreach ([app_path(), resource_path('views')] as $root) {
            foreach (\Illuminate\Support\Facades\File::allFiles($root) as $file) {
                if (preg_match('/resources\\\\[A-Za-z]+\\\\strings/', $file->getContents()) === 1) {
                    $offenders[] = $file->getRelativePathname();
                }
            }
        }

        $this->assertSame([], $offenders, 'Backslash-separated lang keys resolve on Windows only; use forward slashes.');
    }

    public function test_unknown_events_and_hostile_properties_render_safely(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['name' => 'watch rule']);
        $record = PurchaseRequest::factory()->create();
        $activity = app(CalendarActivity::class);

        $activity->log('totally_unknown', $record, $rule, ['label' => 'x']);
        $activity->log('alert_sent', $record, $rule, ['label' => ['nested'], 'event_date' => ['bad'], 'kind' => 'nope', 'lead' => 3]);
        $activity->log('matched', $record, $rule, ['label' => '<script>alert(1)</script>', 'event_date' => '2026-10-15']);

        $history = CalendarActivityAction::history($user, PurchaseRequest::class, $record->id, false);

        $this->assertCount(2, $history['rows']);
        foreach ($history['rows'] as $row) {
            $this->assertStringNotContainsString('strings.', $row['text']);
        }

        $html = view('filament.calendar.activity', $history)->render();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_record_search_treats_like_wildcards_literally(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $record = PurchaseRequest::factory()->create();

        $this->assertSame([], CalendarActivityAction::searchRecords($user, PurchaseRequest::class, '%'));
        $this->assertSame([], CalendarActivityAction::searchRecords($user, PurchaseRequest::class, '_'));
        $this->assertSame([], CalendarActivityAction::searchRecords($user, PurchaseRequest::class, 'PR-%'));
        $this->assertArrayHasKey($record->id, CalendarActivityAction::searchRecords($user, PurchaseRequest::class, (string) $record->pr_number));
    }

    public function test_an_admin_role_without_the_module_view_permission_gets_nothing(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $admin = Role::firstOrCreate(['name' => 'admin_junior', 'guard_name' => 'web']);
        $admin->revokePermissionTo(Permission::firstOrCreate(['name' => 'shipment.view', 'guard_name' => 'web']));
        $user->assignRole($admin);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $shipment = Shipment::factory()->create();

        $this->assertTrue($user->isAdmin());
        $this->assertSame([], CalendarActivityAction::searchRecords($user, Shipment::class, (string) $shipment->shipment_no));
        $this->assertSame([], CalendarActivityAction::history($user, Shipment::class, $shipment->id, true)['rows']);
        $this->assertNull(CalendarActivityAction::recordFor($user, [Shipment::class], $shipment->id));
    }

    public function test_the_dashboard_has_no_header_actions_and_the_grid_widget_exposes_the_activity_action(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        Filament::setCurrentPanel('dashboard');

        $this->assertSame([], (new ReflectionMethod(Dashboard::class, 'getHeaderActions'))->invoke(new Dashboard));

        Livewire::test(CalendarGridWidget::class)
            ->assertActionExists('calendarActivity')
            ->assertSee(__('resources/calendarRule/strings.activity.label'));
    }

    public function test_the_modal_mounts_from_the_widget_and_lists_history_for_a_viewable_record_only(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['name' => 'watch rule']);
        $record = PurchaseRequest::factory()->create();
        $shipment = Shipment::factory()->create();
        app(CalendarActivity::class)->log('matched', $record, $rule, ['label' => 'pr label', 'event_date' => '2026-10-15']);

        Livewire::test(CalendarGridWidget::class)
            ->mountAction('calendarActivity')
            ->fillForm(['module' => PurchaseRequest::class, 'record' => $record->id])
            ->assertMountedActionModalSee('pr label')
            ->fillForm(['module' => Shipment::class, 'record' => $shipment->id])
            ->assertMountedActionModalDontSee('pr label')
            ->assertMountedActionModalSee(__('resources/calendarRule/strings.activity.empty'));
    }

    public function test_a_soft_deleted_record_is_found_labelled_and_its_history_loads(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['name' => 'watch rule']);
        $live = PurchaseRequest::factory()->create();
        $gone = PurchaseRequest::factory()->create();
        app(CalendarActivity::class)->log('matched', $gone, $rule, ['label' => 'gone label', 'event_date' => '2026-10-15']);
        $gone->delete();

        $found = CalendarActivityAction::searchRecords($user, PurchaseRequest::class, (string) $gone->pr_number);
        $this->assertSame((string) $gone->pr_number.' ('.__('resources/calendarRule/strings.activity.deleted').')', $found[$gone->id]);
        $this->assertNotEmpty(CalendarActivityAction::history($user, PurchaseRequest::class, $gone->id, false)['rows']);

        $liveFound = CalendarActivityAction::searchRecords($user, PurchaseRequest::class, (string) $live->pr_number);
        $this->assertSame((string) $live->pr_number, $liveFound[$live->id]);
    }

    public function test_a_soft_deleted_record_of_an_unviewable_module_returns_nothing(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $shipment = Shipment::factory()->create();
        $shipment->delete();
        $live = PurchaseRequest::factory()->create();

        $this->assertSame([], CalendarActivityAction::searchRecords($user, Shipment::class, (string) $shipment->shipment_no));
        $this->assertNull(CalendarActivityAction::recordFor($user, Shipment::class, $shipment->id));
        $this->assertNotNull(CalendarActivityAction::recordFor($user, PurchaseRequest::class, $live->id));
    }

    public function test_the_activity_view_renders_rows_and_empty_states(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['name' => 'watch rule']);
        $record = PurchaseRequest::factory()->create();
        app(CalendarActivity::class)->log('matched', $record, $rule, ['label' => 'pr label', 'event_date' => '2026-10-15']);

        $html = view('filament.calendar.activity', CalendarActivityAction::history($user, PurchaseRequest::class, $record->id, false))->render();
        $this->assertStringContainsString('pr label', $html);
        $this->assertStringContainsString('watch rule', $html);

        $empty = view('filament.calendar.activity', CalendarActivityAction::history($user, PurchaseRequest::class, null, false))->render();
        $this->assertStringContainsString(__('resources/calendarRule/strings.activity.empty'), $empty);
    }
}
