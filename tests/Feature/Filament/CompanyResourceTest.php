<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CompanyResource;
use App\Filament\Resources\Master\CompanyResource\Exports\CompanyExporter;
use App\Filament\Resources\Master\CompanyResource\Imports\CompanyImporter;
use App\Filament\Resources\Master\CompanyResource\Pages\ManageCompanies;
use App\Models\Company;
use App\Models\Permission;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyResourceTest extends TestCase
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
            'company.view',
            'company.create',
            'company.edit',
            'company.delete',
            'company.restore',
        ]);

        $record = Company::factory()->create();

        $this->assertTrue(CompanyResource::canViewAny());
        $this->assertTrue(CompanyResource::canCreate());
        $this->assertTrue(CompanyResource::canEdit($record));
        $this->assertTrue(CompanyResource::canDelete($record));
        $this->assertTrue(CompanyResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = Company::factory()->create();

        $this->assertFalse(CompanyResource::canViewAny());
        $this->assertFalse(CompanyResource::canCreate());
        $this->assertFalse(CompanyResource::canEdit($record));
        $this->assertFalse(CompanyResource::canDelete($record));
        $this->assertFalse(CompanyResource::canRestore($record));
    }

    // List — search

    public function test_manage_page_renders_and_search_finds_by_english_name(): void
    {
        $this->actingAsUserWithPermissions(['company.view']);

        $term = 'SearchTarget'.uniqid();
        $target = Company::factory()->create(['english_name' => $term]);
        $other = Company::factory()->create();

        Livewire::test(ManageCompanies::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Filters

    public function test_active_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['company.view']);

        $active = Company::factory()->create();
        $inactive = Company::factory()->inactive()->create();

        Livewire::test(ManageCompanies::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_company_type_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['company.view']);

        $seller = Company::factory()->seller()->create();
        $buyer = Company::factory()->buyer()->create();

        Livewire::test(ManageCompanies::class)
            ->filterTable('company_types', ['types' => [Company::TYPE_SELLER]])
            ->assertCanSeeTableRecords([$seller])
            ->assertCanNotSeeTableRecords([$buyer]);
    }

    public function test_no_types_filter_shows_only_companies_with_no_assigned_types(): void
    {
        $this->actingAsUserWithPermissions(['company.view']);

        $withTypes = Company::factory()->seller()->create();
        $withoutTypes = Company::factory()->create(['types' => null]);

        Livewire::test(ManageCompanies::class)
            ->filterTable('no_types')
            ->assertCanSeeTableRecords([$withoutTypes])
            ->assertCanNotSeeTableRecords([$withTypes]);
    }

    public function test_creator_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['company.view']);
        $withA = Company::factory()->create();

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $withB = Company::factory()->create();

        $this->actingAs($userA);

        Livewire::test(ManageCompanies::class)
            ->filterTable('user_id', $userA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    // Create — happy path + validation

    public function test_create_action_persists_a_new_company(): void
    {
        $this->actingAsUserWithPermissions(['company.view', 'company.create']);

        Livewire::test(ManageCompanies::class)
            ->mountAction('create')
            ->fillForm([
                'name' => 'شرکت تست ایجاد',
                'english_name' => 'Create Test Company '.uniqid(),
                'description' => 'A freshly created test company',
                'is_active' => true,
                'types' => [Company::TYPE_SELLER, Company::TYPE_BUYER],
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('companies', [
            'name' => 'شرکت تست ایجاد',
        ]);
    }

    public function test_create_action_requires_name_and_english_name(): void
    {
        $this->actingAsUserWithPermissions(['company.view', 'company.create']);

        Livewire::test(ManageCompanies::class)
            ->mountAction('create')
            ->fillForm([
                'name' => '',
                'english_name' => '',
            ])
            ->callMountedAction()
            ->assertHasFormErrors(['name' => 'required', 'english_name' => 'required']);
    }

    public function test_create_action_rejects_non_persian_characters_in_the_persian_name(): void
    {
        $this->actingAsUserWithPermissions(['company.view', 'company.create']);

        Livewire::test(ManageCompanies::class)
            ->mountAction('create')
            ->fillForm([
                'name' => 'English Only Name',
                'english_name' => 'Valid English Name '.uniqid(),
            ])
            ->callMountedAction()
            ->assertHasFormErrors(['name' => 'regex']);
    }

    public function test_create_action_rejects_a_duplicate_english_name(): void
    {
        $this->actingAsUserWithPermissions(['company.view', 'company.create']);
        $existing = Company::factory()->create();

        Livewire::test(ManageCompanies::class)
            ->mountAction('create')
            ->fillForm([
                'name' => 'شرکت دوم',
                'english_name' => $existing->english_name,
            ])
            ->callMountedAction()
            ->assertHasFormErrors(['english_name' => 'unique']);
    }

    // Edit

    public function test_edit_action_persists_field_changes(): void
    {
        $this->actingAsUserWithPermissions(['company.view', 'company.edit']);
        $record = Company::factory()->create(['name' => 'شرکت ویرایش تست']);

        Livewire::test(ManageCompanies::class)
            ->mountTableAction('edit', $record)
            ->fillForm([
                'name' => $record->name,
                'english_name' => $record->english_name,
                'description' => 'Updated via edit action',
                'types' => [Company::TYPE_MANUFACTURER],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $record->refresh();

        $this->assertSame('Updated via edit action', $record->description);
        $this->assertSame([Company::TYPE_MANUFACTURER], $record->types);
    }

    // Infolist

    public function test_view_action_renders_the_infolist_without_errors(): void
    {
        $this->actingAsUserWithPermissions(['company.view']);
        $record = Company::factory()->seller()->create();

        Livewire::test(ManageCompanies::class)
            ->mountTableAction('view', $record)
            ->assertSuccessful();
    }

    // Bulk actions — toolbar order

    public function test_bulk_actions_toolbar_orders_export_activate_deactivate_before_delete_and_restore(): void
    {
        $this->actingAsUserWithPermissions(['company.view', 'company.delete', 'company.restore']);

        Livewire::test(ManageCompanies::class)
            ->assertTableBulkActionsExistInOrder(['exportCompanies', 'activate', 'deactivate', 'delete', 'restore']);
    }

    public function test_activate_and_deactivate_bulk_actions_flip_is_active_when_unused(): void
    {
        $this->actingAsUserWithPermissions(['company.view', 'company.edit']);
        $record = Company::factory()->create();

        Livewire::test(ManageCompanies::class)
            ->callTableBulkAction('deactivate', [$record]);

        $this->assertFalse((bool) $record->fresh()->is_active);

        Livewire::test(ManageCompanies::class)
            ->callTableBulkAction('activate', [$record]);

        $this->assertTrue((bool) $record->fresh()->is_active);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['company.view', 'company.delete', 'company.restore']);
        $record = Company::factory()->create();

        Livewire::test(ManageCompanies::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Company::find($record->id));
        $this->assertTrue(Company::withTrashed()->find($record->id)->trashed());

        Livewire::test(ManageCompanies::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(Company::find($record->id));
    }

    // Global search contract

    public function test_global_search_title_uses_office_emoji_prefix_and_localized_name(): void
    {
        $record = Company::factory()->create();

        $this->assertStringContainsString($record->getLocalizedNameAttribute(), CompanyResource::getGlobalSearchResultTitle($record));
        $this->assertStringStartsWith('🏢', CompanyResource::getGlobalSearchResultTitle($record));
    }

    // Usage guard — delete

    public function test_delete_action_succeeds_when_the_company_is_unused(): void
    {
        $this->actingAsUserWithPermissions(['company.view', 'company.delete']);
        $record = Company::factory()->create();

        Livewire::test(ManageCompanies::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Company::find($record->id));
    }

    public function test_delete_action_is_blocked_with_a_notification_when_the_company_is_referenced(): void
    {
        $this->actingAsUserWithPermissions(['company.view', 'company.delete']);
        $record = Company::factory()->create();
        RegisteredOrder::factory()->create(['seller_id' => $record->id]);

        Livewire::test(ManageCompanies::class)
            ->callTableAction('delete', $record)
            ->assertNotified(__('resources/general/strings.usage_guard.blocked', ['count' => 1]));

        $this->assertNotNull(Company::find($record->id));
    }

    public function test_delete_bulk_action_is_blocked_when_a_selected_company_is_referenced(): void
    {
        $this->actingAsUserWithPermissions(['company.view', 'company.delete']);
        $record = Company::factory()->create();
        RegisteredOrder::factory()->create(['buyer_id' => $record->id]);

        Livewire::test(ManageCompanies::class)
            ->callTableBulkAction('delete', [$record])
            ->assertNotified(__('resources/general/strings.usage_guard.blocked', ['count' => 1]));

        $this->assertNotNull(Company::find($record->id));
    }

    // Usage guard — deactivate

    public function test_deactivate_bulk_action_succeeds_when_the_company_is_unused(): void
    {
        $this->actingAsUserWithPermissions(['company.view', 'company.edit']);
        $record = Company::factory()->create();

        Livewire::test(ManageCompanies::class)
            ->callTableBulkAction('deactivate', [$record]);

        $this->assertFalse((bool) $record->fresh()->is_active);
    }

    public function test_deactivate_bulk_action_is_blocked_when_the_company_is_referenced(): void
    {
        $this->actingAsUserWithPermissions(['company.view', 'company.edit']);
        $record = Company::factory()->create();
        RegisteredOrder::factory()->create(['seller_id' => $record->id]);

        Livewire::test(ManageCompanies::class)
            ->callTableBulkAction('deactivate', [$record])
            ->assertNotified(__('resources/general/strings.usage_guard.blocked', ['count' => 1]));

        $this->assertTrue((bool) $record->fresh()->is_active);
    }

    // Export — bulk action dispatches the queued job

    public function test_export_bulk_action_dispatches_the_queued_export_job(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['company.view']);

        $record = Company::factory()->create();

        Livewire::test(ManageCompanies::class)
            ->callTableBulkAction('exportCompanies', [$record]);

        Queue::assertPushed(\App\Jobs\ExportCompanies::class);
    }

    // Import / Export — flat single-row bulk transfer

    private function importColumnMap(): array
    {
        $names = collect(CompanyImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeImporter(array $data, array $options = []): CompanyImporter
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $importer = new CompanyImporter(new Import, $this->importColumnMap(), array_merge([
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
            'english_name' => '',
            'name' => '',
            'description' => '',
            'is_active' => '',
            'types' => '',
        ], $overrides);
    }

    public function test_import_creates_a_new_company_and_defaults_is_active_to_true_when_blank(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'english_name' => 'Import Co '.uniqid(),
            'name' => 'شرکت وارداتی',
        ]));

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertSame('شرکت وارداتی', $record->name);
        $this->assertTrue((bool) $record->fresh()->is_active);
    }

    public function test_import_rejects_a_new_row_with_a_blank_english_name(): void
    {
        $this->expectException(\Filament\Actions\Imports\Exceptions\RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow(['name' => 'شرکت']));
    }

    public function test_import_rejects_a_new_row_with_a_blank_name(): void
    {
        $this->expectException(\Filament\Actions\Imports\Exceptions\RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow(['english_name' => 'Needs A Name '.uniqid()]));
    }

    public function test_import_rejects_a_duplicate_english_name(): void
    {
        $existing = Company::factory()->create();

        $this->expectException(\Filament\Actions\Imports\Exceptions\RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow([
            'english_name' => $existing->english_name,
            'name' => 'شرکت تکراری',
        ]));
    }

    public function test_import_drops_an_unrecognized_type_and_logs_a_note_without_rejecting_the_row(): void
    {
        Log::partialMock();
        Log::shouldReceive('warning')->once();

        $importer = $this->invokeImporter($this->baseImportRow([
            'english_name' => 'Mixed Types Co '.uniqid(),
            'name' => 'شرکت انواع مختلط',
            'types' => 'seller, not_a_real_type',
        ]));

        $record = $importer->getRecord();

        $this->assertSame([Company::TYPE_SELLER], $record->types);
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Pinned counts — a silent column drop during a future refactor must fail
        // this test, not slip through unnoticed (see importsPattern's settled policy).
        $this->assertCount(5, CompanyImporter::getColumns());
        $this->assertCount(5, CompanyImporter::columnLabels());
        $this->assertCount(10, CompanyExporter::columnLabels());
    }

    public function test_filled_example_file_imports_cleanly_end_to_end(): void
    {
        Log::partialMock();
        Log::shouldReceive('warning')->atLeast()->once();

        $fullPath = storage_path('app/'.CompanyImporter::filledExamplePath());
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
        $this->assertSame(['seller', 'buyer'], $imported[0]->getRecord()->types);
        $this->assertSame(['manufacturer'], $imported[1]->getRecord()->types);
        $this->assertTrue((bool) $imported[2]->getRecord()->fresh()->is_active);
        $this->assertNull($imported[2]->getRecord()->types);
    }

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'company_export_').'.csv';
        CompanyExporter::write($query, $path);

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
        $active = Company::factory()->seller()->create([
            'english_name' => 'Export Active Co',
        ]);

        $this->actingAs(User::factory()->create());
        $inactive = Company::factory()->inactive()->create();

        ['header' => $header, 'rows' => $rows] = $this->exportToRows(Company::whereIn('id', [$active->id, $inactive->id]));

        $labels = CompanyExporter::columnLabels();

        $this->assertCount(10, $header);
        $this->assertSame('Export Active Co', $rows[0][$labels['english_name']]);
        $this->assertSame('Seller', $rows[0][$labels['types']]);
        $this->assertSame(__('resources/company/strings.export.active'), $rows[0][$labels['is_active']]);
        $this->assertSame('Export Creator', $rows[0][$labels['creator']]);
        $this->assertSame(__('resources/company/strings.export.inactive'), $rows[1][$labels['is_active']]);
        $this->assertSame(jdate($active->created_at)->format('Y-m-d'), $rows[0][$labels['created_at']]);
    }
}
