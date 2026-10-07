<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BankResource;
use App\Filament\Resources\Master\BankResource\Exports\BankExporter;
use App\Filament\Resources\Master\BankResource\Imports\BankImporter;
use App\Filament\Resources\Master\BankResource\Pages\ManageBanks;
use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BankResourceTest extends TestCase
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
            'bank.view',
            'bank.create',
            'bank.edit',
            'bank.delete',
            'bank.restore',
        ]);

        $record = Bank::factory()->create();

        $this->assertTrue(BankResource::canViewAny());
        $this->assertTrue(BankResource::canCreate());
        $this->assertTrue(BankResource::canEdit($record));
        $this->assertTrue(BankResource::canDelete($record));
        $this->assertTrue(BankResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = Bank::factory()->create();

        $this->assertFalse(BankResource::canViewAny());
        $this->assertFalse(BankResource::canCreate());
        $this->assertFalse(BankResource::canEdit($record));
        $this->assertFalse(BankResource::canDelete($record));
        $this->assertFalse(BankResource::canRestore($record));
    }

    // List — search

    public function test_manage_page_renders_and_search_finds_by_english_name(): void
    {
        $this->actingAsUserWithPermissions(['bank.view']);

        $target = Bank::factory()->create();
        $other = Bank::factory()->create();

        $term = 'BANK-SEARCH-TARGET-'.$target->id;
        Bank::whereKey($target->id)->update(['english_name' => $term]);
        Bank::whereKey($other->id)->update(['english_name' => 'BANK-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();

        Livewire::test(ManageBanks::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Filters

    public function test_active_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['bank.view']);

        $active = Bank::factory()->create();
        $inactive = Bank::factory()->inactive()->create();

        Livewire::test(ManageBanks::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_creator_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['bank.view']);
        $withA = Bank::factory()->create();

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $withB = Bank::factory()->create();

        $this->actingAs($userA);

        Livewire::test(ManageBanks::class)
            ->filterTable('user_id', $userA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    // Create — happy path + validation

    public function test_create_happy_path_saves_a_new_bank(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.create']);

        Livewire::test(ManageBanks::class)
            ->mountAction('create')
            ->fillForm([
                'name' => 'بانک تست ایجاد',
                'english_name' => 'Create Test Bank '.uniqid(),
                'description' => 'یک بانک تستی',
                'is_active' => true,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('banks', ['name' => 'بانک تست ایجاد']);
    }

    public function test_create_validation_requires_name_and_english_name(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.create']);

        Livewire::test(ManageBanks::class)
            ->mountAction('create')
            ->fillForm(['name' => '', 'english_name' => ''])
            ->callMountedAction()
            ->assertHasActionErrors(['name' => 'required', 'english_name' => 'required']);
    }

    public function test_create_validation_rejects_wrong_script_for_name_and_english_name(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.create']);

        Livewire::test(ManageBanks::class)
            ->mountAction('create')
            ->fillForm(['name' => 'Not Persian Script', 'english_name' => 'نه اسکریپت انگلیسی'])
            ->callMountedAction()
            ->assertHasActionErrors(['name' => 'regex', 'english_name' => 'regex']);
    }

    public function test_create_validation_rejects_a_duplicate_name_or_english_name(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.create']);

        $existing = Bank::factory()->create([
            'name' => 'بانک یکتای موجود',
            'english_name' => 'Existing Unique Bank',
        ]);

        Livewire::test(ManageBanks::class)
            ->mountAction('create')
            ->fillForm([
                'name' => $existing->name,
                'english_name' => $existing->english_name,
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['name' => 'unique', 'english_name' => 'unique']);
    }

    // Edit

    public function test_edit_action_persists_changes(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.edit']);
        $record = Bank::factory()->create();

        Livewire::test(ManageBanks::class)
            ->mountTableAction('edit', $record)
            ->fillForm(['description' => 'توضیحات ویرایش‌شده'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('توضیحات ویرایش‌شده', $record->fresh()->description);
    }

    // Infolist

    public function test_view_action_mounts_the_infolist_without_errors(): void
    {
        $this->actingAsUserWithPermissions(['bank.view']);
        $record = Bank::factory()->create();

        Livewire::test(ManageBanks::class)
            ->mountTableAction('view', $record)
            ->assertSuccessful();
    }

    // Activation — bulk actions

    public function test_deactivate_and_activate_bulk_actions_flip_is_active(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.edit']);
        $record = Bank::factory()->create();

        Livewire::test(ManageBanks::class)
            ->callTableBulkAction('deactivate', [$record]);

        $this->assertFalse($record->fresh()->is_active);

        Livewire::test(ManageBanks::class)
            ->callTableBulkAction('activate', [$record]);

        $this->assertTrue($record->fresh()->is_active);
    }

    public function test_bulk_actions_toolbar_orders_export_activate_deactivate_before_delete_and_restore(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.delete', 'bank.restore']);

        Livewire::test(ManageBanks::class)
            ->assertTableBulkActionsExistInOrder(['exportBanks', 'activate', 'deactivate', 'delete', 'restore']);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.delete', 'bank.restore']);
        $record = Bank::factory()->create();

        Livewire::test(ManageBanks::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Bank::find($record->id));
        $this->assertTrue(Bank::withTrashed()->find($record->id)->trashed());

        Livewire::test(ManageBanks::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(Bank::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.delete']);
        $one = Bank::factory()->create();
        $two = Bank::factory()->create();

        Livewire::test(ManageBanks::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(Bank::find($one->id));
        $this->assertNull(Bank::find($two->id));
    }

    // Global search contract

    public function test_global_search_title_uses_bank_emoji_prefix_and_date(): void
    {
        $record = Bank::factory()->create();

        $expected = '🏦  '.$record->name.' (📆 '.$record->created_at->format('Y-m-d').')';

        $this->assertSame($expected, BankResource::getGlobalSearchResultTitle($record));
    }

    // Export — bulk action dispatches the queued job

    public function test_export_bulk_action_dispatches_the_queued_export_job(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['bank.view']);

        $record = Bank::factory()->create();

        Livewire::test(ManageBanks::class)
            ->callTableBulkAction('exportBanks', [$record]);

        Queue::assertPushed(\App\Jobs\ExportBanks::class);
    }

    // Import / Export — flat single-row bulk transfer, create-only reject-on-duplicate

    private function importColumnMap(): array
    {
        $names = collect(BankImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeImporter(array $data, array $options = []): BankImporter
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $importer = new BankImporter(new Import, $this->importColumnMap(), array_merge([
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
            'name' => '',
            'english_name' => '',
            'description' => '',
            'is_active' => '',
        ], $overrides);
    }

    public function test_import_creates_a_new_record_and_defaults_is_active_to_true_when_blank(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'name' => 'بانک وارداتی',
            'english_name' => 'Imported Bank '.uniqid(),
        ]));

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertSame('بانک وارداتی', $record->name);
        $this->assertTrue($record->fresh()->is_active);
    }

    public function test_import_normalizes_common_boolean_spellings_for_is_active(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'name' => 'بانک غیرفعال',
            'english_name' => 'Inactive Import Bank '.uniqid(),
            'is_active' => 'false',
        ]));

        $this->assertFalse($importer->getRecord()->fresh()->is_active);
    }

    public function test_import_rejects_a_new_row_with_a_blank_name(): void
    {
        $this->expectException(RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow(['english_name' => 'Valid Name '.uniqid()]));
    }

    public function test_import_rejects_a_new_row_with_a_blank_english_name(): void
    {
        $this->expectException(RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow(['name' => 'بانک معتبر']));
    }

    public function test_import_rejects_a_duplicate_english_name_instead_of_updating(): void
    {
        $existing = Bank::factory()->create([
            'english_name' => 'Existing Import Bank '.uniqid(),
            'description' => 'Original Description',
        ]);

        try {
            $this->invokeImporter($this->baseImportRow([
                'name' => 'بانک جدید',
                'english_name' => $existing->english_name,
            ]));
            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (RowImportFailedException $e) {
        }

        $this->assertSame(1, Bank::where('english_name', $existing->english_name)->count());
        $this->assertSame('Original Description', $existing->fresh()->description);
    }

    public function test_import_rejects_a_duplicate_name_even_with_a_unique_english_name(): void
    {
        $existing = Bank::factory()->create(['name' => 'بانک یکتای وارداتی '.uniqid()]);

        try {
            $this->invokeImporter($this->baseImportRow([
                'name' => $existing->name,
                'english_name' => 'Totally Different Bank '.uniqid(),
            ]));
            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (RowImportFailedException $e) {
        }

        $this->assertSame(1, Bank::where('name', $existing->name)->count());
    }

    public function test_filled_example_file_exists_and_matches_the_import_shape(): void
    {
        $path = BankImporter::filledExamplePath();

        $this->assertNotNull($path);

        $fullPath = storage_path('app/'.$path);
        $this->assertFileExists($fullPath);

        $contents = file_get_contents($fullPath);

        $this->assertStringContainsString('(name)', $contents);
        $this->assertStringContainsString('(english_name)', $contents);
        $this->assertStringContainsString('(description)', $contents);
        $this->assertStringContainsString('(is_active)', $contents);
    }

    public function test_auto_generated_empty_example_has_a_real_header_for_every_import_column(): void
    {
        foreach (BankImporter::getColumns() as $column) {
            $this->assertNotSame('', trim($column->getExampleHeader()), "Column [{$column->getName()}] has a blank example header.");
        }
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Pinned counts — a silent column drop during a future refactor must fail
        // this test, not slip through unnoticed (see importsPattern's settled policy).
        $this->assertCount(4, BankImporter::getColumns());
        $this->assertCount(4, BankImporter::columnLabels());
        $this->assertCount(9, BankExporter::columnLabels());
    }

    public function test_filled_example_file_imports_cleanly_end_to_end(): void
    {
        $fullPath = storage_path('app/'.BankImporter::filledExamplePath());
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
        $this->assertSame('Bank Melli Iran', $imported[0]->getRecord()->english_name);
        $this->assertTrue($imported[0]->getRecord()->fresh()->is_active);
        $this->assertTrue($imported[1]->getRecord()->fresh()->is_active);
        $this->assertNull($imported[1]->getRecord()->description);
    }

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'bank_export_').'.csv';
        BankExporter::write($query, $path);

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
        $active = Bank::factory()->create([
            'name' => 'بانک صادرات تست',
            'english_name' => 'Export Test Bank',
        ]);

        $this->actingAs(User::factory()->create());
        $inactive = Bank::factory()->inactive()->create();

        ['header' => $header, 'rows' => $rows] = $this->exportToRows(Bank::whereIn('id', [$active->id, $inactive->id]));

        $labels = BankExporter::columnLabels();

        $this->assertCount(9, $header);
        $this->assertSame('Export Test Bank', $rows[0][$labels['english_name']]);
        $this->assertSame(__('resources/bank/strings.export.active'), $rows[0][$labels['is_active']]);
        $this->assertSame('Export Creator', $rows[0][$labels['creator']]);
        $this->assertSame(__('resources/bank/strings.export.inactive'), $rows[1][$labels['is_active']]);
        $this->assertSame(jdate($active->created_at)->format('Y-m-d'), $rows[0][$labels['created_at']]);
    }

    // In-use count column

    public function test_in_use_count_column_shows_the_total_references(): void
    {
        $this->actingAsUserWithPermissions(['bank.view']);

        $used = Bank::factory()->create();
        BankProfile::factory()->count(2)->create(['bank_id' => $used->id]);
        $unused = Bank::factory()->create();

        Livewire::test(ManageBanks::class)
            ->assertTableColumnStateSet('in_use_count', 2, $used)
            ->assertTableColumnStateSet('in_use_count', 0, $unused);
    }

    // Usage guard — delete

    public function test_delete_action_succeeds_when_the_bank_is_unused(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.delete']);
        $record = Bank::factory()->create();

        Livewire::test(ManageBanks::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Bank::find($record->id));
    }

    public function test_delete_action_is_blocked_with_a_notification_when_the_bank_is_referenced(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.delete']);
        $record = Bank::factory()->create();
        BankProfile::factory()->create(['bank_id' => $record->id]);

        Livewire::test(ManageBanks::class)
            ->callTableAction('delete', $record)
            ->assertNotified(__('resources/general/strings.usage_guard.blocked', ['count' => 1]));

        $this->assertNotNull(Bank::find($record->id));
    }

    public function test_delete_bulk_action_is_blocked_when_a_selected_bank_is_referenced(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.delete']);
        $record = Bank::factory()->create();
        Payment::factory()->create(['bank_id' => $record->id]);

        Livewire::test(ManageBanks::class)
            ->callTableBulkAction('delete', [$record])
            ->assertNotified(__('resources/general/strings.usage_guard.blocked', ['count' => 1]));

        $this->assertNotNull(Bank::find($record->id));
    }

    // Usage guard — deactivate

    public function test_deactivate_bulk_action_succeeds_when_the_bank_is_unused(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.edit']);
        $record = Bank::factory()->create();

        Livewire::test(ManageBanks::class)
            ->callTableBulkAction('deactivate', [$record]);

        $this->assertFalse((bool) $record->fresh()->is_active);
    }

    public function test_deactivate_bulk_action_is_blocked_when_the_bank_is_referenced(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.edit']);
        $record = Bank::factory()->create();
        BankProfile::factory()->create(['bank_id' => $record->id]);

        Livewire::test(ManageBanks::class)
            ->callTableBulkAction('deactivate', [$record])
            ->assertNotified(__('resources/general/strings.usage_guard.blocked', ['count' => 1]));

        $this->assertTrue((bool) $record->fresh()->is_active);
    }

    // Description tooltip

    public function test_description_column_tooltip_shows_the_full_description(): void
    {
        $record = Bank::factory()->create(['description' => 'A fairly long description used for the tooltip test.']);

        $this->assertSame($record->description, BankResource::showDescription()->record($record)->getTooltip());
    }

    public function test_import_action_is_reachable_from_the_page_header(): void
    {
        $this->actingAsUserWithPermissions(['bank.view', 'bank.create']);

        Livewire::test(ManageBanks::class)
            ->assertActionExists('importBanks');
    }
}
