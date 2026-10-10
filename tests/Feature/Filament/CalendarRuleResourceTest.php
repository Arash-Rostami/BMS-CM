<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\CalendarRuleResource;
use App\Filament\Resources\Master\CalendarRuleResource\Components\TableRuleBuilder;
use App\Filament\Resources\Master\CalendarRuleResource\Exports\CalendarRuleExporter;
use App\Filament\Resources\Master\CalendarRuleResource\Pages\ManageCalendarRules;
use App\Jobs\ExportCalendarRules;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\CalendarPathResolver;
use App\Services\Calendar\Sync\CalendarEngine;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class CalendarRuleResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        DB::table('calendar_hits')->delete();
        DB::table('calendar_rules')->delete();
        Queue::fake();
        CalendarRuleResource::flushSimilarCache();
        app()->setLocale('en');
    }

    protected function tearDown(): void
    {
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

        $role = Role::create(['name' => 'calendar_rule_test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    // UserStamps' creating hook stamps auth()->id() unconditionally, so a foreign-owned
    // fixture must be created while acting as its owner, then control handed back.
    private function createRuleAs(User $owner, array $attributes = []): CalendarRule
    {
        $acting = auth()->user();
        $this->actingAs($owner);
        $rule = CalendarRule::factory()->create($attributes);
        $this->actingAs($acting);

        return $rule;
    }

    private function baseFormData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'probe rule',
            'subject' => PurchaseRequest::class,
            'filters' => [],
            'date_path' => 'required_by_date',
            'day_shift' => 0,
            'lead_times' => [['lead_time' => 7], ['lead_time' => 3], ['lead_time' => 1]],
            'on_day' => true,
            'type' => 'heads_up',
            'color' => 'sky',
            'visibility' => 'me',
            'is_active' => true,
        ], $overrides);
    }

    public function test_edit_gate_blocks_a_non_owner_on_both_authorization_families(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit', 'calendar_rule.delete', 'calendar_rule.restore']);
        $foreign = $this->createRuleAs(User::factory()->create());

        $this->assertFalse(CalendarRuleResource::canEdit($foreign));
        $this->assertFalse(CalendarRuleResource::canDelete($foreign));
        $this->assertFalse(CalendarRuleResource::canRestore($foreign));
        $this->assertTrue(CalendarRuleResource::getEditAuthorizationResponse($foreign)->denied());
        $this->assertTrue(CalendarRuleResource::getUpdateAuthorizationResponse($foreign)->denied());
        $this->assertTrue(CalendarRuleResource::getDeleteAuthorizationResponse($foreign)->denied());
        $this->assertTrue(CalendarRuleResource::getRestoreAuthorizationResponse($foreign)->denied());
    }

    public function test_the_edit_gate_fails_closed_for_non_rule_records(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit']);

        $this->assertFalse(CalendarRuleResource::canEdit(new PurchaseRequest));
        $this->assertTrue(CalendarRuleResource::getEditAuthorizationResponse(new PurchaseRequest)->denied());
    }

    public function test_edit_gate_allows_the_owner_and_an_admin(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit', 'calendar_rule.delete', 'calendar_rule.restore']);
        $own = CalendarRule::factory()->create(['user_id' => $user->id]);
        $foreign = $this->createRuleAs(User::factory()->create(), ['day_shift' => 6]);

        $this->assertTrue(CalendarRuleResource::canEdit($own));
        $this->assertTrue(CalendarRuleResource::getEditAuthorizationResponse($own)->allowed());
        $this->assertFalse(CalendarRuleResource::canEdit($foreign));

        $user->assignRole(Role::firstOrCreate(['name' => 'admin_junior', 'guard_name' => 'web']));

        $this->assertTrue(CalendarRuleResource::canEdit($foreign));
        $this->assertTrue(CalendarRuleResource::getDeleteAuthorizationResponse($foreign)->allowed());
    }

    public function test_create_action_stores_a_rule_owned_by_the_current_user(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['day_shift' => 4, 'notification_type' => 'all']))
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $rule = CalendarRule::query()->where('name', 'probe rule')->firstOrFail();
        $this->assertSame($user->id, $rule->user_id);
        $this->assertSame(4, $rule->day_shift);
        $this->assertSame('all', $rule->notification_type);
    }

    public function test_the_channel_filter_narrows_the_list_to_the_chosen_notification_channel(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'purchase_request.view']);
        $mail = $this->createRuleAs($user, ['notification_type' => 'email', 'visibility' => 'everyone']);
        $app = $this->createRuleAs($user, ['notification_type' => 'in_app', 'visibility' => 'everyone']);

        Livewire::test(ManageCalendarRules::class)
            ->filterTable('notification_type', 'email')
            ->assertCanSeeTableRecords([$mail])
            ->assertCanNotSeeTableRecords([$app]);
    }

    public function test_lead_times_accept_any_custom_list_up_to_120_days_and_reject_more(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['lead_times' => [['lead_time' => 120], ['lead_time' => 45], ['lead_time' => 2]]]))
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertEqualsCanonicalizing([120, 45, 2], CalendarRule::query()->where('name', 'probe rule')->firstOrFail()->lead_times);

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['name' => 'too far', 'lead_times' => [['lead_time' => 121]]]))
            ->callMountedAction()
            ->assertHasActionErrors();
    }

    public function test_the_list_search_also_matches_the_module_label_and_the_channel_label(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'purchase_request.view', 'shipment.view']);
        $pr = $this->createRuleAs($user, ['name' => 'alpha rule', 'visibility' => 'everyone', 'subject' => PurchaseRequest::class, 'notification_type' => 'in_app']);
        $ship = $this->createRuleAs($user, ['name' => 'beta rule', 'visibility' => 'everyone', 'subject' => Shipment::class, 'notification_type' => 'email']);

        Livewire::test(ManageCalendarRules::class)
            ->searchTable(CalendarModules::label(Shipment::class))
            ->assertCanSeeTableRecords([$ship])
            ->assertCanNotSeeTableRecords([$pr]);

        Livewire::test(ManageCalendarRules::class)
            ->searchTable(CalendarRule::notificationChannel()['in_app'])
            ->assertCanSeeTableRecords([$pr])
            ->assertCanNotSeeTableRecords([$ship]);

        Livewire::test(ManageCalendarRules::class)
            ->searchTable('beta')
            ->assertCanSeeTableRecords([$ship])
            ->assertCanNotSeeTableRecords([$pr]);
    }

    public function test_global_search_finds_visible_rules_by_name_only_and_skips_deleted_or_private_ones(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'purchase_request.view']);
        $mine = $this->createRuleAs($user, ['name' => 'zeta global', 'visibility' => 'everyone']);
        $hidden = $this->createRuleAs(User::factory()->create(), ['name' => 'zeta secret', 'visibility' => 'me']);
        $gone = $this->createRuleAs($user, ['name' => 'zeta gone', 'visibility' => 'everyone']);
        $gone->delete();

        $results = CalendarRuleResource::getGlobalSearchResults('zeta');
        $titles = collect($results)->map(fn ($result): string => (string) $result->title);

        $this->assertTrue($titles->contains(fn (string $title): bool => str_contains($title, 'zeta global')));
        $this->assertFalse($titles->contains(fn (string $title): bool => str_contains($title, 'zeta secret')));
        $this->assertFalse($titles->contains(fn (string $title): bool => str_contains($title, 'zeta gone')));
        $this->assertStringContainsString('search=zeta', (string) collect($results)->first()->url);
    }

    public function test_create_action_halts_on_an_exact_duplicate(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);
        CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'existing']);
        $before = CalendarRule::count();

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData())
            ->callMountedAction()
            ->assertNotified(__('resources/calendarRule/strings.actions.duplicate_exists'));

        $this->assertSame($before, CalendarRule::count());
    }

    public function test_an_exact_duplicate_of_someone_elses_private_rule_is_not_blocked(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);
        $this->createRuleAs(User::factory()->create(), ['name' => 'secret', 'visibility' => \App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility::ME]);
        $before = CalendarRule::count();

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData())
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame($before + 1, CalendarRule::count());
    }

    public function test_edit_action_halts_when_the_new_data_matches_another_rule(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit', 'purchase_request.view']);
        CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'taken timing', 'day_shift' => 9]);
        $target = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'edit target']);

        Livewire::test(ManageCalendarRules::class)
            ->mountTableAction('edit', $target)
            ->fillForm(['day_shift' => 9])
            ->callMountedTableAction()
            ->assertNotified(__('resources/calendarRule/strings.actions.duplicate_exists'));

        $this->assertSame(0, $target->fresh()->day_shift);
    }

    public function test_duplicate_action_creates_a_copy_owned_by_the_duplicator(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);
        $source = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'source rule', 'day_shift' => 3]);

        Livewire::test(ManageCalendarRules::class)
            ->mountTableAction('duplicate', $source)
            ->callMountedTableAction()
            ->assertNotified(__('resources/calendarRule/strings.actions.duplicated'));

        $copy = CalendarRule::query()
            ->where('name', 'source rule'.__('resources/calendarRule/strings.general.copy_suffix'))
            ->firstOrFail();
        $this->assertSame($user->id, $copy->user_id);
        $this->assertSame($source->fingerprint, $copy->fingerprint);
        $this->assertSame(2, CalendarRule::count());
    }

    public function test_duplicate_action_halts_when_an_identical_rule_already_exists(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);
        $source = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'dupe source']);
        CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'dupe twin']);

        Livewire::test(ManageCalendarRules::class)
            ->mountTableAction('duplicate', $source)
            ->callMountedTableAction()
            ->assertNotified(__('resources/calendarRule/strings.actions.duplicate_exists'));

        $this->assertSame(2, CalendarRule::count());
    }

    public function test_activate_bulk_action_skips_rules_the_user_cannot_edit(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit']);
        $mine = CalendarRule::factory()->create(['user_id' => $user->id, 'is_active' => false, 'day_shift' => 1]);
        $foreign = $this->createRuleAs(User::factory()->create(), ['is_active' => false, 'day_shift' => 2]);

        Livewire::test(ManageCalendarRules::class)
            ->callTableBulkAction('activate', [$mine, $foreign]);

        $this->assertTrue($mine->fresh()->is_active);
        $this->assertFalse($foreign->fresh()->is_active);
    }

    public function test_deactivate_bulk_action_only_touches_rules_the_user_can_edit(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit']);
        $mine = CalendarRule::factory()->create(['user_id' => $user->id, 'is_active' => true, 'day_shift' => 1]);
        $foreign = $this->createRuleAs(User::factory()->create(), ['is_active' => true, 'day_shift' => 2]);

        Livewire::test(ManageCalendarRules::class)
            ->callTableBulkAction('deactivate', [$mine, $foreign]);

        $this->assertFalse($mine->fresh()->is_active);
        $this->assertTrue($foreign->fresh()->is_active);
    }

    public function test_export_bulk_action_dispatches_the_export_job(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);

        Livewire::test(ManageCalendarRules::class)
            ->callTableBulkAction('exportRules', [$rule]);

        Queue::assertPushed(ExportCalendarRules::class, fn (ExportCalendarRules $job): bool => $job->ids === [$rule->id] && $job->userId === $user->id);
    }

    public function test_shared_users_must_hold_the_module_view_permission(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit', 'purchase_request.view']);
        $target = CalendarRule::factory()->create(['user_id' => $user->id, 'day_shift' => 5]);
        $ineligible = User::factory()->create();

        Livewire::test(ManageCalendarRules::class)
            ->mountTableAction('edit', $target)
            ->fillForm(['visibility' => 'users', 'shared_user_ids' => [$ineligible->id]])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['shared_user_ids']);

        $this->assertNull($target->fresh()->shared_user_ids);
    }

    public function test_preview_action_renders_the_live_match_count(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);
        $expected = app(CalendarEngine::class)->preview($user, PurchaseRequest::class, ['rules' => []], [], 'required_by_date', 0)['count'];

        // the hint action lives on the _preview field inside the mounted create action's
        // schema — a bare callAction('preview') resolves modal actions only; a modal-less
        // action also runs and unmounts inside mountAction, so assert on persisted state
        $component = Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData())
            ->callAction(TestAction::make('preview')->schemaComponent('_preview'))
            ->instance();

        $this->assertSame(
            $expected,
            data_get($component, 'mountedActions.0.data._preview.count')
        );
    }

    public function test_merge_action_unions_lead_times_into_the_similar_rule(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'calendar_rule.edit', 'purchase_request.view']);
        $similar = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'early bird', 'lead_times' => [7], 'on_day' => false]);

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['name' => 'my new rule', 'day_shift' => 5, 'lead_times' => [['lead_time' => 3]], 'on_day' => true]))
            ->callAction(TestAction::make('merge')->schemaComponent('_similar'))
            ->assertNotified(__('resources/calendarRule/strings.form.merged', ['rule' => 'early bird']));

        $this->assertSame([7, 3], $similar->fresh()->lead_times);
        $this->assertTrue((bool) $similar->fresh()->on_day);
    }

    public function test_preview_blade_renders_the_count_and_sample_items(): void
    {
        $html = view('filament.calendar.preview', [
            'preview' => [
                'count' => 2,
                'items' => [
                    ['label' => 'Item A', 'date' => '2026-10-08'],
                    ['label' => 'Item B', 'date' => '2026-10-09'],
                ],
            ],
        ])->render();

        $this->assertStringContainsString(__('resources/calendarRule/strings.form.preview_count', ['count' => 2]), $html);
        $this->assertStringContainsString('Item A', $html);
        $this->assertStringContainsString(adaptiveDate('2026-10-08'), $html);
    }

    private function getterFor(array $data): Get
    {
        $get = Mockery::mock(Get::class);
        $get->shouldReceive('__invoke')->andReturnUsing(fn (string $key = '') => $data[$key] ?? null);

        return $get;
    }

    public function test_the_preview_shows_the_count_and_the_first_record_labels_with_dates(): void
    {
        $html = view('filament.calendar.preview', ['preview' => ['count' => 7, 'items' => [['label' => 'PR-1', 'date' => '2026-10-08']]]])->render();

        $this->assertStringContainsString('7', $html);
        $this->assertStringContainsString('PR-1', $html);
        $this->assertStringContainsString(__('resources/calendarRule/strings.form.preview_empty'), view('filament.calendar.preview', ['preview' => null])->render());
        $this->assertStringContainsString(__('resources/calendarRule/strings.form.preview_none'), view('filament.calendar.preview', ['preview' => ['count' => 0, 'items' => []]])->render());
    }

    public function test_calendar_rule_lang_keys_have_fa_fr_parity_with_en(): void
    {
        $keys = fn (string $locale): array => array_keys(\Illuminate\Support\Arr::dot(require lang_path("{$locale}/resources/calendarRule/strings.php")));

        $this->assertSame([], array_diff($keys('en'), $keys('fa')));
        $this->assertSame([], array_diff($keys('en'), $keys('fr')));
        $this->assertSame([], array_diff($keys('fa'), $keys('en')));
        $this->assertSame([], array_diff($keys('fr'), $keys('en')));
    }

    public static function hostilePayloads(): array
    {
        return [
            'related leaf the user cannot view' => [['filters' => ['x' => ['type' => 'creator.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'a']]]]]],
            'unknown filter leaf' => [['filters' => ['x' => ['type' => 'bogus', 'data' => ['operator' => 'x']]]]],
            'foreign date path' => [['date_path' => 'creator.password']],
            'unknown subject class' => [['subject' => 'zz']],
            'unviewable subject' => [['subject' => User::class]],
        ];
    }

    #[DataProvider('hostilePayloads')]
    public function test_tampered_create_payloads_are_rejected_and_never_stored(array $override): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);
        $component = Livewire::test(ManageCalendarRules::class)->mountAction('create');

        foreach ($this->baseFormData($override) as $key => $value) {
            $component->set('mountedActions.0.data.'.$key, $value);
        }

        $component->callMountedAction();

        $this->assertNotEmpty($component->errors()->toArray());
        $this->assertSame(0, CalendarRule::count());
    }

    public static function hostileExtraPaths(): array
    {
        return [
            'password column path' => [['creator.password']],
            'integer path' => [[123]],
            'nested path' => [[['a']]],
            'scalar paths' => ['zz'],
            'hop the user cannot view' => [['creator']],
            'column shaped path' => [['creator.name']],
        ];
    }

    #[DataProvider('hostileExtraPaths')]
    public function test_tampered_extra_paths_are_sanitised_and_never_stored(mixed $paths): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['extra_paths' => $paths]))
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame([], CalendarRule::query()->firstOrFail()->extra_paths);
    }

    public function test_extra_paths_are_derived_from_the_tables_the_conditions_use(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view', 'user.view']);
        $leaf = fn (string $type): array => ['type' => $type, 'data' => ['operator' => 'contains', 'settings' => ['text' => 'a']]];

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['filters' => ['own' => $leaf('pr_number'), 'related' => $leaf('creator.name')]]))
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(['creator'], CalendarRule::query()->firstOrFail()->extra_paths);
    }

    public function test_a_rule_using_only_its_own_fields_stores_no_extra_paths(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);
        $leaf = ['type' => 'pr_number', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'a']]];

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['filters' => ['own' => $leaf]]))
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame([], CalendarRule::query()->firstOrFail()->extra_paths);
    }

    public function test_a_legacy_rule_keeps_its_saved_extra_paths_and_related_filters_when_saved_unchanged(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit', 'purchase_request.view', 'user.view']);
        $filters = ['rules' => [['type' => 'creator.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'a']]]]];
        $rule = CalendarRule::factory()->create([
            'user_id' => $user->id,
            'subject' => PurchaseRequest::class,
            'date_path' => 'required_by_date',
            'extra_paths' => ['creator', 'updater'],
            'filters' => $filters,
        ]);

        Livewire::test(ManageCalendarRules::class)
            ->mountTableAction('edit', $rule)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $fresh = $rule->fresh();
        $this->assertSame(['creator', 'updater'], $fresh->extra_paths);
        $this->assertSame('creator.name', array_values($fresh->filters['rules'])[0]['type']);
    }

    public function test_a_legacy_rule_with_a_related_filter_and_no_extra_paths_still_validates_and_gets_the_relation_on_save(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit', 'purchase_request.view', 'user.view']);
        $rule = CalendarRule::factory()->create([
            'user_id' => $user->id,
            'subject' => PurchaseRequest::class,
            'date_path' => 'required_by_date',
            'extra_paths' => null,
            'filters' => ['rules' => [['type' => 'creator.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'a']]]]],
        ]);

        $this->assertSame([], app(CalendarEngine::class)->filterProblems($rule));

        Livewire::test(ManageCalendarRules::class)
            ->mountTableAction('edit', $rule)
            ->assertTableActionDataSet(['extra_paths' => ['creator']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(['creator'], $rule->fresh()->extra_paths);
    }

    private function getterWithTable(array $overrides = []): Get
    {
        return $this->getterFor($this->baseFormData($overrides));
    }

    public function test_the_table_select_offers_the_module_first_under_two_headings(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'registered_order.view', 'shipment.view']);

        $options = CalendarRuleResource::tableOptions($this->getterWithTable());

        $this->assertSame([
            __('resources/calendarRule/strings.form.tables_direct'),
            __('resources/calendarRule/strings.form.tables_far'),
        ], array_keys($options));
        $this->assertSame(CalendarPathResolver::OWN_TABLE, array_key_first($options[__('resources/calendarRule/strings.form.tables_direct')]));
        $this->assertContains(
            CalendarModules::label(Shipment::class).' (via '.CalendarModules::label(RegisteredOrder::class).')',
            $options[__('resources/calendarRule/strings.form.tables_far')]
        );
        $this->assertSame([], CalendarRuleResource::tableOptions($this->getterFor([])));
    }

    public function test_choosing_a_table_limits_the_condition_picker_to_its_fields_but_keeps_rows_from_other_tables(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'user.view', 'registered_order.view']);
        $leaf = ['type' => 'creator.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'a']]];
        $names = fn (Get $get): array => array_map(fn ($constraint): string => $constraint->getName(), CalendarRuleResource::filterConstraints($get));

        $own = $this->getterWithTable(['filters' => ['row' => $leaf]]);
        $this->assertContains('creator.name', $names($own));
        $this->assertContains('pr_number', $names($own));
        $this->assertSame('', CalendarRuleResource::chosenPath($own));
        $pickable = CalendarRuleResource::pickableNames($own, CalendarRuleResource::filterConstraints($own));
        $this->assertContains('pr_number', $pickable);
        $this->assertNotContains('creator.name', $pickable);
        $this->assertSame([], array_filter($pickable, fn (string $name): bool => str_contains($name, '.')));

        $related = $this->getterWithTable(['_table' => 'registeredOrders', 'filters' => ['row' => $leaf]]);
        $this->assertSame('registeredOrders', CalendarRuleResource::chosenPath($related));
        $relatedNames = $names($related);
        $this->assertContains('creator.name', $relatedNames);
        $this->assertContains('pr_number', $relatedNames);
        $this->assertNotEmpty(array_filter($relatedNames, fn (string $name): bool => str_starts_with($name, 'registeredOrders.')));
        $pickable = CalendarRuleResource::pickableNames($related, CalendarRuleResource::filterConstraints($related));
        $this->assertNotEmpty($pickable);
        $this->assertSame([], array_filter($pickable, fn (string $name): bool => ! str_starts_with($name, 'registeredOrders.')));
    }

    public function test_choosing_a_linked_table_offers_its_requester_and_department_selectors(): void
    {
        $this->actingAsUserWithPermissions(['proforma_invoice.view', 'purchase_request.view', 'user.view', 'department.view']);
        $get = $this->getterWithTable(['subject' => \App\Models\ProformaInvoice::class, '_table' => 'purchaseRequests', 'filters' => []]);

        $this->assertSame('purchaseRequests', CalendarRuleResource::chosenPath($get));
        $pickable = CalendarRuleResource::pickableNames($get, CalendarRuleResource::filterConstraints($get));

        $this->assertContains('purchaseRequests.requester', $pickable);
        $this->assertContains('purchaseRequests.department', $pickable);
        $this->assertNotContains('purchaseRequests.requester_id', $pickable);
    }

    public function test_a_hostile_or_unviewable_table_is_still_blocked_for_selectors(): void
    {
        $this->actingAsUserWithPermissions(['proforma_invoice.view']);
        $get = $this->getterWithTable(['subject' => \App\Models\ProformaInvoice::class, '_table' => 'purchaseRequests', 'filters' => []]);

        $this->assertSame('', CalendarRuleResource::chosenPath($get));
        $this->assertSame([], array_filter(
            array_map(fn ($constraint): string => $constraint->getName(), CalendarRuleResource::filterConstraints($get)),
            fn (string $name): bool => str_starts_with($name, 'purchaseRequests.')
        ));
        $this->assertSame('', CalendarRuleResource::chosenPath($this->getterWithTable(['subject' => \App\Models\ProformaInvoice::class, '_table' => 'purchaseRequests.requester'])));
    }

    public function test_a_forged_table_choice_falls_back_to_the_module_itself(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        foreach (['zz', 'creator', 'items.product', ['x'], 5, null, CalendarPathResolver::OWN_TABLE] as $forged) {
            $this->assertSame('', CalendarRuleResource::chosenPath($this->getterWithTable(['_table' => $forged])));
        }
    }

    public function test_the_condition_picker_offers_plain_field_names_of_the_chosen_table_and_rows_keep_the_module_prefix(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view', 'user.view']);
        $row = ['type' => 'creator.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'a']]];
        $prefix = CalendarModules::label(PurchaseRequest::class).' › ';

        $component = Livewire::test(ManageCalendarRules::class)->mountAction('create')->fillForm($this->baseFormData(['filters' => ['row' => $row]]));
        $schema = (new ReflectionMethod($component->instance(), 'getMountedActionSchema'))->invoke($component->instance());
        $builder = collect($schema->getFlatComponents(true))->first(fn ($child): bool => $child instanceof TableRuleBuilder);

        $picker = collect($builder->getBlockPickerBlocks())->reject(fn ($block): bool => $block->getName() === 'or');
        $blocks = collect($builder->getBlocks())->keyBy(fn ($block): string => $block->getName());

        $this->assertNotNull($builder->getBlock('creator.name'));
        $this->assertTrue($picker->isNotEmpty());
        $this->assertNull($picker->first(fn ($block): bool => str_contains($block->getName(), '.')));
        $this->assertNull($picker->first(fn ($block): bool => str_contains((string) $block->getLabel(), '›')));
        $this->assertStringStartsWith($prefix, (string) $blocks['pr_number']->getLabel());
        $this->assertSame(
            trim(Str::before(__('resources/purchaseRequest/strings.form.pr_number'), ' — ')),
            (string) $picker->first(fn ($block): bool => $block->getName() === 'pr_number')->getLabel()
        );
    }

    public function test_default_conditions_are_own_fields_only_and_a_chosen_related_record_adds_its_fields(): void
    {
        $resolver = app(\App\Services\Calendar\CalendarPathResolver::class);
        $labels = fn (array $paths): array => array_map(fn ($constraint): string => (string) $constraint->getLabel(), $resolver->constraintsFor(\App\Models\Shipment::class, $paths));

        $prefix = CalendarModules::label(\App\Models\Shipment::class).' › ';
        $own = $labels([]);
        $this->assertLessThan(80, count($own));
        $this->assertContains($prefix.'Carrier/Forwarder', $own);
        $this->assertSame([], array_filter($own, fn (string $label): bool => ! str_starts_with($label, $prefix)));

        $with = $labels(['carrier']);
        $this->assertNotEmpty(array_filter($with, fn (string $label): bool => ! str_starts_with($label, $prefix)));
        $this->assertGreaterThan(count($own), count($with));
    }

    public function test_no_form_label_shows_an_underscore_or_a_camel_case_token(): void
    {
        $resolver = app(\App\Services\Calendar\CalendarPathResolver::class);

        foreach ([\App\Models\Shipment::class, PurchaseRequest::class] as $subject) {
            $tables = $resolver->tableOptions($subject);
            $options = array_diff_key(array_merge($tables['direct'], $tables['far']), [\App\Services\Calendar\CalendarPathResolver::OWN_TABLE => 1]);
            $labels = [
                ...array_values($options),
                ...array_map(fn ($constraint): string => (string) $constraint->getLabel(), $resolver->constraintsFor($subject, array_keys($options))),
                ...collect($resolver->groupedDatePathOptions($subject))->flatten()->all(),
            ];

            $this->assertNotEmpty($labels);

            foreach ($labels as $label) {
                $this->assertDoesNotMatchRegularExpression('/_|[a-z][A-Z]/', $label, "{$subject}: {$label}");
            }
        }
    }

    public function test_a_grouped_related_date_path_is_stored_and_a_system_column_is_rejected(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view', 'user.view']);

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['date_path' => 'creator.created_at']))
            ->callMountedAction()
            ->assertHasActionErrors(['date_path']);

        $this->assertSame(0, CalendarRule::count());

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['date_path' => 'approver.last_log_in']))
            ->callMountedAction()
            ->assertHasActionErrors(['date_path']);

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['date_path' => 'approval_date']))
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('approval_date', CalendarRule::query()->firstOrFail()->date_path);
    }

    public function test_the_topbar_url_auto_mounts_the_create_action(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create']);

        $component = Livewire::withQueryParams(['action' => 'create'])->test(ManageCalendarRules::class);

        $this->assertSame('create', $component->get('defaultAction'));
        $component->assertSeeHtml('wire:init="mountAction(')
            ->call('mountAction', 'create', [], $component->instance()->getDefaultActionUrlContext())
            ->assertSet('mountedActions.0.name', 'create');
    }

    public function test_the_use_existing_link_auto_opens_the_edit_modal(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);

        $component = Livewire::withQueryParams(['tableAction' => 'edit', 'tableActionRecord' => (string) $rule->id])->test(ManageCalendarRules::class);

        $component->assertSeeHtml('wire:init="mountAction(')
            ->call('mountAction', 'edit', [], $component->instance()->getDefaultTableActionUrlContext())
            ->assertSet('mountedActions.0.name', 'edit')
            ->assertSet('mountedActions.0.context.recordKey', (string) $rule->id);
    }

    public function test_the_view_modal_renders_every_infolist_entry(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'viewable rule', 'lead_times' => [9, 4], 'shared_user_ids' => [$user->id]]);

        Livewire::test(ManageCalendarRules::class)
            ->mountTableAction('view', $rule)
            ->assertSee('viewable rule');

        $datePath = CalendarRuleResource::viewDatePath()->container(Schema::make()->record($rule));
        $this->assertSame(app(CalendarPathResolver::class)->datePathOptions(PurchaseRequest::class)['required_by_date'], $datePath->formatState('required_by_date'));
    }

    public function test_the_hits_count_column_is_an_icon_badge_toned_by_hits(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'purchase_request.view']);
        $empty = CalendarRule::factory()->create(['user_id' => $user->id])->forceFill(['hits_count' => 0]);
        $full = CalendarRule::factory()->create(['user_id' => $user->id])->forceFill(['hits_count' => 7]);

        $column = CalendarRuleResource::showHitsCount();

        $this->assertSame('heroicon-o-calendar-days', $column->getIcon(0));
        $this->assertTrue($column->isBadge());
        $this->assertSame('gray', $column->record($empty)->getColor(0));
        $this->assertSame('success', $column->record($full)->getColor(7));
    }

    public function test_the_view_modal_shows_the_hits_count_entry(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id])->forceFill(['hits_count' => 4]);

        Livewire::test(ManageCalendarRules::class)
            ->mountTableAction('view', $rule)
            ->assertSee(__('resources/calendarRule/strings.infolist.hits_count'))
            ->assertSee('4');

        $entry = CalendarRuleResource::viewHitsCount()->container(Schema::make()->record($rule));
        $this->assertSame('hits_count', $entry->getName());
        $this->assertSame('heroicon-o-calendar-days', $entry->getIcon(4));
    }

    public function test_the_hits_count_excludes_past_heads_up_and_unviewable_module_hits(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'purchase_request.view']);
        $mine = CalendarRule::factory()->create(['user_id' => $user->id]);
        $shipmentRule = $this->createRuleAs($user, ['subject' => Shipment::class, 'visibility' => 'everyone']);
        $hit = fn (CalendarRule $rule, string $subject, int $subjectId, string $date) => CalendarHit::factory()->create([
            'calendar_rule_id' => $rule->id,
            'subject_type' => $subject,
            'subject_id' => $subjectId,
            'event_date' => $date,
        ]);

        $hit($mine, PurchaseRequest::class, 1, today()->addDay()->toDateString());
        $hit($mine, PurchaseRequest::class, 2, today()->subDay()->toDateString());
        $hit($shipmentRule, Shipment::class, 1, today()->addDay()->toDateString());
        CalendarHit::flushVisibleRuleIds();

        $count = fn (CalendarRule $rule): ?int => CalendarRuleResource::getEloquentQuery()->whereKey($rule->id)->first()->hits_count;

        $this->assertSame(1, $count($mine));
        $this->assertSame(0, $count($shipmentRule));
    }

    public function test_the_next_due_column_shows_the_next_visible_hit_date_with_the_right_tone(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'purchase_request.view']);
        $empty = CalendarRule::factory()->create(['user_id' => $user->id]);
        $overdue = CalendarRule::factory()->create(['user_id' => $user->id, 'type' => 'action']);
        $soon = CalendarRule::factory()->create(['user_id' => $user->id]);
        $far = CalendarRule::factory()->create(['user_id' => $user->id]);
        $hit = fn (CalendarRule $rule, int $subjectId, string $date) => CalendarHit::factory()->create([
            'calendar_rule_id' => $rule->id,
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $subjectId,
            'event_date' => $date,
        ]);

        $hit($overdue, 1, today()->subDays(2)->toDateString());
        $hit($soon, 2, today()->subDay()->toDateString());
        $hit($soon, 3, today()->addDays(3)->toDateString());
        $hit($far, 4, today()->addDays(20)->toDateString());
        CalendarHit::flushVisibleRuleIds();

        $records = CalendarRuleResource::getEloquentQuery()->get()->keyBy('id');
        $this->assertNull($records[$empty->id]->hits_min_event_date);
        $this->assertSame(today()->subDays(2)->toDateString(), $records[$overdue->id]->hits_min_event_date);
        $this->assertSame(today()->addDays(3)->toDateString(), $records[$soon->id]->hits_min_event_date);

        $column = CalendarRuleResource::showNextDue();
        $this->assertSame('danger', $column->record($records[$overdue->id])->getColor(null));
        $this->assertSame('warning', $column->record($records[$soon->id])->getColor(null));
        $this->assertNull($column->record($records[$far->id])->getColor(null));
        $this->assertNull($column->record($records[$empty->id])->getColor(null));
    }

    public function test_the_watches_column_renders_the_date_path_label_and_condition_summaries_hidden_by_default(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'purchase_request.view']);
        $resolver = app(CalendarPathResolver::class);
        $rule = CalendarRule::factory()->create([
            'user_id' => $user->id,
            'subject' => PurchaseRequest::class,
            'date_path' => 'required_by_date',
            'filters' => ['rules' => [['type' => 'pr_number', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'ZZQ']]]]],
        ]);

        $column = Livewire::test(ManageCalendarRules::class)->instance()->getTable()->getColumn('watches');
        $state = $column->record($rule)->getState();

        $this->assertTrue(CalendarRuleResource::showWatches()->isToggledHiddenByDefault());
        $this->assertSame(
            [$resolver->datePathOptions(PurchaseRequest::class)['required_by_date'], ...$resolver->summaries($rule)],
            $state
        );
        $this->assertStringContainsString('ZZQ', implode(' ', $state));
    }

    public function test_the_see_on_calendar_action_links_the_dashboard_pre_filtered_to_the_rule(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);

        Livewire::test(ManageCalendarRules::class)->assertTableActionVisible('seeOnCalendar', $rule);

        $action = CalendarRuleResource::getSeeOnCalendarAction()->record($rule);
        $this->assertSame(Dashboard::getUrl(['cal_rule' => $rule->id]), $action->getUrl());
    }

    public function test_create_stores_role_ids_and_normalised_outside_emails(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);
        $role = Role::create(['name' => 'notify_role_'.uniqid(), 'guard_name' => 'web']);

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['notification_type' => 'email', 'visibility' => 'roles', 'shared_role_ids' => [$role->id], 'notify_emails' => ['Out@Example.com', ' two@example.com ']]))
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $rule = CalendarRule::query()->where('name', 'probe rule')->firstOrFail();
        $this->assertSame([$role->id], $rule->shared_role_ids);
        $this->assertSame(['out@example.com', 'two@example.com'], $rule->notify_emails);
    }

    #[DataProvider('invalidNotifyPayloads')]
    public function test_invalid_outside_emails_roles_and_channel_combinations_are_refused(array $payload, string $field): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData($payload))
            ->callMountedAction()
            ->assertHasActionErrors([$field]);

        $this->assertSame(0, CalendarRule::count());
    }

    public static function invalidNotifyPayloads(): array
    {
        return [
            'not an email' => [['notification_type' => 'email', 'notify_emails' => ['nope']], 'notify_emails'],
            'header injection' => [['notification_type' => 'email', 'notify_emails' => ["a@example.com\r\nBcc: b@example.com"]], 'notify_emails'],
            'more than ten' => [['notification_type' => 'email', 'notify_emails' => array_map(fn (int $i): string => "u{$i}@example.com", range(1, 11))], 'notify_emails'],
            'unknown role' => [['notification_type' => 'email', 'visibility' => 'roles', 'shared_role_ids' => [999999]], 'shared_role_ids.0'],
            'forged visibility with emails' => [['notification_type' => 'email', 'visibility' => 'bogus', 'notify_emails' => ['x@example.com']], 'visibility'],
        ];
    }

    public function test_the_roles_field_shows_only_for_the_roles_visibility_and_needs_a_role(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->assertFormFieldHidden('shared_role_ids')
            ->fillForm(['visibility' => 'users'])
            ->assertFormFieldHidden('shared_role_ids')
            ->fillForm(['visibility' => 'roles'])
            ->assertFormFieldVisible('shared_role_ids')
            ->fillForm($this->baseFormData(['visibility' => 'roles', 'shared_role_ids' => []]))
            ->callMountedAction()
            ->assertHasActionErrors(['shared_role_ids' => 'required']);

        $this->assertSame(0, CalendarRule::count());
    }

    public function test_outside_emails_cannot_repeat_or_belong_to_an_alert_recipient(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);
        $user->forceFill(['email' => 'Me@Example.com'])->save();
        $role = Role::create(['name' => 'notify_role_'.uniqid(), 'guard_name' => 'web'])->givePermissionTo(Permission::firstOrCreate(['name' => 'purchase_request.view', 'guard_name' => 'web']));
        $member = User::factory()->create(['email' => 'member@example.com'])->assignRole($role);
        $submit = fn (array $overrides) => Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['notification_type' => 'email', ...$overrides]))
            ->callMountedAction();

        $submit(['visibility' => 'me', 'notify_emails' => ['ME@example.com']])->assertHasActionErrors(['notify_emails']);
        $submit(['visibility' => 'roles', 'shared_role_ids' => [$role->id], 'notify_emails' => ['Member@Example.com']])->assertHasActionErrors(['notify_emails']);
        $submit(['visibility' => 'me', 'notify_emails' => ['a@example.com', 'A@example.com']])->assertHasActionErrors(['notify_emails']);
        $this->assertSame(0, CalendarRule::count());

        $submit(['visibility' => 'roles', 'shared_role_ids' => [$role->id], 'notify_emails' => ['me@example.com']])->assertHasNoActionErrors();
        $this->assertSame(['me@example.com'], CalendarRule::firstOrFail()->notify_emails);
    }

    public function test_the_emails_field_shows_only_for_email_channels_clears_on_switch_and_is_dropped_server_side(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'calendar_rule.edit', 'purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'notification_type' => 'email', 'notify_emails' => ['keep@example.com']]);
        $component = Livewire::test(ManageCalendarRules::class)->mountTableAction('edit', $rule);

        $component->assertFormFieldVisible('notify_emails')
            ->fillForm(['notification_type' => 'in_app'])
            ->assertFormFieldHidden('notify_emails');

        foreach (['email', 'all'] as $channel) {
            $component->fillForm(['notification_type' => $channel])->assertFormFieldVisible('notify_emails');
        }

        $component->fillForm(['notification_type' => 'in_app'])->callMountedTableAction();

        $this->assertNull($rule->fresh()->notify_emails);
        $this->assertNull(CalendarRule::factory()->create(['user_id' => $user->id, 'notification_type' => 'in_app', 'notify_emails' => ['x@example.com']])->fresh()->notify_emails);
    }

    public function test_the_view_modal_hides_outside_addresses_from_those_who_cannot_edit_the_rule(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit', 'purchase_request.view']);
        $role = Role::create(['name' => 'notify_role_'.uniqid(), 'guard_name' => 'web']);
        $own = CalendarRule::factory()->create(['user_id' => auth()->id(), 'notification_type' => 'email', 'notify_emails' => ['seen@example.com'], 'visibility' => 'roles', 'shared_role_ids' => [$role->id]]);
        $foreign = $this->createRuleAs(User::factory()->create(), ['visibility' => 'everyone', 'notification_type' => 'email', 'notify_emails' => ['secret@example.com', 'other@example.com']]);

        $state = fn (CalendarRule $rule): array => (new ReflectionMethod(CalendarRuleResource::class, 'visibleEmails'))->invoke(null, $rule);

        $this->assertSame(['seen@example.com'], $state($own));
        $this->assertSame([__('resources/calendarRule/strings.infolist.notify_emails_count', ['count' => 2])], $state($foreign));
    }

    public function test_duplicate_copies_roles_and_emails_for_the_editor_but_not_the_emails_for_others(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);
        $role = Role::create(['name' => 'notify_role_'.uniqid(), 'guard_name' => 'web']);
        $user->assignRole($role);
        $own = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'own source', 'day_shift' => 3, 'notification_type' => 'email', 'visibility' => 'roles', 'shared_role_ids' => [$role->id], 'notify_emails' => ['copy@example.com']]);
        $foreign = $this->createRuleAs(User::factory()->create(), ['name' => 'foreign source', 'day_shift' => 9, 'notification_type' => 'email', 'visibility' => 'roles', 'shared_role_ids' => [$role->id], 'notify_emails' => ['secret@example.com']]);

        foreach ([$own, $foreign] as $source) {
            Livewire::test(ManageCalendarRules::class)
                ->mountTableAction('duplicate', $source)
                ->callMountedTableAction()
                ->assertHasNoTableActionErrors();
        }

        $suffix = __('resources/calendarRule/strings.general.copy_suffix');
        $ownCopy = CalendarRule::query()->where('name', 'own source'.$suffix)->firstOrFail();
        $foreignCopy = CalendarRule::query()->where('name', 'foreign source'.$suffix)->firstOrFail();
        $this->assertSame([$role->id], $ownCopy->shared_role_ids);
        $this->assertSame(['copy@example.com'], $ownCopy->notify_emails);
        $this->assertSame([$role->id], $foreignCopy->shared_role_ids);
        $this->assertNull($foreignCopy->notify_emails);
    }

    public function test_the_exporter_lists_roles_and_shows_addresses_only_to_those_who_can_edit(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $role = Role::create(['name' => 'notify_role_'.uniqid(), 'guard_name' => 'web']);
        CalendarRule::factory()->create(['user_id' => $owner->id, 'notification_type' => 'email', 'visibility' => 'roles', 'shared_role_ids' => [$role->id], 'notify_emails' => ['a@example.com', 'b@example.com']]);
        $export = function (?User $viewer): string {
            $path = tempnam(sys_get_temp_dir(), 'cal');
            CalendarRuleExporter::write(CalendarRule::query(), $path, $viewer);
            $csv = (string) file_get_contents($path);
            unlink($path);

            return $csv;
        };

        $this->assertStringContainsString('a@example.com, b@example.com', $export($owner));
        $this->assertStringContainsString($role->name, $export($owner));
        $this->assertStringNotContainsString('a@example.com', $export($other));
        $this->assertStringNotContainsString('a@example.com', $export(null));
        $this->assertStringContainsString(__('resources/calendarRule/strings.export.notify_emails'), $export($other));
    }

    public function test_the_active_toggle_cannot_be_flipped_on_a_rule_the_user_does_not_own(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit']);
        $mine = CalendarRule::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $foreign = $this->createRuleAs(User::factory()->create(), ['visibility' => 'everyone', 'is_active' => true]);

        $component = Livewire::test(ManageCalendarRules::class);
        $component->call('updateTableColumnState', 'is_active', (string) $foreign->id, false);
        $component->call('updateTableColumnState', 'is_active', (string) $mine->id, false);

        $this->assertTrue($foreign->fresh()->is_active);
        $this->assertFalse($mine->fresh()->is_active);
    }

    public function test_a_non_owner_sees_view_and_duplicate_but_not_edit_or_delete(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'calendar_rule.edit', 'calendar_rule.delete']);
        $foreign = $this->createRuleAs(User::factory()->create(), ['visibility' => 'everyone']);

        Livewire::test(ManageCalendarRules::class)
            ->assertTableActionVisible('view', $foreign)
            ->assertTableActionVisible('duplicate', $foreign)
            ->assertTableActionHidden('edit', $foreign)
            ->assertTableActionHidden('delete', $foreign);
    }

    public function test_bulk_delete_skips_rules_the_user_does_not_own(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.delete']);
        $mine = CalendarRule::factory()->create(['user_id' => $user->id]);
        $foreign = $this->createRuleAs(User::factory()->create(), ['visibility' => 'everyone']);

        Livewire::test(ManageCalendarRules::class)
            ->callTableBulkAction('delete', [$mine, $foreign]);

        $this->assertTrue($mine->fresh()->trashed());
        $this->assertFalse($foreign->fresh()->trashed());
    }

    public function test_duplicate_always_starts_private_and_unshared(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);
        $source = $this->createRuleAs(User::factory()->create(), [
            'name' => 'shared source',
            'visibility' => 'users',
            'shared_user_ids' => [$user->id],
            'day_shift' => 8,
        ]);

        Livewire::test(ManageCalendarRules::class)
            ->mountTableAction('duplicate', $source)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $copy = CalendarRule::query()->where('name', 'shared source'.__('resources/calendarRule/strings.general.copy_suffix'))->firstOrFail();
        $this->assertSame('me', $copy->visibility->value);
        $this->assertNull($copy->shared_user_ids);
        $this->assertSame($user->id, $copy->user_id);
    }

    public function test_duplicate_is_refused_when_the_user_cannot_view_the_source_module(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create']);
        $source = $this->createRuleAs(User::factory()->create(), ['visibility' => 'everyone']);

        Livewire::test(ManageCalendarRules::class)
            ->mountTableAction('duplicate', $source)
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['subject']);

        $this->assertSame(1, CalendarRule::count());
    }

    private function assertMergeUnavailable(Testable $component, ?CalendarRule $record = null): void
    {
        $action = TestAction::make('merge')->schemaComponent('_similar');

        try {
            $component->callAction($record === null ? $action : $action->table($record));
            $this->fail('The merge action must not resolve.');
        } catch (ActionNotResolvableException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_merge_never_offers_a_rule_the_user_does_not_own(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'calendar_rule.edit', 'purchase_request.view']);
        $foreign = $this->createRuleAs(User::factory()->create(), ['name' => 'not mine', 'visibility' => 'everyone', 'lead_times' => [7], 'on_day' => false]);

        $this->assertMergeUnavailable(Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['day_shift' => 5, 'lead_times' => [['lead_time' => 3]]])));

        $this->assertSame([7], $foreign->fresh()->lead_times);
    }

    public function test_merge_requires_the_edit_permission(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);
        $similar = CalendarRule::factory()->create(['user_id' => $user->id, 'lead_times' => [7], 'on_day' => false]);

        $this->assertMergeUnavailable(Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['day_shift' => 5])));

        $this->assertSame([7], $similar->fresh()->lead_times);
    }

    public function test_editing_a_rule_never_offers_to_merge_it_into_itself(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit', 'purchase_request.view']);
        $target = CalendarRule::factory()->create(['user_id' => $user->id, 'lead_times' => [7]]);

        $this->assertMergeUnavailable(Livewire::test(ManageCalendarRules::class)
            ->mountTableAction('edit', $target)
            ->fillForm(['day_shift' => 5]), $target);
    }

    public function test_editing_a_rule_still_offers_to_merge_into_another_own_rule(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit', 'purchase_request.view']);
        $sibling = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'sibling', 'lead_times' => [7], 'on_day' => false]);
        $target = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'target', 'day_shift' => 2, 'lead_times' => [3]]);

        Livewire::test(ManageCalendarRules::class)
            ->mountTableAction('edit', $target)
            ->fillForm(['day_shift' => 5])
            ->callAction(TestAction::make('merge')->schemaComponent('_similar')->table($target))
            ->assertNotified(__('resources/calendarRule/strings.form.merged', ['rule' => 'sibling']));

        $this->assertSame([7, 3], $sibling->fresh()->lead_times);
    }

    private function childrenOf(object $component): array
    {
        return (new ReflectionProperty($component, 'childComponents'))->getValue($component)['default'];
    }

    public function test_the_form_is_two_tabs_with_the_rule_first_and_the_conditions_second(): void
    {
        $this->actingAsUserWithPermissions(['calendar_rule.view']);
        $components = CalendarRuleResource::form(Schema::make())->getComponents(withHidden: true);
        $tabs = array_values($this->childrenOf($components[0]));

        $this->assertCount(1, $components);
        $this->assertInstanceOf(Tabs::class, $components[0]);
        $this->assertCount(2, $tabs);
        $this->assertContainsOnlyInstancesOf(Tab::class, $tabs);
        $this->assertSame(__('resources/calendarRule/strings.form.tab_rule'), $tabs[0]->getLabel());
        $this->assertSame(__('resources/calendarRule/strings.form.tab_conditions'), $tabs[1]->getLabel());
        $this->assertContains(3, $tabs[0]->getColumns());
        $this->assertContains(3, $tabs[1]->getColumns());

        $first = array_values($this->childrenOf($tabs[0]));
        $second = array_values($this->childrenOf($tabs[1]));

        $this->assertSame(
            ['name', 'type', 'notification_type', 'subject', 'date_path', 'day_shift', 'lead_times', 'visibility', 'shared_user_ids', 'shared_role_ids', 'notify_emails', 'color', 'on_day', 'is_active'],
            array_map(fn ($component) => $component->getName(), $first)
        );
        $this->assertSame('_table', $second[0]->getName());
        $this->assertInstanceOf(\Filament\Schemas\Components\Group::class, $second[1]);
        $this->assertSame(['_preview', '_similar'], array_map(fn ($component) => $component->getName(), array_slice($second, 2)));
        $this->assertCount(4, $second);
    }

    public function test_the_view_modal_footer_offers_delete_and_edit_only_to_those_who_may_edit(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit', 'calendar_rule.delete', 'purchase_request.view']);
        $own = CalendarRule::factory()->create(['user_id' => $user->id]);
        $foreign = $this->createRuleAs(User::factory()->create(), ['visibility' => 'everyone', 'day_shift' => 6]);
        $viewAction = fn () => Livewire::test(ManageCalendarRules::class)->instance()->getTable()->getAction('view');

        $footer = collect($viewAction()->record($own)->getExtraModalFooterActions());
        $this->assertSame(['delete', 'create', 'edit'], $footer->keys()->all());
        $this->assertTrue($footer->only(['delete', 'edit'])->every(fn ($action): bool => $action->isVisible()));

        $foreignFooter = collect($viewAction()->record($foreign)->getExtraModalFooterActions());
        $this->assertSame(['delete', 'create', 'edit'], $foreignFooter->keys()->all());
        $this->assertFalse($foreignFooter->only(['delete', 'edit'])->contains(fn ($action): bool => $action->isVisible()));
    }

    public function test_the_alert_rule_actions_open_as_classic_modals_not_slide_overs(): void
    {
        $user = $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.edit', 'calendar_rule.create']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        $table = Livewire::test(ManageCalendarRules::class)->instance()->getTable();

        $this->assertFalse($table->getAction('view')->record($rule)->isModalSlideOver());
        $this->assertFalse($table->getAction('edit')->record($rule)->isModalSlideOver());
    }

    private function dateOperatorDate(string $operator): \Filament\Forms\Components\DateTimePicker
    {
        $constraint = collect(app(CalendarPathResolver::class)->constraintsFor(PurchaseRequest::class))->first(fn ($c): bool => $c->getName() === 'required_by_date');

        return collect($constraint->getOperator($operator)->constraint($constraint)->getFormSchema())->first(fn ($c): bool => $c->getName() === 'date');
    }

    private function isJalaliPicker(\Filament\Forms\Components\DateTimePicker $picker): bool
    {
        return $picker->hasView() && $picker->getView() === 'filament-jalali::jalali-date-time-picker';
    }

    public function test_condition_date_inputs_and_sentences_follow_the_calendar_toggle(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $date = '2026-10-09';

        foreach (['isAfter', 'isBefore', 'isDate'] as $operator) {
            session(['calendar_type' => 'jalali']);
            $this->assertTrue($this->isJalaliPicker($this->dateOperatorDate($operator)));
            $this->assertSame('Y-m-d', $this->dateOperatorDate($operator)->getFormat());

            session(['calendar_type' => 'gregorian']);
            $this->assertFalse($this->isJalaliPicker($this->dateOperatorDate($operator)));

            foreach (['jalali', 'gregorian'] as $calendar) {
                session(['calendar_type' => $calendar]);
                $leaf = ['type' => 'required_by_date', 'data' => ['operator' => $operator, 'settings' => ['date' => $date]]];
                $sentence = app(CalendarPathResolver::class)->sentence(PurchaseRequest::class, [], [$leaf]);

                $this->assertStringContainsString(adaptiveDate($date), $sentence);
                $this->assertStringNotContainsString($calendar === 'jalali' ? 'October' : '1405', $sentence);
            }
        }
    }

    public function test_a_jalali_entered_condition_date_is_stored_as_the_canonical_gregorian_date(): void
    {
        session(['calendar_type' => 'jalali']);
        $this->actingAsUserWithPermissions(['calendar_rule.view', 'calendar_rule.create', 'purchase_request.view']);
        $leaf = ['type' => 'required_by_date', 'data' => ['operator' => 'isAfter', 'settings' => ['mode' => 'absolute', 'date' => '2026-10-09']]];

        Livewire::test(ManageCalendarRules::class)
            ->mountAction('create')
            ->fillForm($this->baseFormData(['filters' => ['row' => $leaf]]))
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $stored = CalendarRule::query()->firstOrFail()->filters['rules'][0]['data']['settings'];
        $this->assertSame('2026-10-09', $stored['date']);
    }

    public function test_the_infolist_condition_lines_render_dates_with_the_toggle_and_localized_words(): void
    {
        $rule = CalendarRule::factory()->make(['subject' => PurchaseRequest::class, 'filters' => ['rules' => [
            ['type' => 'required_by_date', 'data' => ['operator' => 'isBefore', 'settings' => ['date' => '2026-10-09']]],
        ]]]);

        session(['calendar_type' => 'jalali']);
        $this->assertStringContainsString('1405', app(CalendarPathResolver::class)->summaries($rule)[0]);
        session(['calendar_type' => 'gregorian']);
        $this->assertStringContainsString('2026 October 09', app(CalendarPathResolver::class)->summaries($rule)[0]);
    }

    public function test_the_condition_sentence_leaks_no_english_in_fa_and_fr(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'department.view']);
        $department = \App\Models\Department::factory()->create(['name' => 'ZZQ', 'english_name' => 'ZZQ', 'code' => 'ZZQ']);
        $leaf = fn (string $type, string $operator, array $settings): array => ['type' => $type, 'data' => ['operator' => $operator, 'settings' => $settings]];
        $filters = [
            $leaf('pr_number', 'contains', ['text' => 'ZZQ']),
            $leaf('total_estimated_cost', 'isMax.inverse', ['number' => 5]),
            $leaf('required_by_date', 'isAfter.inverse', ['date' => '2026-10-09']),
            $leaf('required_by_date', 'isMonth', ['month' => 3]),
            $leaf('department', 'isRelatedTo', ['value' => $department->id]),
            ['type' => 'or', 'data' => ['groups' => [['rules' => [$leaf('pr_number', 'equals', ['text' => 'ZZQ']), $leaf('pr_number', 'startsWith.inverse', ['text' => 'ZZQ'])]], ['rules' => [$leaf('pr_number', 'endsWith', ['text' => 'ZZQ'])]]]]],
        ];
        $render = function (string $locale) use ($filters): string {
            app()->setLocale($locale);
            session(['calendar_type' => 'jalali']);
            $resolver = app(CalendarPathResolver::class);
            $labels = [CalendarModules::label(PurchaseRequest::class), ...array_map(fn (string $column): string => $resolver->columnLabel(PurchaseRequest::class, $column), ['pr_number', 'total_estimated_cost', 'required_by_date'])];
            $text = $resolver->sentence(PurchaseRequest::class, [], $filters);

            return str_replace($labels, '', $text);
        };
        $words = fn (string $text): array => array_unique(array_map('mb_strtolower', preg_match_all('/[A-Za-z]{3,}/', str_replace('ZZQ', '', $text), $m) ? $m[0] : []));

        $english = $words($render('en'));

        foreach (['fa', 'fr'] as $locale) {
            $this->assertSame([], array_values(array_intersect($english, $words($render($locale)))), $locale);
        }
    }

    public function test_the_three_add_buttons_have_distinct_localized_labels_and_dividers(): void
    {
        $root = TableRuleBuilder::make('f');
        $nested = TableRuleBuilder::make('f')->nestingDepth(2);

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $labels = [
                (string) $root->getAddAction()->getLabel(),
                (string) $nested->getAddAction()->getLabel(),
                __('filament-query-builder::query-builder.actions.add_rule_group.label'),
            ];

            $this->assertCount(3, array_unique($labels), $locale);
            $this->assertSame(__('resources/calendarRule/strings.form.add_condition'), $labels[0]);
            $this->assertNotSame(__('filament-query-builder::query-builder.item_separators.and'), __('filament-query-builder::query-builder.item_separators.or'));
        }

        app()->setLocale('fa');
        $this->assertSame('یا', __('filament-query-builder::query-builder.item_separators.or'));
        app()->setLocale('fr');
        $this->assertSame('ET', __('filament-query-builder::query-builder.item_separators.and'));
    }

    public function test_the_builder_vendor_overrides_have_the_same_keys_in_every_locale(): void
    {
        $keys = fn (string $locale): array => array_keys(\Illuminate\Support\Arr::dot(require lang_path("vendor/filament-query-builder/{$locale}/query-builder.php")));

        foreach (['actions.add_rule_group.label', 'form.or_groups.label', 'form.or_groups.block.label', 'item_separators.and', 'item_separators.or'] as $key) {
            foreach (['en', 'fa', 'fr'] as $locale) {
                $this->assertContains($key, $keys($locale), "{$locale} {$key}");
            }
        }
    }

    public function test_a_lookup_table_lists_every_column_with_existing_value_selects_for_the_name_like_ones(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'department.view']);
        $department = \App\Models\Department::factory()->create(['name' => 'بخش آزمایشی', 'english_name' => 'Probe Dept', 'code' => 'PRB-91']);
        $base = ['subject' => PurchaseRequest::class, '_table' => 'costCenter', 'filters' => []];
        $constraints = fn (array $overrides): \Illuminate\Support\Collection => collect(CalendarRuleResource::filterConstraints($this->getterWithTable($overrides)))->keyBy(fn ($constraint): string => $constraint->getName());
        $listed = CalendarRuleResource::pickableNames($this->getterWithTable($base), CalendarRuleResource::filterConstraints($this->getterWithTable($base)));

        foreach (['costCenter.name', 'costCenter.english_name', 'costCenter.code', 'costCenter.description', 'costCenter.is_active'] as $column) {
            $this->assertContains($column, $listed, $column);
        }

        $all = $constraints($base);

        foreach (['costCenter.name', 'costCenter.english_name', 'costCenter.code'] as $column) {
            $this->assertInstanceOf(\Filament\QueryBuilder\Constraints\SelectConstraint::class, $all[$column], $column);
            $this->assertTrue($all[$column]->isMultiple());
            $this->assertArrayHasKey('is', $all[$column]->getOperators());
        }

        $this->assertArrayHasKey('Probe Dept', $all['costCenter.english_name']->getOptions());
        $this->assertArrayHasKey('بخش آزمایشی', $all['costCenter.name']->getOptions());
        $this->assertArrayHasKey('Probe Dept', ($all['costCenter.english_name']->getSearchResultsUsingCallback())('Probe'));
        $this->assertNotInstanceOf(\Filament\QueryBuilder\Constraints\SelectConstraint::class, $all['costCenter.description']);

        $legacy = ['row' => ['type' => 'costCenter.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'x']]]];
        $this->assertInstanceOf(\Filament\QueryBuilder\Constraints\TextConstraint::class, $constraints([...$base, 'filters' => $legacy])['costCenter.name']);
    }

    public function test_a_table_reached_by_two_relations_lists_each_relation_with_its_own_label(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'department.view']);

        foreach (['en', 'fa'] as $locale) {
            app()->setLocale($locale);
            $direct = app(CalendarPathResolver::class)->tableOptions(PurchaseRequest::class, auth()->user())['direct'];

            $this->assertArrayHasKey('department', $direct, $locale);
            $this->assertArrayHasKey('costCenter', $direct, $locale);
            $this->assertNotSame($direct['department'], $direct['costCenter'], $locale);

            foreach (['department', 'costCenter'] as $relation) {
                $names = collect(CalendarRuleResource::filterConstraints($this->getterWithTable(['subject' => PurchaseRequest::class, '_table' => $relation, 'filters' => []])))->map->getName();
                $this->assertTrue($names->contains("{$relation}.name"), "{$locale} {$relation}");
                $this->assertFalse($names->contains($relation === 'department' ? 'costCenter.name' : 'department.name'), "{$locale} {$relation} isolation");
            }
        }

        app()->setLocale('en');
    }

    public function test_a_table_reached_by_one_relation_keeps_its_plain_table_name(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'registered_order.view', 'proforma_invoice.view']);
        $direct = app(CalendarPathResolver::class)->tableOptions(PurchaseRequest::class, auth()->user())['direct'];

        $this->assertSame(CalendarModules::label(\App\Models\RegisteredOrder::class), $direct['registeredOrders'] ?? null);
    }
}
