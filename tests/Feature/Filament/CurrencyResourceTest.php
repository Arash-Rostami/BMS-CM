<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CurrencyResource;
use App\Filament\Resources\Master\CurrencyResource\Exports\CurrencyExporter;
use App\Filament\Resources\Master\CurrencyResource\Imports\CurrencyImporter;
use App\Filament\Resources\Master\CurrencyResource\Pages\ManageCurrencies;
use App\Models\BankProfile;
use App\Models\Currency;
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

class CurrencyResourceTest extends TestCase
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
            'currency.view',
            'currency.create',
            'currency.edit',
            'currency.delete',
            'currency.restore',
        ]);

        $record = Currency::factory()->create();

        $this->assertTrue(CurrencyResource::canViewAny());
        $this->assertTrue(CurrencyResource::canCreate());
        $this->assertTrue(CurrencyResource::canEdit($record));
        $this->assertTrue(CurrencyResource::canDelete($record));
        $this->assertTrue(CurrencyResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = Currency::factory()->create();

        $this->assertFalse(CurrencyResource::canViewAny());
        $this->assertFalse(CurrencyResource::canCreate());
        $this->assertFalse(CurrencyResource::canEdit($record));
        $this->assertFalse(CurrencyResource::canDelete($record));
        $this->assertFalse(CurrencyResource::canRestore($record));
    }

    // List — search

    public function test_manage_page_renders_and_search_finds_by_english_name(): void
    {
        $this->actingAsUserWithPermissions(['currency.view']);

        $target = Currency::factory()->create();
        $other = Currency::factory()->create();

        $term = 'CUR-SEARCH-TARGET-'.$target->id;
        Currency::whereKey($target->id)->update(['english_name' => $term]);
        Currency::whereKey($other->id)->update(['english_name' => 'CUR-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();

        Livewire::test(ManageCurrencies::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Filters

    public function test_active_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['currency.view']);

        $active = Currency::factory()->create();
        $inactive = Currency::factory()->inactive()->create();

        Livewire::test(ManageCurrencies::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_in_use_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['currency.view']);

        $used = Currency::factory()->create();
        BankProfile::factory()->create(['requested_currency_id' => $used->id]);
        $unused = Currency::factory()->create();

        Livewire::test(ManageCurrencies::class)
            ->filterTable('in_use', true)
            ->assertCanSeeTableRecords([$used])
            ->assertCanNotSeeTableRecords([$unused]);
    }

    public function test_creator_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['currency.view']);
        $withA = Currency::factory()->create();

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $withB = Currency::factory()->create();

        $this->actingAs($userA);

        Livewire::test(ManageCurrencies::class)
            ->filterTable('user_id', $userA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    // Create — happy path + unique validation (plain-scalar form, §3d fillForm() quirk doesn't apply — no relationship-bound fields)

    public function test_create_action_happy_path_saves_a_new_currency(): void
    {
        $this->actingAsUserWithPermissions(['currency.view', 'currency.create']);

        $uniq = uniqid();

        Livewire::test(ManageCurrencies::class)
            ->mountAction('create')
            ->fillForm([
                'name' => 'درهم امارات '.random_int(100000, 999999),
                'english_name' => 'UAE Dirham '.$uniq,
                'description' => 'د.إ',
                'is_active' => true,
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $record = Currency::where('english_name', 'UAE Dirham '.$uniq)->first();

        $this->assertNotNull($record);
        $this->assertSame('د.إ', $record->description);
        $this->assertTrue($record->is_active);
    }

    public function test_create_action_rejects_a_duplicate_english_name(): void
    {
        $this->actingAsUserWithPermissions(['currency.view', 'currency.create']);

        $existing = Currency::factory()->create(['english_name' => 'DUP-ENG-'.uniqid()]);

        Livewire::test(ManageCurrencies::class)
            ->mountAction('create')
            ->fillForm([
                'name' => 'ارز تکراری',
                'english_name' => $existing->english_name,
            ])
            ->callMountedAction()
            ->assertHasFormErrors(['english_name' => 'unique']);
    }

    public function test_create_action_rejects_a_duplicate_name(): void
    {
        $this->actingAsUserWithPermissions(['currency.view', 'currency.create']);

        $existing = Currency::factory()->create(['name' => 'نام تکراری '.random_int(100000, 999999)]);

        Livewire::test(ManageCurrencies::class)
            ->mountAction('create')
            ->fillForm([
                'name' => $existing->name,
                'english_name' => 'UNIQUE-ENG-'.uniqid(),
            ])
            ->callMountedAction()
            ->assertHasFormErrors(['name' => 'unique']);
    }

    // Edit — table row action

    public function test_edit_action_persists_field_changes(): void
    {
        $this->actingAsUserWithPermissions(['currency.view', 'currency.edit']);
        $record = Currency::factory()->create();

        Livewire::test(ManageCurrencies::class)
            ->mountTableAction('edit', $record)
            ->fillForm([
                'description' => 'Updated symbol',
                'is_active' => false,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $record->refresh();

        $this->assertSame('Updated symbol', $record->description);
        $this->assertFalse($record->is_active);
    }

    // Infolist

    public function test_view_action_renders_the_infolist_with_record_values(): void
    {
        $this->actingAsUserWithPermissions(['currency.view']);
        $record = Currency::factory()->create();

        Livewire::test(ManageCurrencies::class)
            ->mountTableAction('view', $record)
            ->assertSuccessful()
            ->assertTableActionDataSet([
                'name' => $record->name,
                'english_name' => $record->english_name,
                'is_active' => $record->is_active,
            ]);
    }

    // Activation — bulk actions

    public function test_deactivate_and_activate_bulk_actions_flip_is_active(): void
    {
        $this->actingAsUserWithPermissions(['currency.view', 'currency.edit']);
        $record = Currency::factory()->create();

        Livewire::test(ManageCurrencies::class)
            ->callTableBulkAction('deactivate', [$record]);

        $this->assertFalse($record->fresh()->is_active);

        Livewire::test(ManageCurrencies::class)
            ->callTableBulkAction('activate', [$record]);

        $this->assertTrue($record->fresh()->is_active);
    }

    public function test_bulk_actions_toolbar_orders_export_activate_deactivate_before_delete_and_restore(): void
    {
        $this->actingAsUserWithPermissions(['currency.view', 'currency.delete', 'currency.restore']);

        Livewire::test(ManageCurrencies::class)
            ->assertTableBulkActionsExistInOrder(['exportCurrencies', 'activate', 'deactivate', 'delete', 'restore']);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['currency.view', 'currency.delete', 'currency.restore']);
        $record = Currency::factory()->create();

        Livewire::test(ManageCurrencies::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Currency::find($record->id));
        $this->assertTrue(Currency::withTrashed()->find($record->id)->trashed());

        Livewire::test(ManageCurrencies::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(Currency::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['currency.view', 'currency.delete']);
        $one = Currency::factory()->create();
        $two = Currency::factory()->create();

        Livewire::test(ManageCurrencies::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(Currency::find($one->id));
        $this->assertNull(Currency::find($two->id));
    }

    // Usage guard — delete

    public function test_delete_action_is_blocked_with_a_notification_when_the_currency_is_referenced(): void
    {
        $this->actingAsUserWithPermissions(['currency.view', 'currency.delete']);
        $record = Currency::factory()->create();
        BankProfile::factory()->create(['requested_currency_id' => $record->id]);

        Livewire::test(ManageCurrencies::class)
            ->callTableAction('delete', $record)
            ->assertNotified(__('resources/general/strings.usage_guard.blocked', ['count' => 1]));

        $this->assertNotNull(Currency::find($record->id));
    }

    public function test_delete_bulk_action_is_blocked_when_a_selected_currency_is_referenced(): void
    {
        $this->actingAsUserWithPermissions(['currency.view', 'currency.delete']);
        $record = Currency::factory()->create();
        BankProfile::factory()->create(['purchased_currency_id' => $record->id]);

        Livewire::test(ManageCurrencies::class)
            ->callTableBulkAction('delete', [$record])
            ->assertNotified(__('resources/general/strings.usage_guard.blocked', ['count' => 1]));

        $this->assertNotNull(Currency::find($record->id));
    }

    // Usage guard — deactivate

    public function test_deactivate_bulk_action_succeeds_when_the_currency_is_unused(): void
    {
        $this->actingAsUserWithPermissions(['currency.view', 'currency.edit']);
        $record = Currency::factory()->create();

        Livewire::test(ManageCurrencies::class)
            ->callTableBulkAction('deactivate', [$record]);

        $this->assertFalse((bool) $record->fresh()->is_active);
    }

    public function test_deactivate_bulk_action_is_blocked_when_the_currency_is_referenced(): void
    {
        $this->actingAsUserWithPermissions(['currency.view', 'currency.edit']);
        $record = Currency::factory()->create();
        BankProfile::factory()->create(['requested_currency_id' => $record->id]);

        Livewire::test(ManageCurrencies::class)
            ->callTableBulkAction('deactivate', [$record])
            ->assertNotified(__('resources/general/strings.usage_guard.blocked', ['count' => 1]));

        $this->assertTrue((bool) $record->fresh()->is_active);
    }

    // In-use column

    public function test_in_use_column_reflects_whether_the_currency_is_referenced(): void
    {
        $this->actingAsUserWithPermissions(['currency.view']);

        $used = Currency::factory()->create();
        BankProfile::factory()->create(['requested_currency_id' => $used->id]);
        $unused = Currency::factory()->create();

        Livewire::test(ManageCurrencies::class)
            ->assertTableColumnStateSet('in_use', true, $used)
            ->assertTableColumnStateSet('in_use', false, $unused);
    }

    // Global search contract

    public function test_global_search_title_uses_money_emoji_prefix_and_localized_name(): void
    {
        $record = Currency::factory()->create();

        $this->assertStringContainsString('💰', CurrencyResource::getGlobalSearchResultTitle($record));
        $this->assertStringContainsString($record->getLocalizedNameAttribute(), CurrencyResource::getGlobalSearchResultTitle($record));
    }

    // Export — bulk action dispatches the queued job

    public function test_export_bulk_action_dispatches_the_queued_export_job(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['currency.view']);

        $record = Currency::factory()->create();

        Livewire::test(ManageCurrencies::class)
            ->callTableBulkAction('exportCurrencies', [$record]);

        Queue::assertPushed(\App\Jobs\ExportCurrencies::class);
    }

    // Exporter — row shape + column-count pin

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'currency_export_').'.csv';
        CurrencyExporter::write($query, $path);

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
        $active = Currency::factory()->create([
            'name' => 'یورو صادراتی',
            'english_name' => 'Exported Euro '.uniqid(),
        ]);

        $this->actingAs(User::factory()->create());
        $inactive = Currency::factory()->inactive()->create();

        ['header' => $header, 'rows' => $rows] = $this->exportToRows(Currency::whereIn('id', [$active->id, $inactive->id]));

        $labels = CurrencyExporter::columnLabels();

        $this->assertCount(9, $header);
        $this->assertSame($active->english_name, $rows[0][$labels['english_name']]);
        $this->assertSame(__('resources/currency/strings.export.active'), $rows[0][$labels['is_active']]);
        $this->assertSame('Export Creator', $rows[0][$labels['creator']]);
        $this->assertSame(__('resources/currency/strings.export.inactive'), $rows[1][$labels['is_active']]);
        $this->assertSame(jdate($active->created_at)->format('Y-m-d'), $rows[0][$labels['created_at']]);
    }

    public function test_exporter_escapes_formula_injection_in_free_text_columns(): void
    {
        $record = Currency::factory()->create([
            'english_name' => '=cmd|"/c calc"!A1 '.uniqid(),
            'description' => '=SUM(A1:A2)',
        ]);

        ['rows' => $rows] = $this->exportToRows(Currency::whereKey($record->id));

        $labels = CurrencyExporter::columnLabels();

        $this->assertStringStartsNotWith('=', $rows[0][$labels['english_name']]);
        $this->assertStringStartsNotWith('=', $rows[0][$labels['description']]);
    }

    // Import / Export — flat single-row, create-only, reject-on-duplicate

    private function importColumnMap(): array
    {
        $names = collect(CurrencyImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeImporter(array $data, array $options = []): CurrencyImporter
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $importer = new CurrencyImporter(new Import, $this->importColumnMap(), array_merge([
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

    public function test_import_creates_a_new_currency_defaulting_is_active_to_true_when_blank(): void
    {
        $uniq = uniqid();
        Currency::where('name', 'کرون سوئد')->delete();

        $importer = $this->invokeImporter($this->baseImportRow([
            'name' => 'کرون سوئد',
            'english_name' => "Swedish Krona-{$uniq}",
        ]));

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertSame("Swedish Krona-{$uniq}", $record->english_name);
        $this->assertTrue($record->fresh()->is_active);
    }

    public function test_import_rejects_a_new_row_with_a_blank_english_name(): void
    {
        $this->expectException(RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow(['name' => 'ریال']));
    }

    public function test_import_rejects_a_new_row_with_a_blank_name(): void
    {
        $this->expectException(RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow(['english_name' => 'Blank Name Currency-'.uniqid()]));
    }

    public function test_import_rejects_a_duplicate_english_name_instead_of_updating_the_existing_record(): void
    {
        $uniq = uniqid();
        $existing = Currency::factory()->create([
            'english_name' => "USD-{$uniq}",
            'description' => 'Original description',
        ]);

        try {
            $this->invokeImporter($this->baseImportRow([
                'name' => 'دلار آمریکا دوباره',
                'english_name' => "USD-{$uniq}",
                'description' => 'Should not overwrite',
            ]));
            $this->fail('Expected a RowImportFailedException for a duplicate english_name.');
        } catch (RowImportFailedException $exception) {
            // expected
        }

        $this->assertSame(1, Currency::where('english_name', "USD-{$uniq}")->count());
        $this->assertSame('Original description', $existing->fresh()->description);
    }

    public function test_import_rejects_a_duplicate_english_name_case_insensitively(): void
    {
        $uniq = uniqid();
        Currency::factory()->create(['english_name' => "CAD-{$uniq}"]);

        $this->expectException(RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow([
            'name' => 'دلار کانادا',
            'english_name' => "cad-{$uniq}",
        ]));
    }

    public function test_import_rejects_a_duplicate_name_even_with_a_unique_english_name(): void
    {
        $uniq = uniqid();
        $existing = Currency::factory()->create([
            'name' => "دلار-{$uniq}",
            'english_name' => "USD-{$uniq}",
            'description' => 'Original description',
        ]);

        try {
            $this->invokeImporter($this->baseImportRow([
                'name' => "دلار-{$uniq}",
                'english_name' => "USD-DIFFERENT-{$uniq}",
                'description' => 'Should not overwrite',
            ]));
            $this->fail('Expected a RowImportFailedException for a duplicate name.');
        } catch (RowImportFailedException $exception) {
            // expected
        }

        $this->assertSame(1, Currency::where('name', "دلار-{$uniq}")->count());
        $this->assertSame('Original description', $existing->fresh()->description);
    }

    public function test_is_active_import_column_normalizes_common_truthy_and_falsy_tokens(): void
    {
        $columns = collect(CurrencyImporter::getColumns());
        $isActive = $columns->first(fn ($column) => $column->getName() === 'is_active');

        $this->assertTrue($isActive->castState('Yes'));
        $this->assertTrue($isActive->castState('1'));
        $this->assertFalse($isActive->castState('No'));
        $this->assertFalse($isActive->castState('0'));
    }

    public function test_import_rejects_an_unrecognized_is_active_token(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->invokeImporter($this->baseImportRow([
            'name' => 'ارز نامعتبر',
            'english_name' => 'Invalid Boolean Currency-'.uniqid(),
            'is_active' => 'banana',
        ]));
    }

    public function test_filled_example_file_exists_and_matches_the_import_shape(): void
    {
        $path = CurrencyImporter::filledExamplePath();

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
        foreach (CurrencyImporter::getColumns() as $column) {
            $this->assertNotSame('', trim($column->getExampleHeader()), "Column [{$column->getName()}] has a blank example header.");
        }
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Pinned counts — a silent column drop during a future refactor must fail
        // this test, not slip through unnoticed (see importsPattern's settled policy).
        $this->assertCount(4, CurrencyImporter::getColumns());
        $this->assertCount(4, CurrencyImporter::columnLabels());
        $this->assertCount(9, CurrencyExporter::columnLabels());
    }

    public function test_filled_example_file_imports_cleanly_end_to_end(): void
    {
        Currency::whereIn('name', ['دلار آمریکا', 'ریال قطر', 'دونگ ویتنام', 'پوند بریتانیا'])->delete();

        $fullPath = storage_path('app/'.CurrencyImporter::filledExamplePath());
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

        $this->assertCount(4, $imported);
        $this->assertSame('US Dollar', $imported[0]->getRecord()->english_name);
        $this->assertTrue($imported[0]->getRecord()->is_active);
        $this->assertTrue($imported[1]->getRecord()->is_active);
        $this->assertFalse($imported[3]->getRecord()->is_active);
    }

    public function test_import_action_is_reachable_from_the_page_header(): void
    {
        $this->actingAsUserWithPermissions(['currency.view', 'currency.create']);

        Livewire::test(ManageCurrencies::class)
            ->assertActionExists('importCurrencies');
    }
}
