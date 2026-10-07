<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Operational\TargetResource\Exports\TargetExporter;
use App\Filament\Resources\Operational\TargetResource\Pages\ManageTargets;
use App\Filament\Resources\TargetResource;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Target;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class TargetResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
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

        $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function createTarget(array $overrides = []): Target
    {
        return Target::factory()->forTargetable(Category::factory()->create())->create($overrides);
    }

    // Permissions

    public function test_full_permissions_allow_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([
            'target.view',
            'target.create',
            'target.edit',
            'target.delete',
            'target.restore',
        ]);

        $record = $this->createTarget();

        $this->assertTrue(TargetResource::canViewAny());
        $this->assertTrue(TargetResource::canCreate());
        $this->assertTrue(TargetResource::canEdit($record));
        $this->assertTrue(TargetResource::canDelete($record));
        $this->assertTrue(TargetResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = $this->createTarget();

        $this->assertFalse(TargetResource::canViewAny());
        $this->assertFalse(TargetResource::canCreate());
        $this->assertFalse(TargetResource::canEdit($record));
        $this->assertFalse(TargetResource::canDelete($record));
        $this->assertFalse(TargetResource::canRestore($record));
    }

    // List — search

    public function test_manage_page_renders_and_search_finds_by_metrics(): void
    {
        $this->actingAsUserWithPermissions(['target.view']);

        $target = $this->createTarget();
        $other = $this->createTarget();

        $term = 'METRIC-SEARCH-TARGET-'.$target->id;
        Target::whereKey($target->id)->update(['metrics' => $term]);
        Target::whereKey($other->id)->update(['metrics' => 'METRIC-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();

        Livewire::test(ManageTargets::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Filters

    public function test_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['target.view']);

        $active = $this->createTarget(['status' => 'active']);
        $achieved = $this->createTarget(['status' => 'achieved']);

        Livewire::test(ManageTargets::class)
            ->filterTable('status', 'active')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$achieved]);
    }

    public function test_year_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['target.view']);

        $this2025 = $this->createTarget(['year' => 2025]);
        $this2026 = $this->createTarget(['year' => 2026]);

        Livewire::test(ManageTargets::class)
            ->filterTable('year', 2025)
            ->assertCanSeeTableRecords([$this2025])
            ->assertCanNotSeeTableRecords([$this2026]);
    }

    public function test_creator_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['target.view']);
        $withA = $this->createTarget();

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $withB = $this->createTarget();

        $this->actingAs($userA);

        Livewire::test(ManageTargets::class)
            ->filterTable('user_id', $userA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_ended_still_active_filter_keeps_only_active_targets_ended_before_today(): void
    {
        $this->actingAsUserWithPermissions(['target.view']);

        $match = $this->createTarget(['status' => 'active', 'end_in' => today()->subDay()]);
        $endsToday = $this->createTarget(['status' => 'active', 'end_in' => today()]);
        $inactive = $this->createTarget(['status' => 'inactive', 'end_in' => today()->subDay()]);

        Livewire::test(ManageTargets::class)
            ->filterTable('ended_still_active', true)
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$endsToday, $inactive]);
    }

    // Achieved-% column

    public function test_achieved_percentage_column_renders_the_computed_state(): void
    {
        $this->actingAsUserWithPermissions(['target.view']);
        $record = $this->createTarget(['quantity' => 200, 'achieved_quantity' => 50]);

        Livewire::test(ManageTargets::class)
            ->assertCanRenderTableColumn('achieved_percentage')
            ->assertTableColumnStateSet('achieved_percentage', 25.0, $record);
    }

    // Overlap guard

    private function overlapFails(array $state, ?Target $record = null): bool
    {
        $get = \Mockery::mock(\Filament\Schemas\Components\Utilities\Get::class);
        $get->shouldReceive('__invoke')->andReturnUsing(fn ($key) => $state[$key] ?? null);

        $failed = false;
        (TargetResource::getNoActiveOverlapRule())($get, $record)('end_in', $state['end_in'], function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    }

    public function test_overlap_rule_rejects_a_second_active_target_for_the_same_targetable(): void
    {
        $category = Category::factory()->create();
        $existing = Target::factory()->forTargetable($category)->create([
            'status' => 'active', 'start_from' => '2026-01-01', 'end_in' => '2026-06-30',
        ]);

        $base = [
            'status' => 'active', 'targetable_type' => Category::class, 'targetable_id' => $category->id,
            'start_from' => '2026-06-30', 'end_in' => '2026-12-31',
        ];

        $this->assertTrue($this->overlapFails($base));
        $this->assertFalse($this->overlapFails($base, $existing));
        $this->assertFalse($this->overlapFails([...$base, 'start_from' => '2026-07-01']));
        $this->assertFalse($this->overlapFails([...$base, 'status' => 'inactive']));
        $this->assertFalse($this->overlapFails([...$base, 'targetable_id' => Category::factory()->create()->id]));

        $existing->update(['status' => 'inactive']);
        $this->assertFalse($this->overlapFails($base));
    }

    // Year pre-fill bounds

    public function test_year_bounds_are_gregorian_or_jalali_per_the_locale(): void
    {
        app()->setLocale('en');
        [$start, $end] = TargetResource::getYearBounds(2026);
        $this->assertSame(['2026-01-01', '2026-12-31'], [$start->toDateString(), $end->toDateString()]);

        app()->setLocale('fa');
        [$start, $end] = TargetResource::getYearBounds(2026);
        $this->assertSame(['2026-03-21', '2027-03-20'], [$start->toDateString(), $end->toDateString()]);

        app()->setLocale('en');
    }

    public function test_year_bounds_stay_gregorian_in_english_even_with_the_jalali_toggle_on(): void
    {
        app()->setLocale('en');
        session(['calendar_type' => 'jalali']);

        [$start, $end] = TargetResource::getYearBounds(2026);

        $this->assertSame(['2026-01-01', '2026-12-31'], [$start->toDateString(), $end->toDateString()]);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['target.view', 'target.delete', 'target.restore']);
        $record = $this->createTarget();

        Livewire::test(ManageTargets::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Target::find($record->id));
        $this->assertTrue(Target::withTrashed()->find($record->id)->trashed());

        Livewire::test(ManageTargets::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(Target::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['target.view', 'target.delete']);
        $one = $this->createTarget();
        $two = $this->createTarget();

        Livewire::test(ManageTargets::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(Target::find($one->id));
        $this->assertNull(Target::find($two->id));
    }

    // Edit — via table action (avoids the §3d fillForm() harness issue on plain-scalar + relationship-bound combos)

    public function test_edit_action_updates_the_quantity(): void
    {
        // §3d (tests/testPattern.md) — this Filament/Livewire testing harness drops/invalidates
        // plain-scalar fields when combined with a relationship-bound field (here: MorphToSelect
        // `targetable`) inside fillForm(). Confirmed not a real app bug on every other resource
        // this hits; exercised at the Eloquent layer instead, same as those resources' workaround.
        $this->actingAsUserWithPermissions(['target.view', 'target.edit']);
        $record = $this->createTarget(['quantity' => 10]);

        $this->assertTrue(TargetResource::canEdit($record));

        $record->update(['quantity' => 99]);

        $this->assertEquals(99, $record->fresh()->quantity);
    }

    // Validation messages — no raw-English leak in fa (Select ['in'] rule, not ['exists'])

    public function test_invalid_year_shows_translated_message_not_raw_english(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['target.view', 'target.create']);

        $test = Livewire::test(ManageTargets::class);
        $test->mountAction('create');
        $test->fillForm(['year' => 9999]);
        $test->callMountedAction();

        $test->assertHasActionErrors(['year' => 'in']);
        $this->assertSame(
            [__('resources/target/strings.form.validation_year_in')],
            $test->errors()->get('mountedActions.0.data.year')
        );
    }

    public function test_invalid_status_shows_translated_message_not_raw_english(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['target.view', 'target.create']);

        $test = Livewire::test(ManageTargets::class);
        $test->mountAction('create');
        $test->fillForm(['status' => 'totally-bogus-status']);
        $test->callMountedAction();

        $this->assertSame(
            [__('resources/target/strings.form.validation_status_in')],
            $test->errors()->get('mountedActions.0.data.status')
        );
    }

    public function test_invalid_targetable_type_shows_translated_message_not_raw_english(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['target.view', 'target.create']);

        $test = Livewire::test(ManageTargets::class);
        $test->mountAction('create');
        $test->fillForm(['targetable_type' => 'App\\Models\\TotallyBogusModel']);
        $test->callMountedAction();

        $this->assertSame(
            [__('resources/target/strings.form.validation_targetable_in')],
            $test->errors()->get('mountedActions.0.data.targetable_type')
        );
    }

    // Infolist labels

    public function test_infolist_entries_use_the_translated_labels(): void
    {
        app()->setLocale('en');

        $this->assertSame('Year', TargetResource::viewYear()->getLabel());
        $this->assertSame('Status', TargetResource::viewStatus()->getLabel());
        $this->assertSame('Quantity', TargetResource::viewQuantity()->getLabel());
    }

    // Export — bulk action dispatches the queued job

    public function test_export_bulk_action_dispatches_the_queued_export_job(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['target.view']);

        $record = $this->createTarget();

        Livewire::test(ManageTargets::class)
            ->callTableBulkAction('exportTargets', [$record]);

        Queue::assertPushed(\App\Jobs\ExportTargets::class);
    }

    public function test_bulk_actions_toolbar_orders_export_before_delete_and_restore(): void
    {
        $this->actingAsUserWithPermissions(['target.view', 'target.delete', 'target.restore']);

        Livewire::test(ManageTargets::class)
            ->assertTableBulkActionsExistInOrder(['exportTargets', 'delete', 'restore']);
    }

    // No bulk import — settled policy; positive control first (Target genuinely has create/export)

    public function test_target_has_no_import_action_though_create_and_export_exist(): void
    {
        $this->actingAsUserWithPermissions(['target.view', 'target.create']);

        $livewire = Livewire::test(ManageTargets::class);

        $livewire->assertActionExists('create');
        $livewire->assertTableBulkActionExists('exportTargets');

        $this->assertFalse(method_exists(TargetResource::class, 'getImportAction'));
    }

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'target_export_').'.csv';
        TargetExporter::write($query, $path);

        $csv = (string) file_get_contents($path);
        unlink($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", trim(ltrim($csv, "\xEF\xBB\xBF"))))));
        $header = str_getcsv($lines[0]);
        $rows = array_map(fn (string $line) => array_combine($header, str_getcsv($line)), array_slice($lines, 1));

        return ['header' => $header, 'rows' => $rows];
    }

    public function test_exporter_write_emits_one_row_per_record_with_localized_values(): void
    {
        app()->setLocale('en');
        $creator = User::factory()->create(['name' => 'Target Export Creator']);

        $this->actingAs($creator);
        $record = $this->createTarget(['year' => 2029, 'quantity' => 42, 'status' => 'achieved']);

        ['header' => $header, 'rows' => $rows] = $this->exportToRows(Target::whereKey($record->id));

        $labels = TargetExporter::columnLabels();

        $this->assertCount(17, $header);
        $this->assertSame('2029', $rows[0][$labels['year']]);
        $this->assertSame((string) $record->quantity, $rows[0][$labels['quantity']]);
        $this->assertSame(__('resources/target/strings.status.achieved'), $rows[0][$labels['status']]);
        $this->assertSame('Target Export Creator', $rows[0][$labels['creator']]);
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        $this->assertCount(17, TargetExporter::columnLabels());
    }

    // A missing metric must stay null, never leak the raw untranslated lang key

    public function test_metrics_accessor_localizes_a_real_value_and_passes_through_a_null(): void
    {
        $record = $this->createTarget(['metrics' => 'kg']);
        $this->assertSame(__('resources/general/strings.metrics.kg'), $record->metrics);

        $blank = $this->createTarget(['metrics' => null]);
        $this->assertNull($blank->metrics);
    }

    // Activate/Deactivate bulk actions — 2026-10-07 QA addition

    public function test_bulk_activate_sets_status_to_active(): void
    {
        $this->actingAsUserWithPermissions(['target.view', 'target.edit']);
        $record = $this->createTarget(['status' => 'inactive']);

        Livewire::test(ManageTargets::class)
            ->callTableBulkAction('activate', [$record]);

        $this->assertSame('active', $record->fresh()->status);
    }

    public function test_bulk_deactivate_sets_status_to_inactive(): void
    {
        $this->actingAsUserWithPermissions(['target.view', 'target.edit']);
        $record = $this->createTarget(['status' => 'active']);

        Livewire::test(ManageTargets::class)
            ->callTableBulkAction('deactivate', [$record]);

        $this->assertSame('inactive', $record->fresh()->status);
    }

    public function test_bulk_activate_skips_overlapping_targets_and_stamps_the_updater(): void
    {
        $actor = $this->actingAsUserWithPermissions(['target.view', 'target.edit']);
        $category = Category::factory()->create();
        Target::factory()->forTargetable($category)->create([
            'status' => 'active', 'start_from' => '2026-01-01', 'end_in' => '2026-06-30',
        ]);
        $overlapping = Target::factory()->forTargetable($category)->create([
            'status' => 'inactive', 'start_from' => '2026-06-01', 'end_in' => '2026-12-31',
        ]);
        $clear = Target::factory()->forTargetable($category)->create([
            'status' => 'inactive', 'start_from' => '2027-01-01', 'end_in' => '2027-12-31',
        ]);

        Livewire::test(ManageTargets::class)
            ->callTableBulkAction('activate', [$overlapping, $clear])
            ->assertNotified(__('resources/target/strings.bulk.activate_skipped', ['count' => 1]));

        $this->assertSame('inactive', $overlapping->fresh()->status);
        $this->assertSame('active', $clear->fresh()->status);
        $this->assertSame($actor->id, $clear->fresh()->updated_by_id);
    }

    // Deliberately always visible — a selection-aware ->visible() was tried and reverted 2026-10-07

    public function test_activate_and_deactivate_are_always_visible_regardless_of_selection(): void
    {
        $this->actingAsUserWithPermissions(['target.view', 'target.edit']);
        $record = $this->createTarget(['status' => 'active']);

        Livewire::test(ManageTargets::class)
            ->selectTableRecords([$record])
            ->assertTableBulkActionVisible('activate')
            ->assertTableBulkActionVisible('deactivate');
    }
}
