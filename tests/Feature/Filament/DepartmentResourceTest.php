<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\DepartmentResource;
use App\Filament\Resources\Master\DepartmentResource\Exports\DepartmentExporter;
use App\Filament\Resources\Master\DepartmentResource\Imports\DepartmentImporter;
use App\Filament\Resources\Master\DepartmentResource\Pages\ManageDepartments;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class DepartmentResourceTest extends TestCase
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

    // Permissions

    public function test_full_permissions_allow_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([
            'department.view',
            'department.create',
            'department.edit',
            'department.delete',
            'department.restore',
        ]);

        $record = Department::factory()->create();

        $this->assertTrue(DepartmentResource::canViewAny());
        $this->assertTrue(DepartmentResource::canCreate());
        $this->assertTrue(DepartmentResource::canEdit($record));
        $this->assertTrue(DepartmentResource::canDelete($record));
        $this->assertTrue(DepartmentResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = Department::factory()->create();

        $this->assertFalse(DepartmentResource::canViewAny());
        $this->assertFalse(DepartmentResource::canCreate());
        $this->assertFalse(DepartmentResource::canEdit($record));
        $this->assertFalse(DepartmentResource::canDelete($record));
        $this->assertFalse(DepartmentResource::canRestore($record));
    }

    // List — search

    public function test_manage_page_renders_and_search_finds_by_code(): void
    {
        $this->actingAsUserWithPermissions(['department.view']);

        $target = Department::factory()->create();
        $other = Department::factory()->create();

        $term = 'DEPT-SEARCH-TARGET-'.$target->id;
        Department::whereKey($target->id)->update(['code' => $term]);
        Department::whereKey($other->id)->update(['code' => 'DEPT-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();

        Livewire::test(ManageDepartments::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Filters

    public function test_active_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['department.view']);

        $active = Department::factory()->create();
        $inactive = Department::factory()->inactive()->create();

        Livewire::test(ManageDepartments::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_creator_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['department.view']);
        $withA = Department::factory()->create();

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $withB = Department::factory()->create();

        $this->actingAs($userA);

        Livewire::test(ManageDepartments::class)
            ->filterTable('user_id', $userA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    // Activation — bulk actions

    public function test_deactivate_and_activate_bulk_actions_flip_is_active(): void
    {
        $this->actingAsUserWithPermissions(['department.view', 'department.edit']);
        $record = Department::factory()->create();

        Livewire::test(ManageDepartments::class)
            ->callTableBulkAction('deactivate', [$record]);

        $this->assertFalse($record->fresh()->is_active);

        Livewire::test(ManageDepartments::class)
            ->callTableBulkAction('activate', [$record]);

        $this->assertTrue($record->fresh()->is_active);
    }

    public function test_activation_bulk_actions_are_hidden_without_the_edit_permission(): void
    {
        $this->actingAsUserWithPermissions(['department.view']);
        $record = Department::factory()->create();

        Livewire::test(ManageDepartments::class)
            ->assertTableBulkActionHidden('activate')
            ->assertTableBulkActionHidden('deactivate');
    }

    public function test_bulk_actions_toolbar_orders_export_activate_deactivate_before_delete_and_restore(): void
    {
        $this->actingAsUserWithPermissions(['department.view', 'department.delete', 'department.restore']);

        Livewire::test(ManageDepartments::class)
            ->assertTableBulkActionsExistInOrder(['exportDepartments', 'activate', 'deactivate', 'delete', 'restore']);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['department.view', 'department.delete', 'department.restore']);
        $record = Department::factory()->create();

        Livewire::test(ManageDepartments::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Department::find($record->id));
        $this->assertTrue(Department::withTrashed()->find($record->id)->trashed());

        Livewire::test(ManageDepartments::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(Department::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['department.view', 'department.delete']);
        $one = Department::factory()->create();
        $two = Department::factory()->create();

        Livewire::test(ManageDepartments::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(Department::find($one->id));
        $this->assertNull(Department::find($two->id));
    }

    // Global search contract

    public function test_global_search_title_uses_office_emoji_prefix_and_code(): void
    {
        $record = Department::factory()->create();

        $this->assertSame("🏢  {$record->name} (🔑 {$record->code})", DepartmentResource::getGlobalSearchResultTitle($record));
    }

    // Export — bulk action dispatches the queued job

    public function test_export_bulk_action_dispatches_the_queued_export_job(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['department.view']);

        $record = Department::factory()->create();

        Livewire::test(ManageDepartments::class)
            ->callTableBulkAction('exportDepartments', [$record]);

        Queue::assertPushed(\App\Jobs\ExportDepartments::class);
    }

    // Import / Export — flat single-row bulk transfer

    private function importColumnMap(): array
    {
        $names = collect(DepartmentImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeImporter(array $data, array $options = []): DepartmentImporter
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $importer = new DepartmentImporter(new Import, $this->importColumnMap(), array_merge([
            'locale' => 'en',
            'jalali' => false,
            'date_format' => 'Y-m-d',
        ], $options));

        $importer($data);

        return $importer;
    }

    private function baseImportRow(array $overrides): array
    {
        return array_merge([
            'code' => '',
            'name' => '',
            'english_name' => '',
            'description' => '',
        ], $overrides);
    }

    public function test_import_creates_a_new_record_with_code_auto_generated_when_blank(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'name' => 'واحد تست وارد کردن',
        ]));

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertMatchesRegularExpression('/^DEPT-\d{6}(-\d+)?$/', $record->code);
        $this->assertSame('واحد تست وارد کردن', $record->name);
        $this->assertTrue($record->fresh()->is_active);
    }

    public function test_import_reupload_of_existing_code_updates_in_place(): void
    {
        $row = $this->baseImportRow([
            'name' => 'مالی',
            'description' => 'First upload',
        ]);

        $first = $this->invokeImporter($row);
        $id = $first->getRecord()->id;

        $row['code'] = $first->getRecord()->code;
        $row['description'] = 'Second upload';

        $second = $this->invokeImporter($row);

        $this->assertSame($id, $second->getRecord()->id);
        $this->assertSame('Second upload', $second->getRecord()->description);
    }

    public function test_import_rejects_a_new_row_with_a_blank_name(): void
    {
        $this->expectException(\Filament\Actions\Imports\Exceptions\RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow([]));
    }

    public function test_filled_example_file_exists_and_matches_the_import_shape(): void
    {
        $path = DepartmentImporter::filledExamplePath();

        $this->assertNotNull($path);

        $fullPath = storage_path('app/'.$path);
        $this->assertFileExists($fullPath);

        $contents = file_get_contents($fullPath);

        $this->assertStringContainsString('(code)', $contents);
        $this->assertStringContainsString('(name)', $contents);
        $this->assertStringContainsString('(english_name)', $contents);
        $this->assertStringContainsString('(description)', $contents);
    }

    public function test_auto_generated_empty_example_has_a_real_header_for_every_import_column(): void
    {
        foreach (DepartmentImporter::getColumns() as $column) {
            $this->assertNotSame('', trim($column->getExampleHeader()), "Column [{$column->getName()}] has a blank example header.");
        }
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Pinned counts — a silent column drop during a future refactor must fail
        // this test, not slip through unnoticed (see importsPattern's settled policy).
        $this->assertCount(4, DepartmentImporter::getColumns());
        $this->assertCount(4, DepartmentImporter::columnLabels());
        $this->assertCount(10, DepartmentExporter::columnLabels());
    }

    public function test_filled_example_file_imports_cleanly_end_to_end(): void
    {
        $fullPath = storage_path('app/'.DepartmentImporter::filledExamplePath());
        $csv = \League\Csv\Reader::createFromPath($fullPath, 'r');
        $csv->setHeaderOffset(0);

        $imported = [];
        foreach ($csv->getRecords() as $row) {
            $mapped = [];
            foreach ($row as $header => $value) {
                if (preg_match('/\(([a-z_]+)\)$/', $header, $matches)) {
                    $mapped[$matches[1]] = $value;
                }
            }
            $imported[] = $this->invokeImporter($mapped);
        }

        $this->assertCount(3, $imported);
        $this->assertMatchesRegularExpression('/^DEPT-\d{6}(-\d+)?$/', $imported[0]->getRecord()->code);
        $this->assertSame('DEPT-0001', $imported[1]->getRecord()->code);
        $this->assertSame('Sales & Marketing', $imported[1]->getRecord()->english_name);
        $this->assertNull($imported[2]->getRecord()->description);
    }

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'dept_export_').'.csv';
        DepartmentExporter::write($query, $path);

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
        $creator = User::factory()->create(['name' => 'Export Creator']);

        $this->actingAs($creator);
        $active = Department::factory()->create([
            'code' => 'DEPT-EXP-1',
            'name' => 'واحد صادرات',
            'english_name' => 'Export Department',
        ]);

        $this->actingAs(User::factory()->create());
        $inactive = Department::factory()->inactive()->create(['code' => 'DEPT-EXP-2']);

        ['header' => $header, 'rows' => $rows] = $this->exportToRows(Department::whereIn('id', [$active->id, $inactive->id]));

        $labels = DepartmentExporter::columnLabels();

        $this->assertCount(10, $header);
        $this->assertSame('DEPT-EXP-1', $rows[0][$labels['code']]);
        $this->assertSame('Export Department', $rows[0][$labels['english_name']]);
        $this->assertSame(__('resources/department/strings.export.active'), $rows[0][$labels['is_active']]);
        $this->assertSame('Export Creator', $rows[0][$labels['creator']]);
        $this->assertSame(__('resources/department/strings.export.inactive'), $rows[1][$labels['is_active']]);
        $this->assertSame(jdate($active->created_at)->format('Y-m-d'), $rows[0][$labels['created_at']]);
    }
}
