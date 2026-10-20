<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ActivityResource;
use App\Filament\Resources\BankProfileResource;
use App\Filament\Resources\CustomResource;
use App\Filament\Resources\General\InfoComponents;
use App\Filament\Resources\Master\ActivityResource\Pages\ManageActivities;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\ProformaInvoiceResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\PurchaseRequestResource;
use App\Filament\Resources\RegisteredOrderResource;
use App\Filament\Resources\ShipmentResource;
use App\Models\CalendarRule;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Services\AuditChanges;
use App\Services\Calendar\CalendarActivity;
use App\Services\SmartCacheManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityResourceTest extends TestCase
{
    private const MODULE_VIEWS = [
        'purchase_request.view', 'registered_order.view', 'purchase_order.view', 'payment.view',
        'shipment.view', 'custom.view', 'bank_profile.view', 'proforma_invoice.view',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        app()->setLocale('en');
        SmartCacheManager::invalidate('Activity');
    }

    protected function tearDown(): void
    {
        SmartCacheManager::invalidate('Activity');
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

        $role = Role::create(['name' => 'agent_test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function auditRow(Model $record, string $event = 'created'): Activity
    {
        return Activity::query()
            ->inLog('audit')
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->getKey())
            ->where('event', $event)
            ->firstOrFail();
    }

    private function auditTab(Schema $schema): ?Tab
    {
        $tabsComponent = $schema->getComponents()[0];
        $reflection = new ReflectionProperty($tabsComponent, 'childComponents');
        $reflection->setAccessible(true);

        return collect($reflection->getValue($tabsComponent)['default'])->first(function ($tab) {
            $labelReflection = new ReflectionProperty($tab, 'label');
            $labelReflection->setAccessible(true);

            return $labelReflection->getValue($tab) === __('resources/general/strings.status_history.tab_label');
        });
    }

    // Permissions

    public function test_view_permission_opens_the_resource_and_every_write_permission_stays_dead(): void
    {
        $this->actingAsUserWithPermissions(['activity.view', 'activity.create', 'activity.edit', 'activity.delete', 'activity.restore']);
        $row = Activity::query()->inLog('audit')->firstOr(fn () => $this->auditRow(PurchaseRequest::factory()->create()));

        $this->assertTrue(ActivityResource::canViewAny());
        $this->assertTrue(ActivityResource::getViewAuthorizationResponse($row)->allowed());
        $this->assertFalse(ActivityResource::canCreate());
        $this->assertFalse(ActivityResource::canEdit($row));
        $this->assertFalse(ActivityResource::canDelete($row));
        $this->assertFalse(ActivityResource::canDeleteAny());
        $this->assertFalse(ActivityResource::canForceDelete($row));
        $this->assertFalse(ActivityResource::canRestore($row));
        $this->assertTrue(ActivityResource::getCreateAuthorizationResponse()->denied());
        $this->assertTrue(ActivityResource::getEditAuthorizationResponse($row)->denied());
        $this->assertTrue(ActivityResource::getUpdateAuthorizationResponse($row)->denied());
        $this->assertTrue(ActivityResource::getDeleteAuthorizationResponse($row)->denied());
        $this->assertTrue(ActivityResource::getDeleteAnyAuthorizationResponse()->denied());
        $this->assertTrue(ActivityResource::getForceDeleteAuthorizationResponse($row)->denied());
        $this->assertTrue(ActivityResource::getRestoreAuthorizationResponse($row)->denied());
        $this->assertTrue(ActivityResource::getRestoreAnyAuthorizationResponse()->denied());
    }

    public function test_no_permissions_denies_viewing(): void
    {
        $this->actingAsUserWithPermissions([]);

        $this->assertFalse(ActivityResource::canViewAny());
        $this->assertSame('activity', ActivityResource::getPermissionPrefix());
    }

    public function test_seeder_lists_the_full_activity_action_set(): void
    {
        $seeder = file_get_contents(database_path('seeders/PermissionSeeder.php'));

        foreach (['view', 'create', 'edit', 'delete', 'restore'] as $action) {
            $this->assertStringContainsString("'activity.{$action}'", $seeder);
        }
    }

    // View-only surface

    public function test_resource_is_view_only_with_no_header_row_or_bulk_write_action(): void
    {
        $this->actingAsUserWithPermissions(['activity.view', 'activity.delete', 'activity.restore', ...self::MODULE_VIEWS]);
        $record = $this->auditRow(PurchaseRequest::factory()->create());

        $livewire = Livewire::test(ManageActivities::class);

        $livewire->assertActionDoesNotExist('create');
        $livewire->assertTableActionExists('view');
        $livewire->assertTableActionDoesNotExist('edit');
        $livewire->assertTableActionDoesNotExist('delete');
        $livewire->assertTableActionDoesNotExist('restore');
        $livewire->assertTableBulkActionDoesNotExist('delete');
        $this->assertSame([], $livewire->instance()->getTable()->getBulkActions());
        $this->assertArrayNotHasKey('trashed', $livewire->instance()->getTable()->getFilters());
        $livewire->assertCanSeeTableRecords([$record]);
    }

    public function test_view_modal_footer_never_offers_edit_delete_or_create(): void
    {
        $this->actingAsUserWithPermissions(['activity.view', 'activity.create', 'activity.edit', 'activity.delete', ...self::MODULE_VIEWS]);
        $record = $this->auditRow(PurchaseRequest::factory()->create());

        $component = Livewire::test(ManageActivities::class)->callTableAction('view', $record)->instance();
        $action = $component->getTable()->getAction('view')->record($record);

        foreach ($action->getExtraModalFooterActions() as $footerAction) {
            $this->assertFalse($footerAction->isVisible(), $footerAction->getName());
        }
    }

    public function test_view_modal_renders_old_and_new_values_per_field(): void
    {
        $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);
        $record = PurchaseRequest::factory()->create(['urgency_level' => 'low']);
        $record->update(['urgency_level' => 'high']);
        $row = $this->auditRow($record, 'updated');

        Livewire::test(ManageActivities::class)
            ->mountTableAction('view', $row)
            ->assertSuccessful();

        $fields = app(AuditChanges::class)->fields(collect([$row]))[$row->id];

        $this->assertSame([['label' => 'Urgency Level', 'old' => 'low', 'new' => 'high']], $fields);
    }

    // Scope to log_name = audit

    public function test_table_lists_audit_rows_only_never_calendar_rows(): void
    {
        $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);
        $record = PurchaseRequest::factory()->create();
        $audit = $this->auditRow($record);
        $rule = CalendarRule::factory()->create();
        app(CalendarActivity::class)->log('matched', $record, $rule, ['label' => 'x']);
        $calendar = Activity::query()->inLog('calendar')->where('subject_id', $record->id)->where('subject_type', $record->getMorphClass())->latest('id')->first();

        Livewire::test(ManageActivities::class)
            ->assertCanSeeTableRecords([$audit])
            ->assertCanNotSeeTableRecords([$calendar]);

        $this->assertSame(0, ActivityResource::getEloquentQuery()->where('log_name', '!=', 'audit')->count());
    }

    public function test_rows_are_limited_to_modules_the_viewer_may_view(): void
    {
        $payment = $this->auditRow(Payment::factory()->create());
        $request = $this->auditRow(PurchaseRequest::factory()->create());

        $this->actingAsUserWithPermissions(['activity.view']);
        Livewire::test(ManageActivities::class)
            ->assertCanNotSeeTableRecords([$payment, $request]);
        $this->assertSame([], app(AuditChanges::class)->viewableModels());
        $this->assertSame([], ActivityResource::getSubjectTypeFilter()->getOptions());

        $this->actingAsUserWithPermissions(['activity.view', 'payment.view']);
        Livewire::test(ManageActivities::class)
            ->assertCanSeeTableRecords([$payment])
            ->assertCanNotSeeTableRecords([$request]);
        $this->assertSame([Payment::class], app(AuditChanges::class)->viewableModels());
    }

    public function test_a_soft_deleted_causer_is_still_named_in_the_table_and_the_tab(): void
    {
        $causer = $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);
        $record = PurchaseRequest::factory()->create();
        $row = $this->auditRow($record);
        $causer->delete();
        $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);

        $loaded = ActivityResource::getEloquentQuery()->whereKey($row->id)->first();
        $method = new ReflectionMethod(InfoComponents::class, 'auditRows');
        $method->setAccessible(true);

        $this->assertSame($causer->name, $loaded->causer->name);
        $this->assertSame($causer->name, $method->invoke(null, $record)[0]['causer']);
        $this->assertArrayHasKey($causer->id, ActivityResource::getCauserFilter()->getOptions());
    }

    public function test_table_renders_mixed_models_without_lazy_loading(): void
    {
        $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);
        $rows = collect([PurchaseRequest::factory()->create(), Payment::factory()->create(), Shipment::factory()->create()])
            ->map(fn (Model $record): Activity => $this->auditRow($record));
        $rows->last()->subject->delete();

        Model::preventLazyLoading();

        try {
            Livewire::test(ManageActivities::class)
                ->assertCanSeeTableRecords($rows)
                ->assertSuccessful();
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_table_columns_show_record_identifier_event_and_changed_fields(): void
    {
        $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);
        $record = PurchaseRequest::factory()->create();
        $record->update(['urgency_level' => 'changed']);
        $row = $this->auditRow($record, 'updated')->load('subject');

        $this->assertSame($record->pr_number, app(AuditChanges::class)->record($row));
        $this->assertSame('Urgency Level', app(AuditChanges::class)->summary($row));
        $this->assertSame('warning', ActivityResource::eventColor('updated'));
        $this->assertSame('Updated', ActivityResource::eventLabels()['updated']);
        $this->assertTrue(ActivityResource::showEvent()->isBadge());
    }

    // Filters

    public function test_subject_type_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);
        $pr = $this->auditRow(PurchaseRequest::factory()->create());
        $payment = $this->auditRow(Payment::factory()->create());

        Livewire::test(ManageActivities::class)
            ->filterTable('module', PurchaseRequest::class)
            ->assertCanSeeTableRecords([$pr])
            ->assertCanNotSeeTableRecords([$payment]);

        $this->assertCount(8, ActivityResource::getSubjectTypeFilter()->getOptions());
    }

    public function test_event_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);
        $record = PurchaseRequest::factory()->create();
        $record->update(['urgency_level' => 'changed']);
        $created = $this->auditRow($record);
        $updated = $this->auditRow($record, 'updated');

        Livewire::test(ManageActivities::class)
            ->filterTable('event', 'updated')
            ->assertCanSeeTableRecords([$updated])
            ->assertCanNotSeeTableRecords([$created]);
    }

    public function test_causer_filter_narrows_the_table(): void
    {
        $first = $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);
        $mine = $this->auditRow(PurchaseRequest::factory()->create());
        $second = $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);
        $theirs = $this->auditRow(PurchaseRequest::factory()->create());
        $this->actingAs($first);

        Livewire::test(ManageActivities::class)
            ->filterTable('causer_id', $second->id)
            ->assertCanSeeTableRecords([$theirs])
            ->assertCanNotSeeTableRecords([$mine]);
    }

    public function test_date_range_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);
        $recent = $this->auditRow(PurchaseRequest::factory()->create());
        $old = $this->auditRow(PurchaseRequest::factory()->create());
        Activity::query()->whereKey($old->id)->update(['created_at' => now()->subDays(30)]);

        Livewire::test(ManageActivities::class)
            ->filterTable('created_at', ['created_from' => now()->subDays(2)->toDateString(), 'created_until' => null])
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$old->refresh()]);
    }

    // Activity tab on the eight operational infolists

    public function test_every_audited_resource_infolist_has_one_merged_activities_tab_and_no_separate_audit_tab(): void
    {
        app()->setLocale('en');

        foreach ([PurchaseRequestResource::class, RegisteredOrderResource::class, PurchaseOrderResource::class, PaymentResource::class, ShipmentResource::class, CustomResource::class, BankProfileResource::class, ProformaInvoiceResource::class] as $resource) {
            $tabsComponent = $resource::infolist(Schema::make())->getComponents()[0];
            $reflection = new ReflectionProperty($tabsComponent, 'childComponents');
            $reflection->setAccessible(true);
            $labels = collect($reflection->getValue($tabsComponent)['default'])->map(function ($tab) {
                $labelReflection = new ReflectionProperty($tab, 'label');
                $labelReflection->setAccessible(true);

                return $labelReflection->getValue($tab);
            })->filter(fn ($label) => is_string($label))->values();

            $merged = __('resources/general/strings.status_history.tab_label');

            $this->assertSame(1, $labels->filter(fn ($label) => $label === $merged)->count(), $resource);
            $this->assertFalse($labels->search(__('resources/general/strings.audit.tab_label')), $resource);
        }
    }

    public function test_every_operational_view_modal_renders_the_merged_tab_with_and_without_activity_view(): void
    {
        $resources = [PurchaseRequestResource::class, ProformaInvoiceResource::class, RegisteredOrderResource::class, PurchaseOrderResource::class, PaymentResource::class, ShipmentResource::class, CustomResource::class, BankProfileResource::class, \App\Filament\Resources\CorrespondenceResource::class];

        foreach ([[], ['activity.view']] as $extra) {
            $this->actingAsUserWithPermissions([...self::MODULE_VIEWS, 'correspondence.view', ...$extra]);

            foreach ($resources as $resource) {
                $record = $resource::getModel()::factory()->create();

                Livewire::test($resource::getPages()['index']->getPage())
                    ->mountTableAction('view', $record)
                    ->assertHasNoErrors();
            }
        }
    }

    public function test_correspondence_infolist_has_no_activity_tab(): void
    {
        $tabsComponent = \App\Filament\Resources\CorrespondenceResource::infolist(Schema::make())->getComponents()[0];
        $reflection = new ReflectionProperty($tabsComponent, 'childComponents');
        $reflection->setAccessible(true);

        foreach ($reflection->getValue($tabsComponent)['default'] as $tab) {
            $labelReflection = new ReflectionProperty($tab, 'label');
            $labelReflection->setAccessible(true);

            $this->assertNotSame(__('resources/general/strings.audit.tab_label'), $labelReflection->getValue($tab));
        }
    }

    public function test_the_change_log_section_needs_the_activity_view_permission_and_the_badge_adds_audit_rows_to_history(): void
    {
        $record = PurchaseRequest::factory()->create();
        $record->update(['urgency_level' => 'changed']);
        $tab = $this->auditTab(PurchaseRequestResource::infolist(Schema::make()));
        $badge = new ReflectionProperty($tab, 'badge');
        $badge->setAccessible(true);
        $history = $record->statusHistories()->count();

        $this->actingAsUserWithPermissions([]);
        $this->assertSame($history ?: null, ($badge->getValue($tab))($record));

        $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);
        $this->assertSame($history + 2, ($badge->getValue($tab))($record));
    }

    public function test_change_log_rows_are_empty_without_the_activity_view_permission_and_for_models_without_an_audit_log(): void
    {
        $record = PurchaseRequest::factory()->create(['urgency_level' => 'low']);
        $record->update(['urgency_level' => 'high']);
        $method = new ReflectionMethod(InfoComponents::class, 'auditRows');
        $method->setAccessible(true);

        $this->actingAsUserWithPermissions(self::MODULE_VIEWS);
        $this->assertSame([], $method->invoke(null, $record));

        $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);
        $this->assertSame([], $method->invoke(null, new \App\Models\Correspondence));
    }

    public function test_activity_tab_rows_list_events_newest_first_with_old_to_new_lines(): void
    {
        $this->actingAsUserWithPermissions(['activity.view', ...self::MODULE_VIEWS]);
        $record = PurchaseRequest::factory()->create(['urgency_level' => 'low']);
        $record->update(['urgency_level' => 'high']);
        $method = new ReflectionMethod(InfoComponents::class, 'auditRows');
        $method->setAccessible(true);

        $rows = $method->invoke(null, $record);

        $this->assertSame(['updated', 'created'], array_column($rows, 'event'));
        $this->assertSame(['Urgency Level: low | high'], $rows[0]['changes']);
        $this->assertSame([], $method->invoke(null, null));
    }
}
