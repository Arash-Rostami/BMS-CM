<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Operational\PurchaseOrderResource\Exports\PurchaseOrderExporter;
use App\Filament\Resources\Operational\PurchaseOrderResource\Imports\PurchaseOrderImporter;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\CreatePurchaseOrder;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\EditPurchaseOrder;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\ListPurchaseOrders;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\ProformaInvoicesRelationManager;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\PurchaseRequestsRelationManager;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\RegisteredOrdersRelationManager;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\Imports\GroupRowFailedException;
use App\Services\SmartCacheManager;
use Filament\Actions\Imports\Models\Import;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class PurchaseOrderResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        SmartCacheManager::invalidate('Status');
    }

    protected function tearDown(): void
    {
        SmartCacheManager::invalidate('Status');
        DB::rollBack();
        parent::tearDown();
    }

    private function poStatus(string $englishName, ?int $stageOrder = null, ?string $approvalPermission = null): Status
    {
        return Status::factory()->create([
            'type' => PurchaseOrder::TYPE_PURCHASE_ORDER,
            'english_type' => PurchaseOrder::TYPE_PURCHASE_ORDER,
            'name' => $englishName,
            'english_name' => $englishName,
            'stage_order' => $stageOrder,
            'approval_permission' => $approvalPermission,
        ]);
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

    private function statusHistoryTab(Schema $schema): ?Tab
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

    private function statusHistoryTabBadge(Tab $tab, $record): ?int
    {
        $reflection = new ReflectionProperty($tab, 'badge');
        $reflection->setAccessible(true);

        return ($reflection->getValue($tab))($record);
    }

    private function repeatableItemComponents(RepeatableEntry $entry): array
    {
        $reflection = new ReflectionProperty($entry, 'childComponents');
        $reflection->setAccessible(true);

        return $reflection->getValue($entry)['default'];
    }

    private function mergedColumnMap(): array
    {
        $names = collect(PurchaseOrderImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeMergedImporter(array $parentData, array $itemRows = [], array $options = []): PurchaseOrderImporter
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $importer = new PurchaseOrderImporter(new Import, $this->mergedColumnMap(), array_merge([
            'locale' => 'en',
            'jalali' => false,
            'date_format' => 'Y-m-d',
        ], $options));

        if ($itemRows) {
            $importer->forGroup($itemRows);
        }

        $importer($parentData);

        return $importer;
    }

    private function baseParentRow(array $overrides): array
    {
        return array_merge([
            'po_number' => '',
            'order_date' => '2026-01-15',
            'validity_date' => '',
            'expected_delivery_date' => '',
            'incoterms' => '',
            'shipping_address' => '',
            'packing_details' => '',
            'notes' => '',
            'pr_numbers' => '',
            'invoice_nos' => '',
            'ro_numbers' => '',
        ], $overrides);
    }

    public function test_import_creates_a_new_record_with_po_number_auto_generated_when_blank(): void
    {
        $seller = Company::factory()->create(['english_name' => 'Import Seller One']);
        $buyer = Company::factory()->create(['english_name' => 'Import Buyer One']);
        $currency = Currency::factory()->create(['english_name' => 'PIU1']);
        $status = $this->poStatus('ImportNewOrder');

        $importer = $this->invokeMergedImporter($this->baseParentRow([
            'seller_id' => $seller->english_name,
            'buyer_id' => $buyer->english_name,
            'status_id' => $status->english_name,
            'currency_id' => $currency->english_name,
        ]));

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertMatchesRegularExpression('/^PO-\d{6}(-\d+)?$/', $record->po_number);
    }

    public function test_import_reupload_of_existing_po_number_updates_in_place(): void
    {
        $seller = Company::factory()->create(['english_name' => 'Reupload Seller']);
        $buyer = Company::factory()->create(['english_name' => 'Reupload Buyer']);
        $currency = Currency::factory()->create(['english_name' => 'PIU2']);
        $status = $this->poStatus('ImportReuploadStatus');

        $row = $this->baseParentRow([
            'seller_id' => $seller->english_name,
            'buyer_id' => $buyer->english_name,
            'status_id' => $status->english_name,
            'currency_id' => $currency->english_name,
            'notes' => 'First upload',
        ]);

        $first = $this->invokeMergedImporter($row);
        $id = $first->getRecord()->id;

        $row['po_number'] = $first->getRecord()->po_number;
        $row['notes'] = 'Second upload';

        $second = $this->invokeMergedImporter($row);

        $this->assertSame($id, $second->getRecord()->id);
        $this->assertSame('Second upload', $second->getRecord()->notes);
    }

    public function test_import_still_rejects_an_unresolvable_seller_value(): void
    {
        $buyer = Company::factory()->create(['english_name' => 'Strict Buyer']);
        $currency = Currency::factory()->create(['english_name' => 'PIU3']);
        $status = $this->poStatus('ImportStrictSellerStatus');

        try {
            $this->invokeMergedImporter($this->baseParentRow([
                'seller_id' => 'Nonexistent Seller Co XYZ',
                'buyer_id' => $buyer->english_name,
                'status_id' => $status->english_name,
                'currency_id' => $currency->english_name,
            ]));

            $this->fail('Expected ValidationException was not thrown.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertStringContainsString('Nonexistent Seller Co XYZ', collect($exception->errors())->flatten()->implode(' '));
        }
    }

    public function test_import_blank_seller_or_buyer_on_a_new_record_is_rejected_cleanly_not_a_raw_query_exception(): void
    {
        $buyer = Company::factory()->create(['english_name' => 'Blank Seller Buyer']);
        $currency = Currency::factory()->create(['english_name' => 'PIU3B']);
        $status = $this->poStatus('ImportBlankSellerStatus');

        try {
            $this->invokeMergedImporter($this->baseParentRow([
                'seller_id' => '',
                'buyer_id' => $buyer->english_name,
                'status_id' => $status->english_name,
                'currency_id' => $currency->english_name,
            ]));

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    public function test_import_new_record_with_unresolvable_incoterms_saves_via_null_fallback_and_logs_a_note(): void
    {
        $seller = Company::factory()->create(['english_name' => 'Note Seller']);
        $buyer = Company::factory()->create(['english_name' => 'Note Buyer']);
        $currency = Currency::factory()->create(['english_name' => 'PIU4']);
        $status = $this->poStatus('ImportNoteIncotermsStatus');

        $importer = $this->invokeMergedImporter($this->baseParentRow([
            'seller_id' => $seller->english_name,
            'buyer_id' => $buyer->english_name,
            'status_id' => $status->english_name,
            'currency_id' => $currency->english_name,
            'incoterms' => 'NOT-A-REAL-TERM',
        ]));

        $record = $importer->getRecord();

        $this->assertNull($record->incoterms);
        $this->assertStringContainsString('NOT-A-REAL-TERM', $record->notes);
    }

    public function test_import_item_with_blank_unit_is_rejected(): void
    {
        $seller = Company::factory()->create(['english_name' => 'Unit Seller']);
        $buyer = Company::factory()->create(['english_name' => 'Unit Buyer']);
        $currency = Currency::factory()->create(['english_name' => 'PIU5']);
        $status = $this->poStatus('ImportUnitStatus');
        $product = Product::factory()->create();

        $countBefore = PurchaseOrder::withTrashed()->count();

        try {
            $this->invokeMergedImporter($this->baseParentRow([
                'seller_id' => $seller->english_name,
                'buyer_id' => $buyer->english_name,
                'status_id' => $status->english_name,
                'currency_id' => $currency->english_name,
            ]), [
                ['product_id' => $product->code, 'quantity' => '2', 'unit' => '', 'unit_price' => '10', 'net_weight' => '', 'gross_weight' => '', 'description' => ''],
            ]);

            $this->fail('Expected GroupRowFailedException was not thrown.');
        } catch (GroupRowFailedException) {
            $this->assertSame($countBefore, PurchaseOrder::withTrashed()->count(), 'A failed item row must roll back the parent record too — no orphan purchase order should be persisted.');
        }
    }

    public function test_import_item_with_blank_quantity_or_unit_price_is_rejected_cleanly(): void
    {
        $seller = Company::factory()->create(['english_name' => 'QtyPrice Seller']);
        $buyer = Company::factory()->create(['english_name' => 'QtyPrice Buyer']);
        $currency = Currency::factory()->create(['english_name' => 'PIU6']);
        $status = $this->poStatus('ImportQtyPriceStatus');
        $product = Product::factory()->create();

        $countBefore = PurchaseOrder::withTrashed()->count();

        try {
            $this->invokeMergedImporter($this->baseParentRow([
                'seller_id' => $seller->english_name,
                'buyer_id' => $buyer->english_name,
                'status_id' => $status->english_name,
                'currency_id' => $currency->english_name,
            ]), [
                ['product_id' => $product->code, 'quantity' => '', 'unit' => 'kg', 'unit_price' => '10', 'net_weight' => '', 'gross_weight' => '', 'description' => ''],
            ]);

            $this->fail('Expected GroupRowFailedException was not thrown for a blank quantity.');
        } catch (GroupRowFailedException) {
            $this->assertSame($countBefore, PurchaseOrder::withTrashed()->count(), 'A failed item row must roll back the parent record too — no orphan purchase order should be persisted.');
        }

        try {
            $this->invokeMergedImporter($this->baseParentRow([
                'seller_id' => $seller->english_name,
                'buyer_id' => $buyer->english_name,
                'status_id' => $status->english_name,
                'currency_id' => $currency->english_name,
            ]), [
                ['product_id' => $product->code, 'quantity' => '2', 'unit' => 'kg', 'unit_price' => '', 'net_weight' => '', 'gross_weight' => '', 'description' => ''],
            ]);

            $this->fail('Expected GroupRowFailedException was not thrown for a blank unit_price.');
        } catch (GroupRowFailedException) {
            $this->assertSame($countBefore, PurchaseOrder::withTrashed()->count());
        }
    }

    public function test_import_attaches_purchase_requests_from_the_pr_numbers_column(): void
    {
        $seller = Company::factory()->create(['english_name' => 'Pivot Seller']);
        $buyer = Company::factory()->create(['english_name' => 'Pivot Buyer']);
        $currency = Currency::factory()->create(['english_name' => 'PIU7']);
        $status = $this->poStatus('ImportPivotStatus');
        $prOne = PurchaseRequest::factory()->create();
        $prTwo = PurchaseRequest::factory()->create();

        $importer = $this->invokeMergedImporter($this->baseParentRow([
            'seller_id' => $seller->english_name,
            'buyer_id' => $buyer->english_name,
            'status_id' => $status->english_name,
            'currency_id' => $currency->english_name,
            'pr_numbers' => "{$prOne->pr_number},{$prTwo->pr_number}",
        ]));

        $record = $importer->getRecord();

        $this->assertSame(2, $record->purchaseRequests()->count());
        $this->assertTrue($record->purchaseRequests()->whereKey($prOne->id)->exists());
        $this->assertTrue($record->purchaseRequests()->whereKey($prTwo->id)->exists());
    }

    public function test_import_pivot_attach_skips_an_unresolvable_identifier_and_logs_a_note_without_rejecting_the_row(): void
    {
        $seller = Company::factory()->create(['english_name' => 'Pivot Note Seller']);
        $buyer = Company::factory()->create(['english_name' => 'Pivot Note Buyer']);
        $currency = Currency::factory()->create(['english_name' => 'PIU8']);
        $status = $this->poStatus('ImportPivotNoteStatus');
        $prOne = PurchaseRequest::factory()->create();

        $importer = $this->invokeMergedImporter($this->baseParentRow([
            'seller_id' => $seller->english_name,
            'buyer_id' => $buyer->english_name,
            'status_id' => $status->english_name,
            'currency_id' => $currency->english_name,
            'pr_numbers' => "{$prOne->pr_number},PR-DOES-NOT-EXIST",
        ]));

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertSame(1, $record->purchaseRequests()->count());
        $this->assertTrue($record->purchaseRequests()->whereKey($prOne->id)->exists());
        $this->assertStringContainsString('PR-DOES-NOT-EXIST', $record->notes);
    }

    public function test_filled_example_file_exists_and_matches_the_grouped_import_shape(): void
    {
        $path = PurchaseOrderImporter::filledExamplePath();

        $this->assertNotNull($path);

        $fullPath = storage_path('app/'.$path);
        $this->assertFileExists($fullPath);

        $contents = file_get_contents($fullPath);

        $this->assertStringContainsString('product_id', $contents);
        $this->assertStringContainsString('pr_numbers', $contents);
    }

    public function test_filled_example_file_imports_cleanly_end_to_end(): void
    {
        Company::factory()->create(['english_name' => 'Persol']);
        Company::factory()->create(['english_name' => 'Persore']);
        Currency::factory()->create(['english_name' => 'United States Dollar']);
        Product::factory()->create(['code' => '340263']);
        Product::factory()->create(['code' => '340189']);
        Product::factory()->create(['code' => '321313']);
        $pr = PurchaseRequest::factory()->create();
        PurchaseRequest::whereKey($pr->id)->update(['pr_number' => 'PR-260628']);

        $fullPath = storage_path('app/'.PurchaseOrderImporter::filledExamplePath());
        $csv = \League\Csv\Reader::createFromPath($fullPath, 'r');
        $csv->setHeaderOffset(0);

        $records = [];
        foreach ($csv->getRecords() as $row) {
            $mapped = [];
            foreach ($row as $header => $value) {
                if (preg_match('/\(([a-z_]+)\)$/', $header, $matches)) {
                    $mapped[$matches[1]] = $value;
                }
            }
            $records[] = $mapped;
        }

        $parent = $records[0];
        $items = array_slice($records, 1);

        $imported = $this->invokeMergedImporter($parent, $items);

        $this->assertSame('Persol', $imported->getRecord()->sellerCompany->english_name);
        $this->assertCount(3, $imported->getRecord()->items);
        $this->assertSame('Submitted', $imported->getRecord()->status->english_name);
        $this->assertTrue($imported->getRecord()->purchaseRequests->pluck('pr_number')->contains('PR-260628'));
    }

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'po_export_').'.csv';
        PurchaseOrderExporter::write($query, $path);

        $csv = (string) file_get_contents($path);
        unlink($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", trim(ltrim($csv, "\xEF\xBB\xBF"))))));
        $header = str_getcsv($lines[0]);
        $rows = array_map(fn (string $line) => array_combine($header, str_getcsv($line)), array_slice($lines, 1));

        return ['header' => $header, 'rows' => $rows];
    }

    // Permissions

    public function test_full_permissions_allow_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([
            'purchase_order.view',
            'purchase_order.create',
            'purchase_order.edit',
            'purchase_order.delete',
            'purchase_order.restore',
        ]);

        $record = PurchaseOrder::factory()->create();

        $this->assertTrue(PurchaseOrderResource::canViewAny());
        $this->assertTrue(PurchaseOrderResource::canCreate());
        $this->assertTrue(PurchaseOrderResource::canEdit($record));
        $this->assertTrue(PurchaseOrderResource::canDelete($record));
        $this->assertTrue(PurchaseOrderResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = PurchaseOrder::factory()->create();

        $this->assertFalse(PurchaseOrderResource::canViewAny());
        $this->assertFalse(PurchaseOrderResource::canCreate());
        $this->assertFalse(PurchaseOrderResource::canEdit($record));
        $this->assertFalse(PurchaseOrderResource::canDelete($record));
        $this->assertFalse(PurchaseOrderResource::canRestore($record));
    }

    // List — search, EAV search, filters

    public function test_list_page_renders_and_search_finds_by_po_number(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);

        $target = PurchaseOrder::factory()->create();
        $other = PurchaseOrder::factory()->create();

        PurchaseOrder::whereKey($target->id)->update(['po_number' => 'PO-SEARCH-TARGET-'.$target->id]);
        PurchaseOrder::whereKey($other->id)->update(['po_number' => 'PO-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();
        $term = 'TARGET-'.$target->id;

        Livewire::test(ListPurchaseOrders::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_finds_records_by_extra_attribute_key_and_value(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);

        $target = PurchaseOrder::factory()->create();
        $other = PurchaseOrder::factory()->create();
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(ListPurchaseOrders::class)
            ->searchTable('contract_reference')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_globally_searchable_attributes_include_po_number(): void
    {
        $this->assertContains('po_number', PurchaseOrderResource::getGloballySearchableAttributes());
    }

    public function test_seller_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);

        $sellerA = Company::factory()->create(['types' => [Company::TYPE_SERVICE_ALL_SELLERS], 'is_active' => true]);
        $sellerB = Company::factory()->create(['types' => [Company::TYPE_SERVICE_ALL_SELLERS], 'is_active' => true]);
        $withSellerA = PurchaseOrder::factory()->create(['seller_id' => $sellerA->id]);
        $withSellerB = PurchaseOrder::factory()->create(['seller_id' => $sellerB->id]);

        Livewire::test(ListPurchaseOrders::class)
            ->filterTable('seller_id', $sellerA->id)
            ->assertCanSeeTableRecords([$withSellerA])
            ->assertCanNotSeeTableRecords([$withSellerB]);
    }

    public function test_buyer_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);

        $buyerA = Company::factory()->buyer()->create();
        $buyerB = Company::factory()->buyer()->create();
        $withBuyerA = PurchaseOrder::factory()->create(['buyer_id' => $buyerA->id]);
        $withBuyerB = PurchaseOrder::factory()->create(['buyer_id' => $buyerB->id]);

        Livewire::test(ListPurchaseOrders::class)
            ->filterTable('buyer_id', $buyerA->id)
            ->assertCanSeeTableRecords([$withBuyerA])
            ->assertCanNotSeeTableRecords([$withBuyerB]);
    }

    public function test_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);

        $statusA = $this->poStatus('FilterStatusA');
        $statusB = $this->poStatus('FilterStatusB');
        $withA = PurchaseOrder::factory()->create(['status_id' => $statusA->id]);
        $withB = PurchaseOrder::factory()->create(['status_id' => $statusB->id]);

        Livewire::test(ListPurchaseOrders::class)
            ->filterTable('status_id', $statusA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_incoterms_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);

        $withFob = PurchaseOrder::factory()->create(['incoterms' => 'fob']);
        $withExw = PurchaseOrder::factory()->create(['incoterms' => 'exw']);

        Livewire::test(ListPurchaseOrders::class)
            ->filterTable('incoterms', 'fob')
            ->assertCanSeeTableRecords([$withFob])
            ->assertCanNotSeeTableRecords([$withExw]);
    }

    public function test_currency_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);

        $currencyA = Currency::factory()->create();
        $currencyB = Currency::factory()->create();
        $withA = PurchaseOrder::factory()->create(['currency_id' => $currencyA->id]);
        $withB = PurchaseOrder::factory()->create(['currency_id' => $currencyB->id]);

        Livewire::test(ListPurchaseOrders::class)
            ->filterTable('currency_id', $currencyA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_creator_filter_narrows_the_table(): void
    {
        // UserStamps force-overwrites user_id to the acting user on creating(), so creatorA/B
        // must each be the acting user at creation time — passing user_id via the factory is
        // silently discarded (see testPattern.md / BankProfileResourceTest's identical fix).
        $creatorA = $this->actingAsUserWithPermissions(['purchase_order.view']);
        $withA = PurchaseOrder::factory()->create();

        $creatorB = User::factory()->create();
        $this->actingAs($creatorB);
        $withB = PurchaseOrder::factory()->create();

        $this->actingAs($creatorA);

        Livewire::test(ListPurchaseOrders::class)
            ->filterTable('user_id', $creatorA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_creation_date_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);

        $old = PurchaseOrder::factory()->create();
        PurchaseOrder::whereKey($old->id)->update(['created_at' => now()->subDays(30)]);
        $recent = PurchaseOrder::factory()->create();

        Livewire::test(ListPurchaseOrders::class)
            ->filterTable('created_at', ['created_from' => now()->subDays(2)->toDateString()])
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$old->fresh()]);
    }

    // Infolist — total_amount / total_quantity entries (accessor correctness itself is PurchaseOrderModelTest's job)

    public function test_total_amount_and_total_quantity_infolist_entries_render(): void
    {
        $record = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $record->id, 'quantity' => 4, 'unit_price' => 25]);
        $record->refresh();

        $amountEntry = PurchaseOrderResource::viewTotalAmount()->model($record);
        $quantityEntry = PurchaseOrderResource::viewTotalQuantity()->model($record);

        $this->assertSame(preciseNumber($record->total_amount), $amountEntry->formatState($record->total_amount));
        $this->assertSame(preciseNumber($record->total_quantity), $quantityEntry->formatState($record->total_quantity));
        $this->assertEquals(100, $record->total_amount);
    }

    // EAV custom attributes

    public function test_custom_attributes_map_does_not_render_a_blank_value_as_the_literal_word_null(): void
    {
        $record = PurchaseOrder::factory()->create();
        $record->syncCustomAttributes(['blank_field' => null, 'filled_field' => 'hello']);

        $map = $record->getCustomAttributesMap();

        $this->assertSame('', $map['blank_field']);
        $this->assertSame('hello', $map['filled_field']);
    }

    public function test_extra_attributes_form_tab_defers_loading_of_the_repeater_schema(): void
    {
        $tab = PurchaseOrderResource::getExtraAttributesFormTab();

        $reflection = new ReflectionProperty($tab, 'childComponents');
        $reflection->setAccessible(true);
        $schema = $reflection->getValue($tab)['default'];

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertTrue($schema->isLoadingDeferred());
    }

    public function test_extra_attributes_infolist_tab_defers_loading_of_the_repeater_schema(): void
    {
        $tab = PurchaseOrderResource::getExtraAttributesInfolistTab();

        $reflection = new ReflectionProperty($tab, 'childComponents');
        $reflection->setAccessible(true);
        $schema = $reflection->getValue($tab)['default'];

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertTrue($schema->isLoadingDeferred());
    }

    // Create — validation (§3d fillForm() quirk resolved 2026-09-26)

    public function test_create_requires_seller_buyer_currency_order_date_and_validity_date(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.create', 'purchase_order.view']);

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'seller_id' => null,
                'buyer_id' => null,
                'currency_id' => null,
                'order_date' => null,
                'validity_date' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['seller_id' => 'required', 'buyer_id' => 'required', 'currency_id' => 'required', 'order_date' => 'required', 'validity_date' => 'required']);
    }

    public function test_seller_and_buyer_must_differ(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.create', 'purchase_order.view']);
        $company = Company::factory()->create();

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'seller_id' => $company->id,
                'buyer_id' => $company->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['seller_id' => 'different']);
    }

    public function test_create_rejects_a_nonexistent_buyer_id_with_translated_message(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['purchase_order.create', 'purchase_order.view']);

        $test = Livewire::test(CreatePurchaseOrder::class)
            ->fillForm(['buyer_id' => 999999])
            ->call('create');

        $this->assertSame(
            [__('resources/purchaseOrder/strings.form.validation_in')],
            $test->errors()->get('data.buyer_id')
        );
    }

    public function test_creating_a_second_recent_order_for_the_same_seller_and_buyer_warns_but_does_not_block(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.create', 'purchase_order.view']);
        $seller = Company::factory()->create(['types' => [Company::TYPE_SERVICE_ALL_SELLERS], 'is_active' => true]);
        $buyer = Company::factory()->buyer()->create();
        $currency = Currency::factory()->create();

        $formData = fn () => [
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'currency_id' => $currency->id,
            'order_date' => now()->format('Y-m-d'),
            'validity_date' => now()->addDays(30)->format('Y-m-d'),
        ];

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm($formData())
            ->call('create')
            ->assertHasNoErrors();

        // Filament ≥4.13's Livewire dehydrate hook claims session notifications into
        // `filament.claimed_notifications` on every non-redirect request — including the
        // second component's initial mount — see testPattern.md §3d for the full gotcha.
        session()->forget(['filament.notifications', 'filament.claimed_notifications']);

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm($formData())
            ->call('create')
            ->assertHasNoErrors()
            ->assertNotified(__('resources/purchaseOrder/strings.notifications.duplicate_title'));

        $this->assertSame(2, PurchaseOrder::where('seller_id', $seller->id)->where('buyer_id', $buyer->id)->count());
    }

    // Needs Payment filter

    public function test_needs_payment_filter_narrows_the_table_to_orders_with_no_payments(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);
        $withPayment = PurchaseOrder::factory()->create();
        Payment::factory()->forTargetable($withPayment)->create();
        $withoutPayment = PurchaseOrder::factory()->create();

        Livewire::test(ListPurchaseOrders::class)
            ->filterTable('needs_payment')
            ->assertCanSeeTableRecords([$withoutPayment])
            ->assertCanNotSeeTableRecords([$withPayment]);
    }

    // Validity-lapsed badge (validity_date + payments_count)

    public function test_validity_date_shows_expired_badge_when_stale_and_no_payment_exists(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);
        $record = PurchaseOrder::factory()->create(['validity_date' => now()->subDay()]);

        Livewire::test(ListPurchaseOrders::class)
            ->assertTableColumnFormattedStateSet('validity_date', __('resources/purchaseOrder/strings.table.validity_expired'), $record);
    }

    public function test_validity_date_shows_plain_date_when_a_payment_already_exists(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);
        $record = PurchaseOrder::factory()->create(['validity_date' => now()->subDay()]);
        Payment::factory()->forTargetable($record)->create();
        $record = PurchaseOrder::withCount('payments')->findOrFail($record->id);

        Livewire::test(ListPurchaseOrders::class)
            ->assertTableColumnFormattedStateSet('validity_date', adaptiveDate($record->validity_date), $record);
    }

    public function test_validity_date_shows_plain_date_when_still_valid(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);
        $record = PurchaseOrder::factory()->create(['validity_date' => now()->addDays(30)]);

        Livewire::test(ListPurchaseOrders::class)
            ->assertTableColumnFormattedStateSet('validity_date', adaptiveDate($record->validity_date), $record);
    }

    // Edit — relationship-bound field (§3d fillForm() quirk resolved 2026-09-26)

    public function test_edit_page_loads_existing_values_and_persists_a_status_update(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.edit', 'purchase_order.view']);
        $statusA = $this->poStatus('EditStatusA');
        $statusB = $this->poStatus('EditStatusB');
        $record = PurchaseOrder::factory()->create([
            'status_id' => $statusA->id,
            'seller_id' => Company::factory()->create(['types' => [Company::TYPE_SERVICE_ALL_SELLERS], 'is_active' => true])->id,
            'buyer_id' => Company::factory()->buyer()->create()->id,
        ]);

        Livewire::test(EditPurchaseOrder::class, ['record' => $record->getRouteKey()])
            ->assertFormSet(['status_id' => $statusA->id])
            ->fillForm(['status_id' => $statusB->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($statusB->id, $record->fresh()->status_id);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view', 'purchase_order.delete', 'purchase_order.restore']);
        $record = PurchaseOrder::factory()->create();

        Livewire::test(ListPurchaseOrders::class)
            ->callTableAction('delete', $record);

        $this->assertNull(PurchaseOrder::find($record->id));
        $this->assertTrue(PurchaseOrder::withTrashed()->find($record->id)->trashed());

        Livewire::test(ListPurchaseOrders::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(PurchaseOrder::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view', 'purchase_order.delete']);
        $one = PurchaseOrder::factory()->create();
        $two = PurchaseOrder::factory()->create();

        Livewire::test(ListPurchaseOrders::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(PurchaseOrder::find($one->id));
        $this->assertNull(PurchaseOrder::find($two->id));
    }

    public function test_bulk_actions_toolbar_orders_export_before_delete_and_restore(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view', 'purchase_order.delete', 'purchase_order.restore']);

        Livewire::test(ListPurchaseOrders::class)
            ->assertTableBulkActionsExistInOrder(['exportPurchaseOrders', 'delete', 'restore']);
    }

    // Table — stacked on mobile

    public function test_table_is_stacked_on_mobile(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);

        $instance = Livewire::test(ListPurchaseOrders::class);

        $this->assertTrue($instance->instance()->getTable()->isStackedOnMobile());
    }

    // Global search contract

    public function test_global_search_title_uses_shopping_bag_emoji_prefix_and_po_number(): void
    {
        $record = PurchaseOrder::factory()->create();

        $this->assertSame('🛍️ '.$record->po_number, PurchaseOrderResource::getGlobalSearchResultTitle($record));
    }

    // Exporter — custom parent-row + item-row CSV (converted from Filament's native Exporter, see testPattern.md)

    public function test_exporter_escapes_formula_injection_in_creator_and_updater_names(): void
    {
        app()->setLocale('en');
        $seller = Company::factory()->create(['types' => [Company::TYPE_SERVICE_ALL_SELLERS], 'is_active' => true]);
        $buyer = Company::factory()->buyer()->create();
        $currency = Currency::factory()->create(['is_active' => true]);
        $status = $this->poStatus('CreatorEscapeCheck');

        $creator = User::factory()->create(['name' => '=1+1']);
        $this->actingAs($creator);
        $record = PurchaseOrder::factory()->create([
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'currency_id' => $currency->id,
            'status_id' => $status->id,
            'order_date' => '2026-01-15',
            'notes' => null,
            'shipping_address' => '123 Test Street',
            'packing_details' => null,
        ]);

        $updater = User::factory()->create(['name' => '+SUM(1,2)']);
        $this->actingAs($updater);
        $record->update(['notes' => 'triggers updated_by_id']);

        ['rows' => [$row]] = $this->exportToRows(PurchaseOrder::query()->whereKey($record->id));
        $labels = PurchaseOrderExporter::columnLabels();

        $this->assertSame("'=1+1", $row[$labels['creator']]);
        $this->assertSame("'+SUM(1,2)", $row[$labels['updater']]);
    }

    public function test_exporter_write_emits_a_parent_row_then_item_rows(): void
    {
        app()->setLocale('en');
        $seller = Company::factory()->create(['english_name' => 'Export Seller EN', 'types' => [Company::TYPE_SERVICE_ALL_SELLERS], 'is_active' => true]);
        $buyer = Company::factory()->buyer()->create(['english_name' => 'Export Buyer EN']);
        $currency = Currency::factory()->create(['english_name' => 'EXU1', 'is_active' => true]);
        $status = $this->poStatus('ExportStatusCheck');
        $product = Product::factory()->create();

        $record = PurchaseOrder::factory()->create([
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'currency_id' => $currency->id,
            'status_id' => $status->id,
            'order_date' => '2026-01-15',
            'notes' => null,
            'shipping_address' => '123 Test Street',
            'packing_details' => null,
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $record->id,
            'product_id' => $product->id,
            'quantity' => 3.5,
            'unit' => 'kg',
            'unit_price' => 12.25,
        ]);

        ['rows' => [$parentRow, $itemRow]] = $this->exportToRows(PurchaseOrder::query()->whereKey($record->id));

        $labels = PurchaseOrderExporter::columnLabels();

        $this->assertSame($record->po_number, $parentRow[$labels['po_number']]);
        $this->assertSame('Export Seller EN', $parentRow[$labels['seller_english']]);
        $this->assertSame('Export Buyer EN', $parentRow[$labels['buyer_english']]);
        $this->assertSame($status->english_name, $parentRow[$labels['status_english']]);
        $this->assertSame(jdate($record->order_date)->format('Y-m-d'), $parentRow[$labels['order_date']]);
        $this->assertSame('', $parentRow[$labels['product']]);

        $this->assertSame('', $itemRow[$labels['po_number']]);
        $this->assertSame($product->english_name, $itemRow[$labels['product']]);
        $this->assertSame((string) $record->items->first()->quantity, $itemRow[$labels['quantity']]);
        $this->assertSame('kg', $itemRow[$labels['unit']]);
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Export is a comprehensive reporting artifact (30 columns: every purchase_orders column
        // + item columns), independent of whatever the import column set chooses to accept — see
        // importsPattern.md's "export is deliberately wider than import" rule. Pinned so a future
        // edit can't silently drop/add columns on either side without this test failing loudly.
        $this->assertCount(22, PurchaseOrderImporter::getColumns());
        $this->assertCount(22, PurchaseOrderImporter::columnLabels());
        $this->assertCount(30, PurchaseOrderExporter::columnLabels());
    }

    public function test_infolist_status_history_tab_renders_and_badge_matches_history_count(): void
    {
        app()->setLocale('en');
        $po = PurchaseOrder::factory()->create();
        $po->update(['status_id' => Status::factory()->create()->id]);
        $po->load('statusHistories');

        $tab = $this->statusHistoryTab(PurchaseOrderResource::infolist(Schema::make()));

        $this->assertNotNull($tab);
        $this->assertSame($po->statusHistories->count(), $this->statusHistoryTabBadge($tab, $po));
    }

    public function test_payments_relation_manager_renders_for_a_purchase_order_with_a_payment(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view', 'payment.view']);

        $po = PurchaseOrder::factory()->create();
        Payment::factory()->forTargetable($po)->create();

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $po,
            'pageClass' => EditPurchaseOrder::class,
        ])->assertSuccessful();
    }

    public function test_payments_relation_manager_shows_the_gated_empty_state_when_owner_has_none(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view', 'payment.view']);

        $po = PurchaseOrder::factory()->create();

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $po,
            'pageClass' => EditPurchaseOrder::class,
        ])
            ->assertSee(__('resources/general/strings.empty_state.gated_heading'));
    }

    public function test_proforma_invoices_relation_manager_renders_for_a_purchase_order_with_a_linked_invoice(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view', 'proforma_invoice.view']);

        $po = PurchaseOrder::factory()->create();
        $invoice = ProformaInvoice::factory()->create();
        $po->proformaInvoices()->attach($invoice->id);

        Livewire::test(ProformaInvoicesRelationManager::class, [
            'ownerRecord' => $po,
            'pageClass' => EditPurchaseOrder::class,
        ])->assertSuccessful();
    }

    public function test_purchase_requests_relation_manager_renders_and_gates_attach_by_owner_status(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view', 'purchase_request.view']);

        $po = PurchaseOrder::factory()->create();
        $pr = PurchaseRequest::factory()->create();
        $po->purchaseRequests()->attach($pr->id);

        Livewire::test(PurchaseRequestsRelationManager::class, [
            'ownerRecord' => $po,
            'pageClass' => EditPurchaseOrder::class,
        ])->assertSuccessful();
    }

    public function test_purchase_requests_relation_manager_shows_the_true_empty_state_when_owner_has_no_linked_requests(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view', 'purchase_request.view']);

        $po = PurchaseOrder::factory()->create();

        Livewire::test(PurchaseRequestsRelationManager::class, [
            'ownerRecord' => $po,
            'pageClass' => EditPurchaseOrder::class,
        ])
            ->assertSee(__('resources/general/strings.empty_state.heading'))
            ->assertDontSee(__('resources/general/strings.empty_state.filtered_heading'));
    }

    public function test_registered_orders_relation_manager_renders_and_exports_without_error(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['purchase_order.view', 'registered_order.view']);

        $po = PurchaseOrder::factory()->create();
        $ro = RegisteredOrder::factory()->create();
        $po->registeredOrders()->attach($ro->id);

        Livewire::test(RegisteredOrdersRelationManager::class, [
            'ownerRecord' => $po,
            'pageClass' => EditPurchaseOrder::class,
        ])
            ->assertSuccessful()
            ->callTableBulkAction('exportRegisteredOrders', [$ro])
            ->assertHasNoTableBulkActionErrors();

        Queue::assertPushed(\App\Jobs\ExportRegisteredOrders::class);
    }

    // Status workflow wiring — safe no-op today (no admin-configured stage_order exists for PO's Status type)

    public function test_only_the_edit_page_exposes_the_status_workflow_pipeline_header_action(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.create', 'purchase_order.view', 'purchase_order.edit']);
        $po = PurchaseOrder::factory()->create();

        Livewire::test(ListPurchaseOrders::class)
            ->assertActionDoesNotExist('statusWorkflowPipeline');

        Livewire::test(EditPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->assertActionExists('statusWorkflowPipeline');
    }

    public function test_apply_initial_status_on_create_is_a_no_op_without_a_configured_stage_order(): void
    {
        $data = PurchaseOrderResource::applyInitialStatusOnCreate(['po_number' => 'PO-TEST']);

        $this->assertArrayNotHasKey('status_id', $data);
    }

    public function test_assert_status_transition_allowed_permits_any_status_without_a_configured_stage_order(): void
    {
        $status = $this->poStatus('Confirmed');

        PurchaseOrderResource::assertStatusTransitionAllowed(null, 'status_id', $status->id);

        $this->addToAssertionCount(1);
    }

    public function test_status_field_options_are_unrestricted_without_a_configured_stage_order(): void
    {
        $one = $this->poStatus('Draft');
        $two = $this->poStatus('Confirmed');

        $method = new ReflectionMethod(PurchaseOrderResource::class, 'availableStatusWorkflowIds');
        $method->setAccessible(true);

        $ids = $method->invoke(null, 'status_id', null);

        $this->assertContains($one->id, $ids);
        $this->assertContains($two->id, $ids);
    }

    public function test_status_field_stays_enabled_on_create_when_type_has_no_configured_workflow(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.create', 'purchase_order.view']);

        $instance = Livewire::test(CreatePurchaseOrder::class);

        $this->assertFalse($instance->instance()->form->getComponent('status_id')->isDisabled());
    }

    public function test_create_mutator_leaves_the_selected_status_unchanged_when_type_has_no_configured_workflow(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.create', 'purchase_order.view']);
        $status = $this->poStatus('Confirmed');

        $page = new CreatePurchaseOrder;
        $method = new ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $data = $method->invoke($page, ['status_id' => $status->id]);

        $this->assertSame($status->id, $data['status_id']);
    }

    public function test_edit_page_persists_a_status_update_when_type_has_no_configured_workflow(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.edit', 'purchase_order.view']);
        $initial = $this->poStatus('Draft');
        $target = $this->poStatus('Confirmed');
        $po = PurchaseOrder::factory()->create([
            'status_id' => $initial->id,
            'seller_id' => Company::factory()->seller()->create()->id,
            'buyer_id' => Company::factory()->buyer()->create()->id,
        ]);

        Livewire::test(EditPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->set('data.status_id', $target->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Confirmed', $po->fresh()->status->english_name);
    }

    public function test_status_workflow_gates_a_forward_transition_once_a_stage_order_is_configured(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.edit', 'purchase_order.view']);
        $draft = $this->poStatus('Draft', 1);
        $confirmed = $this->poStatus('Confirmed', 2, 'status.grant_purchase_order_confirmed');
        $po = PurchaseOrder::factory()->create(['status_id' => $draft->id]);

        Livewire::test(EditPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->set('data.status_id', $confirmed->id)
            ->call('save')
            ->assertHasFormErrors(['status_id']);

        $this->assertSame('Draft', $po->fresh()->status->english_name);
    }

    public function test_status_workflow_permits_a_forward_transition_with_the_granted_approval_permission_once_configured(): void
    {
        $draft = $this->poStatus('Draft', 1);
        $confirmed = $this->poStatus('Confirmed', 2, 'status.grant_purchase_order_confirmed');
        $po = PurchaseOrder::factory()->create([
            'status_id' => $draft->id,
            'seller_id' => Company::factory()->seller()->create()->id,
            'buyer_id' => Company::factory()->buyer()->create()->id,
        ]);

        $this->actingAsUserWithPermissions([
            'purchase_order.edit', 'purchase_order.view',
            'status.grant_purchase_order_confirmed',
        ]);

        Livewire::test(EditPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->set('data.status_id', $confirmed->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Confirmed', $po->fresh()->status->english_name);
    }

    public function test_edit_form_shows_a_status_select_for_each_attachment(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view', 'purchase_order.edit']);
        $po = PurchaseOrder::factory()->create();
        $uploaded = Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_UPLOADED);
        $attachment = Attachment::factory()->forAttachable($po)->create(['status_id' => $uploaded->id]);

        Livewire::test(EditPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->assertSee($attachment->name ?: basename($attachment->path))
            ->assertSee($uploaded->getLocalizedNameAttribute());
    }

    public function test_attachments_infolist_entry_wires_the_supersede_and_revert_actions(): void
    {
        $entry = collect($this->repeatableItemComponents(PurchaseOrderResource::viewAttachments()))
            ->first(fn ($component) => $component->getName() === 'status.name');

        $reflection = new ReflectionProperty($entry, 'suffixActions');
        $reflection->setAccessible(true);

        $names = collect($reflection->getValue($entry))->map(fn ($action) => $action->getName())->all();

        $this->assertSame(['supersedeAttachment', 'revertAttachment'], $names);
    }

    public function test_attachments_infolist_entry_splits_filename_and_status_three_to_two(): void
    {
        $entry = PurchaseOrderResource::viewAttachments();

        $columnsReflection = new ReflectionProperty($entry, 'columns');
        $columnsReflection->setAccessible(true);
        $this->assertSame(['lg' => 5], $columnsReflection->getValue($entry));

        $components = collect($this->repeatableItemComponents($entry))->keyBy(fn ($component) => $component->getName());

        $spanReflection = new ReflectionProperty($components['path'], 'columnSpan');
        $spanReflection->setAccessible(true);
        $this->assertSame(['default' => 1, 'lg' => 3], $spanReflection->getValue($components['path']));

        $spanReflection = new ReflectionProperty($components['status.name'], 'columnSpan');
        $spanReflection->setAccessible(true);
        $this->assertSame(['default' => 1, 'lg' => 2], $spanReflection->getValue($components['status.name']));
    }
}
