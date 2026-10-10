<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Master\ProductResource\Exports\ProductExporter;
use App\Filament\Resources\Master\ProductResource\Imports\ProductImporter;
use App\Filament\Resources\Master\ProductResource\Pages\ManageProducts;
use App\Filament\Resources\ProductResource;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ProductResourceTest extends TestCase
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

    // Inquiry-flow pure methods

    public function test_find_by_inquiry_code_returns_null_for_a_blank_code(): void
    {
        $this->assertNull(ProductResource::findByInquiryCode(null));
        $this->assertNull(ProductResource::findByInquiryCode(''));
    }

    public function test_find_by_inquiry_code_returns_null_when_no_product_matches(): void
    {
        $this->assertNull(ProductResource::findByInquiryCode('NO-SUCH-CODE-'.fake()->unique()->bothify('####')));
    }

    public function test_find_by_inquiry_code_returns_the_matching_product(): void
    {
        $product = Product::factory()->create(['code' => 'PRD-INQUIRY-TEST']);

        $found = ProductResource::findByInquiryCode('PRD-INQUIRY-TEST');

        $this->assertNotNull($found);
        $this->assertSame($product->id, $found->id);
    }

    public function test_find_by_inquiry_code_normalizes_case_and_surrounding_whitespace(): void
    {
        $product = Product::factory()->create(['code' => 'PRD-NORMALIZE-TEST']);

        $found = ProductResource::findByInquiryCode('  prd-normalize-test  ');

        $this->assertNotNull($found);
        $this->assertSame($product->id, $found->id);
    }

    public function test_specification_has_data_returns_false_when_every_field_is_blank(): void
    {
        $this->assertFalse(ProductResource::specificationHasData([
            'hs_code' => null,
            'import_duty' => '',
            'packing_type' => null,
            'vat_exempt' => false,
            'tax_id' => null,
            'manufacturer' => null,
            'import_licenses' => null,
            'extra' => [],
        ]));
    }

    public function test_specification_has_data_returns_true_when_any_text_field_is_filled(): void
    {
        $this->assertTrue(ProductResource::specificationHasData([
            'hs_code' => '7208.10',
            'vat_exempt' => false,
            'extra' => [],
        ]));
    }

    public function test_specification_has_data_treats_vat_exempt_true_as_filled(): void
    {
        $this->assertTrue(ProductResource::specificationHasData([
            'vat_exempt' => true,
        ]));
    }

    public function test_specification_has_data_treats_a_non_empty_extra_array_as_filled(): void
    {
        $this->assertTrue(ProductResource::specificationHasData([
            'vat_exempt' => false,
            'extra' => ['grade' => 'A'],
        ]));
    }

    // Trashed-code detection

    public function test_a_soft_deleted_product_code_is_detectable_via_only_trashed(): void
    {
        $product = Product::factory()->create(['code' => 'PRD-TRASHED-TEST']);
        $product->delete();

        $this->assertTrue(Product::onlyTrashed()->where('code', Product::normalizeCode(' prd-trashed-test '))->exists());
        $this->assertFalse(Product::where('code', Product::normalizeCode('PRD-TRASHED-TEST'))->exists());
    }

    // Permissions

    public function test_full_permissions_allow_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([
            'product.view',
            'product.create',
            'product.edit',
            'product.delete',
            'product.restore',
        ]);

        $record = Product::factory()->create();

        $this->assertTrue(ProductResource::canViewAny());
        $this->assertTrue(ProductResource::canCreate());
        $this->assertTrue(ProductResource::canEdit($record));
        $this->assertTrue(ProductResource::canDelete($record));
        $this->assertTrue(ProductResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = Product::factory()->create();

        $this->assertFalse(ProductResource::canViewAny());
        $this->assertFalse(ProductResource::canCreate());
        $this->assertFalse(ProductResource::canEdit($record));
        $this->assertFalse(ProductResource::canDelete($record));
        $this->assertFalse(ProductResource::canRestore($record));
    }

    // List — search

    public function test_manage_page_renders_and_search_finds_by_english_name(): void
    {
        $this->actingAsUserWithPermissions(['product.view']);

        $target = Product::factory()->create(['english_name' => 'Searchable Target Product']);
        $other = Product::factory()->create(['english_name' => 'Unrelated Product']);

        Livewire::test(ManageProducts::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable('Searchable Target')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_finds_by_persian_name(): void
    {
        $this->actingAsUserWithPermissions(['product.view']);

        $target = Product::factory()->create(['name' => 'محصول قابل جستجوی منحصر به فرد']);
        $other = Product::factory()->create(['name' => 'محصول نامرتبط']);

        Livewire::test(ManageProducts::class)
            ->searchTable('قابل جستجوی منحصر')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_attributes_column_search_matches_a_known_tag_and_excludes_a_sibling_without_it(): void
    {
        $this->actingAsUserWithPermissions(['product.view']);

        $target = Product::factory()->create(['attributes' => ['crimson', 'premium']]);
        $other = Product::factory()->create(['attributes' => ['navy', 'standard']]);

        Livewire::test(ManageProducts::class)
            ->searchTable('crimson')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Validation messages — no raw-English leak in fa (multi-value Select nested inside a Repeater)

    public function test_create_rejects_an_invalid_import_license_with_translated_message(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['product.view', 'product.create']);

        $test = Livewire::test(ManageProducts::class)
            ->mountAction('create')
            ->fillForm([
                'chain_complete' => true,
                'confirmed_create' => true,
                'code' => 'PROBE-CODE-'.uniqid(),
                'specifications' => [
                    ['import_licenses' => ['totally-bogus-license']],
                ],
            ])
            ->callMountedAction();

        $this->assertSame(
            [__('resources/product/strings.form.validation_import_licenses_in')],
            $test->errors()->get('mountedActions.0.data.specifications.0.import_licenses.0')
        );
    }

    // Filters

    public function test_active_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['product.view']);

        $active = Product::factory()->create();
        $inactive = Product::factory()->inactive()->create();

        Livewire::test(ManageProducts::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_in_stock_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['product.view']);

        $inStock = Product::factory()->create(['in_stock' => true]);
        $outOfStock = Product::factory()->create(['in_stock' => false]);

        Livewire::test(ManageProducts::class)
            ->filterTable('in_stock', true)
            ->assertCanSeeTableRecords([$inStock])
            ->assertCanNotSeeTableRecords([$outOfStock]);
    }

    public function test_category_filter_narrows_the_table_to_the_category_and_its_descendants(): void
    {
        $this->actingAsUserWithPermissions(['product.view']);

        $parent = Category::factory()->create();
        $child = Category::factory()->forParent($parent)->create();

        $inChild = Product::factory()->create(['category_id' => $child->id]);
        $unrelated = Product::factory()->create();

        Livewire::test(ManageProducts::class)
            ->filterTable('category_id', $parent->id)
            ->assertCanSeeTableRecords([$inChild])
            ->assertCanNotSeeTableRecords([$unrelated]);
    }

    public function test_roll_sheet_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['product.view']);

        $roll = Product::factory()->create(['category_id' => null, 'attributes' => ['120cm']]);
        $other = Product::factory()->create(['category_id' => null, 'attributes' => ['plain']]);

        Livewire::test(ManageProducts::class)
            ->filterTable('roll_sheet_type', 'Roll')
            ->assertCanSeeTableRecords([$roll])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_customs_ready_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['product.view']);

        $ready = Product::factory()->create();
        $ready->specifications()->create(['hs_code' => '1234.56']);

        $notReady = Product::factory()->create();

        Livewire::test(ManageProducts::class)
            ->filterTable('customs_ready', true)
            ->assertCanSeeTableRecords([$ready])
            ->assertCanNotSeeTableRecords([$notReady]);
    }

    public function test_customs_ready_column_reflects_whether_the_product_has_an_hs_code(): void
    {
        $this->actingAsUserWithPermissions(['product.view']);

        $ready = Product::factory()->create();
        $ready->specifications()->create(['hs_code' => '1234.56']);

        $this->assertTrue(filled($ready->fresh()->specifications->first()?->hs_code));
    }

    public function test_creator_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['product.view']);
        $withA = Product::factory()->create();

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $withB = Product::factory()->create();

        $this->actingAs($userA);

        Livewire::test(ManageProducts::class)
            ->filterTable('user_id', $userA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_updater_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['product.view']);
        $product = Product::factory()->create();

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $product->update(['description' => 'updated by B']);

        $this->actingAs($userA);
        $other = Product::factory()->create();

        Livewire::test(ManageProducts::class)
            ->filterTable('updated_by_id', $userB->id)
            ->assertCanSeeTableRecords([$product])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_trashed_filter_hides_soft_deleted_records_by_default_and_can_show_only_trashed(): void
    {
        $this->actingAsUserWithPermissions(['product.view']);

        $active = Product::factory()->create();
        $trashed = Product::factory()->create();
        $trashed->delete();

        Livewire::test(ManageProducts::class)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$trashed])
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$trashed])
            ->assertCanNotSeeTableRecords([$active]);
    }

    // Edit

    public function test_edit_action_persists_a_field_update(): void
    {
        $this->actingAsUserWithPermissions(['product.view', 'product.edit']);
        $product = Product::factory()->create(['description' => 'Original description']);

        Livewire::test(ManageProducts::class)
            ->mountTableAction('edit', $product)
            ->fillForm(['description' => 'Updated description'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('Updated description', $product->fresh()->description);
    }

    // Infolist

    public function test_view_action_renders_both_infolist_tabs_without_error(): void
    {
        $this->actingAsUserWithPermissions(['product.view']);
        $product = Product::factory()->create();
        $product->specifications()->create(['hs_code' => '1234.56']);

        Livewire::test(ManageProducts::class)
            ->mountTableAction('view', $product)
            ->assertSuccessful();
    }

    // Bulk actions — toolbar order

    public function test_bulk_actions_toolbar_orders_export_before_delete_and_restore(): void
    {
        $this->actingAsUserWithPermissions(['product.view', 'product.delete', 'product.restore']);

        Livewire::test(ManageProducts::class)
            ->assertTableBulkActionsExistInOrder(['exportProducts', 'delete', 'restore']);
    }

    public function test_export_bulk_action_dispatches_the_queued_export_job(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['product.view']);

        $record = Product::factory()->create();

        Livewire::test(ManageProducts::class)
            ->callTableBulkAction('exportProducts', [$record]);

        Queue::assertPushed(\App\Jobs\ExportProducts::class);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['product.view', 'product.delete', 'product.restore']);
        $record = Product::factory()->create();

        Livewire::test(ManageProducts::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Product::find($record->id));
        $this->assertTrue(Product::withTrashed()->find($record->id)->trashed());

        Livewire::test(ManageProducts::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(Product::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['product.view', 'product.delete']);
        $one = Product::factory()->create();
        $two = Product::factory()->create();

        Livewire::test(ManageProducts::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(Product::find($one->id));
        $this->assertNull(Product::find($two->id));
    }

    // Global search contract

    public function test_global_search_title_uses_package_emoji_prefix_and_localized_name(): void
    {
        app()->setLocale('en');
        $record = Product::factory()->create(['english_name' => 'Global Search Product']);

        $title = ProductResource::getGlobalSearchResultTitle($record);

        $this->assertStringContainsString('📦', $title);
        $this->assertStringContainsString('Global Search Product', $title);
    }

    public function test_globally_searchable_attributes_are_both_name_columns_and_the_code(): void
    {
        $this->assertEqualsCanonicalizing(['name', 'english_name', 'code'], ProductResource::getGloballySearchableAttributes());
    }

    // Exporter

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'product_export_').'.csv';
        ProductExporter::write($query, $path);

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
        $category = Category::factory()->create(['english_name' => 'Export Category']);

        $this->actingAs($creator);
        $product = Product::factory()->create([
            'code' => 'PRD-EXP-1',
            'english_name' => 'Export Product',
            'category_id' => $category->id,
            'attributes' => ['red', 'large'],
        ]);
        $product->specifications()->create([
            'hs_code' => '1234.56',
            'vat_exempt' => true,
            'import_licenses' => ['standard_certificate'],
            'extra' => ['Grade' => 'A'],
        ]);

        ['header' => $header, 'rows' => $rows] = $this->exportToRows(Product::whereKey($product->id));

        $labels = ProductExporter::columnLabels();

        $this->assertSame('PRD-EXP-1', $rows[0][$labels['code']]);
        $this->assertSame('Export Product', $rows[0][$labels['english_name']]);
        $this->assertSame('Export Creator', $rows[0][$labels['creator']]);
        $this->assertSame(__('resources/product/strings.export.yes'), $rows[0][$labels['vat_exempt']]);
        $this->assertStringContainsString('Mandatory Standard Certificate', $rows[0][$labels['import_licenses']]);
        $this->assertStringContainsString('Grade: A', $rows[0][$labels['extra']]);
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Pinned counts — a silent column drop during a future refactor must fail
        // this test, not slip through unnoticed (see importsPattern's settled policy).
        $this->assertCount(16, ProductImporter::getColumns());
        $this->assertCount(16, ProductImporter::columnLabels());
        $this->assertCount(24, ProductExporter::columnLabels());
    }

    // Importer

    private function importColumnMap(): array
    {
        $names = collect(ProductImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeImporter(array $data, array $options = []): ProductImporter
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $importer = new ProductImporter(new Import, $this->importColumnMap(), array_merge([
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
            'category_id' => '',
            'name' => '',
            'english_name' => '',
            'attributes' => '',
            'description' => '',
            'in_stock' => '',
            'is_active' => '',
            'hs_code' => '',
            'import_duty' => '',
            'packing_type' => '',
            'vat_exempt' => '',
            'tax_id' => '',
            'manufacturer' => '',
            'import_licenses' => '',
            'extra' => '',
        ], $overrides);
    }

    public function test_import_creates_a_new_product_with_defaults_applied(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'code' => 'PRD-IMPORT-NEW',
            'english_name' => 'Imported Product',
        ]));

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertSame('PRD-IMPORT-NEW', $record->code);
        $this->assertSame('Imported Product', $record->english_name);
        $this->assertTrue($record->fresh()->in_stock);
        $this->assertTrue($record->fresh()->is_active);
    }

    public function test_import_rejects_a_row_with_a_blank_code(): void
    {
        $this->expectException(RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow([]));
    }

    public function test_import_rejects_a_row_whose_code_already_belongs_to_an_existing_product(): void
    {
        Product::factory()->create(['code' => 'DUP-1']);

        $this->expectException(RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow(['code' => 'DUP-1', 'english_name' => 'Should Not Save']));
    }

    public function test_import_rejects_a_case_and_whitespace_variant_of_an_existing_code(): void
    {
        Product::factory()->create(['code' => 'DUP-CASE-1']);

        $this->expectException(RowImportFailedException::class);

        $this->invokeImporter($this->baseImportRow(['code' => ' dup-case-1 ', 'english_name' => 'Should Not Save']));
    }

    public function test_import_resolves_a_valid_category_name_to_its_id(): void
    {
        $category = Category::factory()->create(['english_name' => 'Importable Category']);

        $importer = $this->invokeImporter($this->baseImportRow([
            'code' => 'PRD-IMPORT-CAT-OK',
            'category_id' => 'Importable Category',
        ]));

        $this->assertSame($category->id, $importer->getRecord()->category_id);
    }

    public function test_import_leaves_category_id_null_when_the_category_name_does_not_resolve(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'code' => 'PRD-IMPORT-CAT-BAD',
            'category_id' => 'Totally Made Up Category Name',
        ]));

        $this->assertNull($importer->getRecord()->category_id);
    }

    public function test_import_does_not_create_a_specification_row_when_every_specification_field_is_blank(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'code' => 'PRD-IMPORT-NO-SPEC',
        ]));

        $this->assertSame(0, $importer->getRecord()->specifications()->count());
    }

    public function test_import_creates_a_specification_row_when_at_least_one_field_is_filled(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'code' => 'PRD-IMPORT-WITH-SPEC',
            'hs_code' => '1234.56',
        ]));

        $specification = $importer->getRecord()->specifications()->first();

        $this->assertNotNull($specification);
        $this->assertSame('1234.56', $specification->hs_code);
    }

    public function test_import_parses_import_licenses_accepting_either_raw_keys_or_translated_labels(): void
    {
        app()->setLocale('en');

        $importer = $this->invokeImporter($this->baseImportRow([
            'code' => 'PRD-IMPORT-LICENSES',
            'import_licenses' => 'standard_certificate, Health Certificate (Health Network)',
        ]));

        $licenses = $importer->getRecord()->specifications()->first()->import_licenses;

        $this->assertSame(['standard_certificate', 'health_certificate'], $licenses);
    }

    public function test_import_parses_well_formed_extra_pairs_and_drops_a_malformed_pair_with_a_note(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'code' => 'PRD-IMPORT-EXTRA',
            'extra' => 'Grade: A| BadPairWithoutColon| Origin: Finland',
        ]));

        $record = $importer->getRecord();
        $specification = $record->specifications()->first();

        $this->assertSame(['Grade' => 'A', 'Origin' => 'Finland'], $specification->extra);
        $this->assertStringContainsString('BadPairWithoutColon', (string) $record->fresh()->notes);
    }

    public function test_import_parses_attributes_into_an_array(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'code' => 'PRD-IMPORT-ATTRS',
            'attributes' => 'red, large, 100cm',
        ]));

        $this->assertSame(['red', 'large', '100cm'], $importer->getRecord()->attributes);
    }

    public function test_import_parses_a_falsy_boolean_value_for_in_stock(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'code' => 'PRD-IMPORT-BOOL',
            'in_stock' => 'no',
        ]));

        $this->assertFalse($importer->getRecord()->fresh()->in_stock);
    }

    public function test_import_parses_a_truthy_vat_exempt_value_and_creates_a_specification(): void
    {
        $importer = $this->invokeImporter($this->baseImportRow([
            'code' => 'PRD-IMPORT-VAT',
            'vat_exempt' => 'yes',
        ]));

        $specification = $importer->getRecord()->specifications()->first();

        $this->assertNotNull($specification);
        $this->assertTrue($specification->vat_exempt);
    }

    public function test_filled_example_file_exists_and_matches_the_import_shape(): void
    {
        $path = ProductImporter::filledExamplePath();

        $this->assertNotNull($path);

        $fullPath = storage_path('app/'.$path);
        $this->assertFileExists($fullPath);

        $contents = file_get_contents($fullPath);

        $this->assertStringContainsString('(code)', $contents);
        $this->assertStringContainsString('(category_id)', $contents);
        $this->assertStringContainsString('(extra)', $contents);
    }

    public function test_auto_generated_empty_example_has_a_real_header_for_every_import_column(): void
    {
        foreach (ProductImporter::getColumns() as $column) {
            $this->assertNotSame('', trim($column->getExampleHeader()), "Column [{$column->getName()}] has a blank example header.");
        }
    }

    public function test_filled_example_file_imports_cleanly_end_to_end(): void
    {
        Category::factory()->create(['english_name' => 'Cellulosic products']);
        Category::factory()->create(['english_name' => 'Wood products']);

        $fullPath = storage_path('app/'.ProductImporter::filledExamplePath());
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
        $this->assertSame('PRD-IMP-001', $imported[0]->getRecord()->code);
        $this->assertNotNull($imported[0]->getRecord()->specifications()->first());
        $this->assertSame(0, $imported[2]->getRecord()->specifications()->count());
        $this->assertNull($imported[3]->getRecord()->category_id);
    }

    public function test_import_action_is_reachable_from_the_page_header(): void
    {
        $this->actingAsUserWithPermissions(['product.view', 'product.create']);

        Livewire::test(ManageProducts::class)
            ->assertActionExists('importProducts');
    }
}
