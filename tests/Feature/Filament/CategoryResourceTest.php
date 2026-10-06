<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\Master\CategoryResource\Exports\CategoryExporter;
use App\Filament\Resources\Master\CategoryResource\Imports\CategoryImporter;
use App\Filament\Resources\Master\CategoryResource\Pages\ManageCategories;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
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
            'category.view',
            'category.create',
            'category.edit',
            'category.delete',
            'category.restore',
        ]);

        $record = Category::factory()->create();

        $this->assertTrue(CategoryResource::canViewAny());
        $this->assertTrue(CategoryResource::canCreate());
        $this->assertTrue(CategoryResource::canEdit($record));
        $this->assertTrue(CategoryResource::canDelete($record));
        $this->assertTrue(CategoryResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = Category::factory()->create();

        $this->assertFalse(CategoryResource::canViewAny());
        $this->assertFalse(CategoryResource::canCreate());
        $this->assertFalse(CategoryResource::canEdit($record));
        $this->assertFalse(CategoryResource::canDelete($record));
        $this->assertFalse(CategoryResource::canRestore($record));
    }

    // List — search

    public function test_manage_page_renders_and_search_finds_by_english_name(): void
    {
        $this->actingAsUserWithPermissions(['category.view']);

        $target = Category::factory()->create();
        $other = Category::factory()->create();

        $term = 'CAT-SEARCH-TARGET-'.$target->id;
        Category::whereKey($target->id)->update(['english_name' => $term]);
        Category::whereKey($other->id)->update(['english_name' => 'CAT-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();

        Livewire::test(ManageCategories::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Filters

    public function test_level_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['category.view']);

        $base = Category::factory()->create(['level' => 0]);
        $sub = Category::factory()->create(['level' => 1]);

        Livewire::test(ManageCategories::class)
            ->filterTable('level', 0)
            ->assertCanSeeTableRecords([$base])
            ->assertCanNotSeeTableRecords([$sub]);
    }

    public function test_active_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['category.view']);

        $active = Category::factory()->create(['active' => true]);
        $inactive = Category::factory()->create(['active' => false]);

        Livewire::test(ManageCategories::class)
            ->filterTable('active', true)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_ancestors_filter_shows_higher_level_categories_of_the_selected_descendant(): void
    {
        $this->actingAsUserWithPermissions(['category.view']);

        $parent = Category::factory()->create();
        $child = Category::factory()->forParent($parent)->create();
        $unrelated = Category::factory()->create();

        Livewire::test(ManageCategories::class)
            ->filterTable('ancestors_filter', ['category_id' => $child->id])
            ->assertCanSeeTableRecords([$parent])
            ->assertCanNotSeeTableRecords([$unrelated]);
    }

    public function test_descendants_filter_shows_lower_level_categories_of_the_selected_ancestor(): void
    {
        $this->actingAsUserWithPermissions(['category.view']);

        $parent = Category::factory()->create();
        $child = Category::factory()->forParent($parent)->create();
        $unrelated = Category::factory()->create();

        Livewire::test(ManageCategories::class)
            ->filterTable('descendants_filter', ['category_id' => $parent->id])
            ->assertCanSeeTableRecords([$child])
            ->assertCanNotSeeTableRecords([$unrelated]);
    }

    public function test_creator_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['category.view']);
        $withA = Category::factory()->create();

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $withB = Category::factory()->create();

        $this->actingAs($userA);

        Livewire::test(ManageCategories::class)
            ->filterTable('user_id', $userA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_updater_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['category.view']);
        $record = Category::factory()->create();
        Category::whereKey($record->id)->update(['updated_by_id' => $userA->id]);

        $other = Category::factory()->create();

        Livewire::test(ManageCategories::class)
            ->filterTable('updated_by_id', $userA->id)
            ->assertCanSeeTableRecords([$record])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Create — happy path + validation

    public function test_create_action_persists_a_root_category_and_assigns_a_slug(): void
    {
        $this->actingAsUserWithPermissions(['category.view', 'category.create']);

        Livewire::test(ManageCategories::class)
            ->mountAction('create')
            ->fillForm([
                'name' => 'آزمایشی '.random_int(1000, 9999),
                'english_name' => 'TestCategory'.random_int(1000, 9999),
                'level' => 0,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $record = Category::latest('id')->first();
        $this->assertNotNull($record->slug);
        $this->assertNull($record->parent_id);
    }

    public function test_create_action_requires_level_to_be_an_integer(): void
    {
        $this->actingAsUserWithPermissions(['category.view', 'category.create']);

        Livewire::test(ManageCategories::class)
            ->mountAction('create')
            ->fillForm([
                'name' => 'آزمایشی',
                'english_name' => 'LevelInvalid',
                'level' => 'not-a-number',
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['level']);
    }

    public function test_create_action_requires_english_name(): void
    {
        $this->actingAsUserWithPermissions(['category.view', 'category.create']);

        Livewire::test(ManageCategories::class)
            ->mountAction('create')
            ->fillForm([
                'name' => 'آزمایشی',
                'english_name' => '',
                'level' => 0,
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['english_name']);
    }

    // Edit — re-parenting syncs the closure table, cycle rejected

    public function test_edit_action_reparents_a_category_and_syncs_the_closure_table(): void
    {
        $this->actingAsUserWithPermissions(['category.view', 'category.edit']);

        $newParent = Category::factory()->create(['level' => 0]);
        $record = Category::factory()->create(['level' => 0, 'parent_id' => null]);

        Livewire::test(ManageCategories::class)
            ->mountTableAction('edit', $record)
            ->fillForm(['parent_id' => $newParent->id, 'level' => 1])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $record->refresh();
        $this->assertSame($newParent->id, $record->parent_id);

        $this->assertTrue(
            DB::table('category_closure')
                ->where('ancestor_id', $newParent->id)
                ->where('descendant_id', $record->id)
                ->exists()
        );
    }

    public function test_edit_action_rejects_setting_a_categorys_own_descendant_as_its_parent(): void
    {
        $this->actingAsUserWithPermissions(['category.view', 'category.edit']);

        $parent = Category::factory()->create(['level' => 0]);
        $child = Category::factory()->forParent($parent)->create();

        Livewire::test(ManageCategories::class)
            ->mountTableAction('edit', $parent)
            ->fillForm(['parent_id' => $child->id])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['parent_id']);

        $this->assertNull($parent->fresh()->parent_id);
    }

    // Infolist

    public function test_view_action_renders_the_infolist(): void
    {
        $this->actingAsUserWithPermissions(['category.view']);
        $record = Category::factory()->create();

        Livewire::test(ManageCategories::class)
            ->mountTableAction('view', $record)
            ->assertSuccessful();
    }

    // Bulk actions — toolbar order

    public function test_bulk_actions_toolbar_orders_export_before_delete_and_restore(): void
    {
        $this->actingAsUserWithPermissions(['category.view', 'category.delete', 'category.restore']);

        Livewire::test(ManageCategories::class)
            ->assertTableBulkActionsExistInOrder(['exportCategories', 'delete', 'restore']);
    }

    // Delete blocking — children / products

    public function test_delete_action_is_blocked_when_category_has_children(): void
    {
        $this->actingAsUserWithPermissions(['category.view', 'category.delete']);

        $parent = Category::factory()->create();
        Category::factory()->forParent($parent)->create();

        Livewire::test(ManageCategories::class)
            ->callTableAction('delete', $parent);

        $this->assertNotNull(Category::find($parent->id));
    }

    public function test_delete_action_is_blocked_when_category_has_products(): void
    {
        $this->actingAsUserWithPermissions(['category.view', 'category.delete']);

        $record = Category::factory()->create();
        Product::factory()->create(['category_id' => $record->id]);

        Livewire::test(ManageCategories::class)
            ->callTableAction('delete', $record);

        $this->assertNotNull(Category::find($record->id));
    }

    public function test_bulk_delete_blocks_the_whole_batch_when_one_selected_category_has_children(): void
    {
        $this->actingAsUserWithPermissions(['category.view', 'category.delete']);

        $blockedParent = Category::factory()->create();
        Category::factory()->forParent($blockedParent)->create();
        $clean = Category::factory()->create();

        Livewire::test(ManageCategories::class)
            ->callTableBulkAction('delete', [$blockedParent, $clean]);

        $this->assertNotNull(Category::find($blockedParent->id));
        $this->assertNotNull(Category::find($clean->id));
    }

    public function test_bulk_delete_succeeds_when_no_selected_category_has_children_or_products(): void
    {
        $this->actingAsUserWithPermissions(['category.view', 'category.delete']);

        $one = Category::factory()->create();
        $two = Category::factory()->create();

        Livewire::test(ManageCategories::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(Category::find($one->id));
        $this->assertNull(Category::find($two->id));
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['category.view', 'category.delete', 'category.restore']);
        $record = Category::factory()->create();

        Livewire::test(ManageCategories::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Category::find($record->id));
        $this->assertTrue(Category::withTrashed()->find($record->id)->trashed());

        Livewire::test(ManageCategories::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(Category::find($record->id));
    }

    // Global search contract

    public function test_global_search_title_uses_folder_emoji_prefix_and_date(): void
    {
        $record = Category::factory()->create();

        $date = toYmdDate($record);
        $name = $record->getLocalizedNameAttribute() ?? '-';

        $this->assertSame("📁   {$name} (📆 {$date})", CategoryResource::getGlobalSearchResultTitle($record));
    }

    public function test_globally_searchable_attributes_are_name_and_english_name(): void
    {
        $this->assertSame(['name', 'english_name'], CategoryResource::getGloballySearchableAttributes());
    }

    // Export — bulk action dispatches the queued job

    public function test_export_bulk_action_dispatches_the_queued_export_job(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['category.view']);

        $record = Category::factory()->create();

        Livewire::test(ManageCategories::class)
            ->callTableBulkAction('exportCategories', [$record]);

        Queue::assertPushed(\App\Jobs\ExportCategories::class);
    }

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'cat_export_').'.csv';
        CategoryExporter::write($query, $path);

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
        $parent = Category::factory()->create(['english_name' => 'Export Parent']);
        $record = Category::factory()->forParent($parent)->create(['english_name' => 'Export Category']);

        ['header' => $header, 'rows' => $rows] = $this->exportToRows(Category::whereIn('id', [$record->id]));

        $labels = CategoryExporter::columnLabels();

        $this->assertCount(10, $header);
        $this->assertSame('Export Category', $rows[0][$labels['english_name']]);
        $this->assertSame($parent->name, $rows[0][$labels['parent']]);
        $this->assertSame('Export Creator', $rows[0][$labels['creator']]);
        $this->assertSame(__('resources/category/strings.table.active'), $rows[0][$labels['active']]);
    }

    // Import / Export — flat single-row, create-only

    private function importColumnMap(): array
    {
        $names = collect(CategoryImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeImporter(array $data, array $options = []): CategoryImporter
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $importer = new CategoryImporter(new Import, $this->importColumnMap(), array_merge([
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
            'parent_id' => '',
            'description' => '',
            'active' => '',
        ], $overrides);
    }

    public function test_import_creates_a_root_category_when_parent_is_blank(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'english_name' => 'ImportedRoot'.random_int(1000, 9999),
            'name' => 'وارداتی',
        ]));

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertNull($record->parent_id);
        $this->assertSame(0, $record->level);
        $this->assertNotNull($record->fresh()->slug);
    }

    public function test_import_with_an_unresolvable_parent_name_leaves_the_category_root_level(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'english_name' => 'ImportedOrphan'.random_int(1000, 9999),
            'parent_id' => 'NoSuchCategoryAtAll',
        ]));

        $record = $importer->getRecord();

        $this->assertNull($record->parent_id);
        $this->assertSame(0, $record->level);
    }

    public function test_import_with_a_valid_parent_name_resolves_the_parent_and_derives_the_level(): void
    {
        $parent = Category::factory()->create(['level' => 0]);

        $importer = $this->invokeImporter($this->baseImportRow([
            'english_name' => 'ImportedChild'.random_int(1000, 9999),
            'parent_id' => $parent->english_name,
        ]));

        $record = $importer->getRecord();

        $this->assertSame($parent->id, $record->parent_id);
        $this->assertSame($parent->level + 1, $record->level);
    }

    public function test_import_rejects_a_row_with_a_blank_english_name(): void
    {
        $this->expectException(\Filament\Actions\Imports\Exceptions\RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow([]));
    }

    public function test_import_rejects_a_row_whose_english_name_already_exists(): void
    {
        $existing = Category::factory()->create(['english_name' => 'DuplicateName'.random_int(1000, 9999)]);

        $this->expectException(\Filament\Actions\Imports\Exceptions\RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow([
            'english_name' => $existing->english_name,
        ]));
    }

    public function test_import_defaults_active_to_true_when_blank(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'english_name' => 'ImportedActiveDefault'.random_int(1000, 9999),
        ]));

        $this->assertTrue((bool) $importer->getRecord()->active);
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Pinned counts — a silent column drop during a future refactor must fail
        // this test, not slip through unnoticed (see importsPattern's settled policy).
        $this->assertCount(5, CategoryImporter::getColumns());
        $this->assertCount(5, CategoryImporter::columnLabels());
        $this->assertCount(10, CategoryExporter::columnLabels());
    }

    public function test_filled_example_file_exists_and_matches_the_import_shape(): void
    {
        $path = CategoryImporter::filledExamplePath();

        $this->assertNotNull($path);

        $fullPath = storage_path('app/'.$path);
        $this->assertFileExists($fullPath);

        $contents = file_get_contents($fullPath);

        $this->assertStringContainsString('(english_name)', $contents);
        $this->assertStringContainsString('(name)', $contents);
        $this->assertStringContainsString('(parent_id)', $contents);
        $this->assertStringContainsString('(description)', $contents);
        $this->assertStringContainsString('(active)', $contents);
    }

    public function test_filled_example_file_imports_cleanly_end_to_end(): void
    {
        $fullPath = storage_path('app/'.CategoryImporter::filledExamplePath());
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

        $electronics = $imported[0]->getRecord();
        $mobilePhones = $imported[1]->getRecord();
        $furniture = $imported[2]->getRecord();
        $officeChairs = $imported[3]->getRecord();

        $this->assertNull($electronics->parent_id);
        $this->assertSame(0, $electronics->level);

        $this->assertSame($electronics->id, $mobilePhones->parent_id);
        $this->assertSame(1, $mobilePhones->level);

        $this->assertNull($furniture->parent_id);
        $this->assertSame(0, $furniture->level);

        $this->assertSame($furniture->id, $officeChairs->parent_id);
        $this->assertSame(1, $officeChairs->level);
        $this->assertNull($officeChairs->description);
        $this->assertFalse((bool) $officeChairs->active);
    }
}
