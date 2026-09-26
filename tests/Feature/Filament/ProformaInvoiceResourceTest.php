<?php

namespace Tests\Feature\Filament;

use App\Filament\Actions\GroupedImportAction;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Exports\ProformaInvoiceExporter;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Imports\ProformaInvoiceImporter;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Imports\ProformaInvoiceItemImporter;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Pages\ListProformaInvoices;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\EditPurchaseOrder;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\ProformaInvoicesRelationManager as PurchaseOrderProformaInvoicesRelationManager;
use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\EditPurchaseRequest;
use App\Filament\Resources\Operational\PurchaseRequestResource\RelationManagers\ProformaInvoicesRelationManager;
use App\Filament\Resources\Operational\RegisteredOrderResource\Pages\EditRegisteredOrder;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\ProformaInvoicesRelationManager as RegisteredOrderProformaInvoicesRelationManager;
use App\Filament\Resources\ProformaInvoiceResource;
use App\Jobs\ImportGroupedCsv;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\ProformaInvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\Imports\GroupRowFailedException;
use Filament\Actions\Imports\Models\Import;
use Filament\Infolists\Components\RepeatableEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class ProformaInvoiceResourceTest extends TestCase
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

    private function callProtectedStatic(string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod(ProformaInvoiceResource::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, ...$args);
    }

    private function repeatableItemComponents(RepeatableEntry $entry): array
    {
        $reflection = new ReflectionProperty($entry, 'childComponents');
        $reflection->setAccessible(true);

        return $reflection->getValue($entry)['default'];
    }

    // Permissions

    public function test_full_permissions_allow_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([
            'proforma_invoice.view',
            'proforma_invoice.create',
            'proforma_invoice.edit',
            'proforma_invoice.delete',
            'proforma_invoice.restore',
        ]);

        $record = ProformaInvoice::factory()->create();

        $this->assertTrue(ProformaInvoiceResource::canViewAny());
        $this->assertTrue(ProformaInvoiceResource::canCreate());
        $this->assertTrue(ProformaInvoiceResource::canEdit($record));
        $this->assertTrue(ProformaInvoiceResource::canDelete($record));
        $this->assertTrue(ProformaInvoiceResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = ProformaInvoice::factory()->create();

        $this->assertFalse(ProformaInvoiceResource::canViewAny());
        $this->assertFalse(ProformaInvoiceResource::canCreate());
        $this->assertFalse(ProformaInvoiceResource::canEdit($record));
        $this->assertFalse(ProformaInvoiceResource::canDelete($record));
        $this->assertFalse(ProformaInvoiceResource::canRestore($record));
    }

    // List — search, filters

    public function test_list_page_renders_and_search_finds_by_invoice_no(): void
    {
        $this->actingAsUserWithPermissions(['proforma_invoice.view']);

        $target = ProformaInvoice::factory()->create();
        $other = ProformaInvoice::factory()->create();

        ProformaInvoice::whereKey($target->id)->update(['invoice_no' => 'PI-SEARCH-TARGET-'.$target->id]);
        ProformaInvoice::whereKey($other->id)->update(['invoice_no' => 'PI-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();
        $term = 'TARGET-'.$target->id;

        Livewire::test(ListProformaInvoices::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_finds_records_by_extra_attribute_key_and_value(): void
    {
        $this->actingAsUserWithPermissions(['proforma_invoice.view']);

        $target = ProformaInvoice::factory()->create();
        $other = ProformaInvoice::factory()->create();
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(ListProformaInvoices::class)
            ->searchTable('contract_reference')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_proforma_invoices_relation_manager_search_finds_records_by_extra_attribute(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'proforma_invoice.view']);

        $pr = PurchaseRequest::factory()->create();
        $target = ProformaInvoice::factory()->create();
        $other = ProformaInvoice::factory()->create();
        $pr->proformaInvoices()->attach([$target->id, $other->id]);
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(ProformaInvoicesRelationManager::class, [
            'ownerRecord' => $pr,
            'pageClass' => EditPurchaseRequest::class,
        ])
            ->assertSuccessful()
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_registered_order_proforma_invoices_relation_manager_search_finds_records_by_extra_attribute(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view', 'proforma_invoice.view']);

        $ro = RegisteredOrder::factory()->create();
        $target = ProformaInvoice::factory()->create();
        $other = ProformaInvoice::factory()->create();
        $ro->proformaInvoices()->attach([$target->id, $other->id]);
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(RegisteredOrderProformaInvoicesRelationManager::class, [
            'ownerRecord' => $ro,
            'pageClass' => EditRegisteredOrder::class,
        ])
            ->assertSuccessful()
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_purchase_order_proforma_invoices_relation_manager_search_finds_records_by_extra_attribute(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view', 'proforma_invoice.view']);

        $po = PurchaseOrder::factory()->create();
        $target = ProformaInvoice::factory()->create();
        $other = ProformaInvoice::factory()->create();
        $po->proformaInvoices()->attach([$target->id, $other->id]);
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(PurchaseOrderProformaInvoicesRelationManager::class, [
            'ownerRecord' => $po,
            'pageClass' => EditPurchaseOrder::class,
        ])
            ->assertSuccessful()
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_with_extra_attributes_search_appends_the_shared_eav_columns(): void
    {
        $this->assertSame(
            ['invoice_no', 'contract_no', 'extraAttributes.key', 'extraAttributes.value'],
            ProformaInvoiceResource::withExtraAttributesSearch(['invoice_no', 'contract_no'])
        );
    }

    public function test_or_where_extra_attributes_match_filters_by_key_and_value(): void
    {
        $target = ProformaInvoice::factory()->create();
        $other = ProformaInvoice::factory()->create();
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        $byKey = ProformaInvoiceResource::orWhereExtraAttributesMatch(
            ProformaInvoice::query()->whereRaw('1 = 0'),
            'contract_reference'
        )->pluck('id')->all();
        $byValue = ProformaInvoiceResource::orWhereExtraAttributesMatch(
            ProformaInvoice::query()->whereRaw('1 = 0'),
            'ACME-9981'
        )->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$target->id], $byKey);
        $this->assertEqualsCanonicalizing([$target->id], $byValue);
        $this->assertNotContains($other->id, $byKey);
    }

    public function test_seller_company_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['proforma_invoice.view']);

        $sellerA = Company::factory()->seller()->create();
        $sellerB = Company::factory()->seller()->create();
        $inSellerA = ProformaInvoice::factory()->create(['seller_id' => $sellerA->id]);
        $inSellerB = ProformaInvoice::factory()->create(['seller_id' => $sellerB->id]);

        Livewire::test(ListProformaInvoices::class)
            ->filterTable('seller_id', $sellerA->id)
            ->assertCanSeeTableRecords([$inSellerA])
            ->assertCanNotSeeTableRecords([$inSellerB]);
    }

    public function test_invoice_date_filter_indicator_reports_the_selected_range(): void
    {
        $filter = ProformaInvoiceResource::getInvoiceDateFilter();
        $property = new ReflectionProperty($filter, 'indicateUsing');
        $property->setAccessible(true);
        $callback = $property->getValue($filter);

        $withRange = $callback(['invoice_date_from' => '2026-01-01', 'invoice_date_until' => null]);
        $withoutRange = $callback(['invoice_date_from' => null, 'invoice_date_until' => null]);

        $this->assertNotEmpty($withRange);
        $this->assertStringContainsString(__('resources/proformaInvoice/strings.filters.invoice_date_from'), (string) $withRange[0]);
        $this->assertSame([], $withoutRange);
    }

    public function test_needs_conversion_filter_narrows_to_invoices_without_registered_orders(): void
    {
        $this->actingAsUserWithPermissions(['proforma_invoice.view']);

        $withRo = ProformaInvoice::factory()->create();
        $ro = RegisteredOrder::factory()->create();
        $withRo->registeredOrders()->attach($ro->id);
        $withoutRo = ProformaInvoice::factory()->create();

        Livewire::test(ListProformaInvoices::class)
            ->filterTable('needs_conversion', true)
            ->assertCanSeeTableRecords([$withoutRo])
            ->assertCanNotSeeTableRecords([$withRo]);
    }

    // Stale-quote badge (validity_date + registered_orders_count)

    public function test_validity_date_shows_expired_badge_when_stale_and_unconverted(): void
    {
        $this->actingAsUserWithPermissions(['proforma_invoice.view']);
        $record = ProformaInvoice::factory()->create(['validity_date' => now()->subDay()]);

        Livewire::test(ListProformaInvoices::class)
            ->assertTableColumnFormattedStateSet('validity_date', __('resources/proformaInvoice/strings.table.validity_expired'), $record);
    }

    public function test_validity_date_shows_plain_date_when_already_converted_to_a_registered_order(): void
    {
        $this->actingAsUserWithPermissions(['proforma_invoice.view']);
        $record = ProformaInvoice::factory()->create(['validity_date' => now()->subDay()]);
        $ro = RegisteredOrder::factory()->create();
        $record->registeredOrders()->attach($ro->id);
        $record = ProformaInvoice::withCount('registeredOrders')->findOrFail($record->id);

        Livewire::test(ListProformaInvoices::class)
            ->assertTableColumnFormattedStateSet('validity_date', adaptiveDate($record->validity_date), $record);
    }

    public function test_validity_date_shows_plain_date_when_still_valid(): void
    {
        $this->actingAsUserWithPermissions(['proforma_invoice.view']);
        $record = ProformaInvoice::factory()->create(['validity_date' => now()->addDays(30)]);

        Livewire::test(ListProformaInvoices::class)
            ->assertTableColumnFormattedStateSet('validity_date', adaptiveDate($record->validity_date), $record);
    }

    // Budget-variance badge

    public function test_budget_variance_percent_reflects_overrun_against_linked_purchase_requests(): void
    {
        $pr = PurchaseRequest::factory()->create(['total_estimated_cost' => 100]);
        $record = ProformaInvoice::factory()->create(['total_amount' => 150]);
        $record->purchaseRequests()->attach($pr->id);
        $record->load('purchaseRequests');

        $this->assertSame(50.0, $this->callProtectedStatic('budgetVariancePercent', [$record]));
    }

    public function test_budget_variance_percent_is_null_without_linked_purchase_requests(): void
    {
        $record = ProformaInvoice::factory()->create();
        $record->setRelation('purchaseRequests', collect());

        $this->assertNull($this->callProtectedStatic('budgetVariancePercent', [$record]));
    }

    public function test_budget_variance_color_matches_severity_thresholds(): void
    {
        $this->assertSame('gray', $this->callProtectedStatic('budgetVarianceColor', [null]));
        $this->assertSame('success', $this->callProtectedStatic('budgetVarianceColor', [0.0]));
        $this->assertSame('success', $this->callProtectedStatic('budgetVarianceColor', [-15.0]));
        $this->assertSame('warning', $this->callProtectedStatic('budgetVarianceColor', [10.0]));
        $this->assertSame('danger', $this->callProtectedStatic('budgetVarianceColor', [25.0]));
    }

    // Infolist correctness audit

    public function test_seller_company_infolist_entry_shows_the_localized_name(): void
    {
        app()->setLocale('fa');
        $seller = Company::factory()->seller()->create(['name' => 'شرکت الف', 'english_name' => 'Alpha Co']);
        $record = ProformaInvoice::factory()->create(['seller_id' => $seller->id]);
        $record->load('sellerCompany');

        $entry = ProformaInvoiceResource::viewSellerCompany()->model($record);

        $this->assertSame('شرکت الف', $entry->formatState($record->sellerCompany?->name));
    }

    public function test_main_currency_infolist_entry_shows_the_localized_name(): void
    {
        app()->setLocale('en');
        $currency = Currency::factory()->create(['name' => 'ریال', 'english_name' => 'Rial', 'is_active' => true]);
        $record = ProformaInvoice::factory()->create(['main_currency_id' => $currency->id]);
        $record->load('mainCurrency');

        $entry = ProformaInvoiceResource::viewMainCurrency()->model($record);

        $this->assertSame('Rial', $entry->formatState($record->mainCurrency?->name));
    }

    public function test_beneficiary_country_infolist_entry_returns_null_instead_of_crashing_when_blank(): void
    {
        $record = ProformaInvoice::factory()->create(['beneficiary_country' => null]);
        $entry = ProformaInvoiceResource::viewBeneficiaryCountry()->model($record);

        $this->assertNull($entry->formatState(null));
    }

    public function test_origin_and_destination_country_infolist_entries_return_null_instead_of_crashing_when_blank(): void
    {
        $record = ProformaInvoice::factory()->create(['origin_country' => null, 'destination_country' => null]);

        $this->assertNull(ProformaInvoiceResource::viewOriginCountry()->model($record)->formatState(null));
        $this->assertNull(ProformaInvoiceResource::viewDestinationCountry()->model($record)->formatState(null));
    }

    // Infolist — compact items row (product/quantity/unit/unit_price/total_amount, no origin/hs_code/weights/description)

    public function test_invoice_items_infolist_entry_keeps_only_the_compact_field_set(): void
    {
        $names = collect($this->repeatableItemComponents(ProformaInvoiceResource::viewInvoiceItems()))
            ->map(fn ($component) => $component->getName())
            ->all();

        $this->assertSame(['product.name', 'quantity', 'unit', 'unit_price', 'total_amount'], $names);
    }

    public function test_invoice_items_infolist_entry_renders_the_kept_fields(): void
    {
        app()->setLocale('en');
        $product = Product::factory()->create(['name' => 'محصول الف', 'english_name' => 'Alpha Product']);
        $item = ProformaInvoiceItem::factory()->make([
            'quantity' => 5,
            'unit' => 'kg',
            'unit_price' => 12.5,
            'total_amount' => 62.5,
        ]);
        $item->setRelation('product', $product);

        $components = collect($this->repeatableItemComponents(ProformaInvoiceResource::viewInvoiceItems()))->keyBy(fn ($c) => $c->getName());

        $this->assertSame('Alpha Product', $components['product.name']->model($item)->formatState($item->product?->getLocalizedNameAttribute()));
        $this->assertSame(preciseNumber(5), $components['quantity']->model($item)->formatState(5));
        $this->assertSame(__('resources/general/strings.metrics.kg'), $components['unit']->model($item)->formatState('kg'));
        $this->assertSame(preciseNumber(12.5), $components['unit_price']->model($item)->formatState(12.5));
        $this->assertSame(preciseNumber(62.5), $components['total_amount']->model($item)->formatState(62.5));
    }

    // EAV custom-attribute null-safety (KeyValue "Manage Custom Attributes" entry point)

    public function test_custom_attributes_map_does_not_render_a_blank_value_as_the_literal_word_null(): void
    {
        $record = ProformaInvoice::factory()->create();
        $record->syncCustomAttributes(['blank_field' => null, 'filled_field' => 'hello']);

        $map = $record->getCustomAttributesMap();

        $this->assertSame('', $map['blank_field']);
        $this->assertSame('hello', $map['filled_field']);
    }

    // Create

    public function test_create_requires_seller_buyer_and_main_currency(): void
    {
        $this->actingAsUserWithPermissions(['proforma_invoice.create', 'proforma_invoice.view']);

        Livewire::test(\App\Filament\Resources\Operational\ProformaInvoiceResource\Pages\CreateProformaInvoice::class)
            ->fillForm([
                'seller_id' => null,
                'buyer_id' => null,
                'main_currency_id' => null,
                'invoice_no' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['seller_id' => 'required', 'buyer_id' => 'required', 'main_currency_id' => 'required']);
    }

    public function test_create_happy_path_saves_a_new_proforma_invoice(): void
    {
        $this->markTestSkipped('fillForm() harness quirk — see tests/testPattern.md §3d. Verified working in real browser QA.');

        $this->actingAsUserWithPermissions(['proforma_invoice.create', 'proforma_invoice.view']);
        $seller = Company::factory()->seller()->create();
        $buyer = Company::factory()->buyer()->create();
        $currency = Currency::factory()->create();

        Livewire::test(\App\Filament\Resources\Operational\ProformaInvoiceResource\Pages\CreateProformaInvoice::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'buyer_id' => $buyer->id,
                'main_currency_id' => $currency->id,
                'invoice_date' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame(1, ProformaInvoice::where('seller_id', $seller->id)->count());
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['proforma_invoice.view', 'proforma_invoice.delete', 'proforma_invoice.restore']);
        $record = ProformaInvoice::factory()->create();

        Livewire::test(ListProformaInvoices::class)
            ->callTableAction('delete', $record);

        $this->assertNull(ProformaInvoice::find($record->id));
        $this->assertTrue(ProformaInvoice::withTrashed()->find($record->id)->trashed());

        Livewire::test(ListProformaInvoices::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(ProformaInvoice::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['proforma_invoice.view', 'proforma_invoice.delete']);
        $one = ProformaInvoice::factory()->create();
        $two = ProformaInvoice::factory()->create();

        Livewire::test(ListProformaInvoices::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(ProformaInvoice::find($one->id));
        $this->assertNull(ProformaInvoice::find($two->id));
    }

    // Import / Export — merged single-file grouped bulk transfer

    private function mergedColumnMap(): array
    {
        $names = collect(ProformaInvoiceImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeMergedImporter(array $parentData, array $itemRows = [], array $options = []): ProformaInvoiceImporter
    {
        $importer = new ProformaInvoiceImporter(new Import, $this->mergedColumnMap(), array_merge([
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

    private function makePersistedImport(int $totalRows): Import
    {
        $import = new Import;
        $import->importer = ProformaInvoiceImporter::class;
        $import->file_name = 'test.csv';
        $import->file_path = '/tmp/test.csv';
        $import->total_rows = $totalRows;
        $import->user_id = User::factory()->create()->id;
        $import->save();

        return $import;
    }

    private function groupedImportAction(): GroupedImportAction
    {
        return GroupedImportAction::make('t')
            ->itemDiscriminatorColumn('product_id')
            ->itemOnlyColumns(['origin', 'hs_code', 'unit', 'quantity', 'unit_price', 'net_weight', 'gross_weight', 'item_freight_charges', 'item_total_amount', 'description']);
    }

    public function test_import_end_to_end_creates_parent_with_items_via_after_save_hook(): void
    {
        $seller = Company::factory()->seller()->create();
        $buyer = Company::factory()->buyer()->create();
        $mainCurrency = Currency::factory()->create();
        $productOne = Product::factory()->create();
        $productTwo = Product::factory()->create();

        $importer = $this->invokeMergedImporter([
            'invoice_no' => '',
            'invoice_date' => '2026-06-01',
            'contract_no' => 'CT-100',
            'seller_id' => $seller->name,
            'buyer_id' => $buyer->name,
            'beneficiary_country' => 'US',
            'transport_mode' => 'Sea',
            'delivery_terms' => 'FOB',
            'main_currency_id' => $mainCurrency->name,
            'discount' => '0',
            'total_amount' => '1000',
            'notes' => 'Imported parent row',
        ], [
            ['product_id' => $productOne->code, 'unit' => 'kg', 'quantity' => '5', 'unit_price' => '10', 'item_total_amount' => '50', 'description' => 'First item'],
            ['product_id' => $productTwo->code, 'unit' => 'pcs', 'quantity' => '2', 'unit_price' => '20', 'item_total_amount' => '40', 'description' => 'Second item'],
        ]);

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertNotEmpty($record->invoice_no);
        $this->assertMatchesRegularExpression('/^PI-\d{6}(-\d+)?$/', $record->invoice_no);
        $this->assertSame('sea', $record->transport_mode);
        $this->assertSame('fob', $record->delivery_terms);
        $this->assertSame(2, $record->items()->count());
        $this->assertSame(1, $record->items()->where('product_id', $productOne->id)->count());
        $this->assertSame(1, $record->items()->where('product_id', $productTwo->id)->count());
    }

    public function test_import_parent_with_zero_items_saves_cleanly(): void
    {
        $seller = Company::factory()->seller()->create();
        $mainCurrency = Currency::factory()->create();

        $importer = $this->invokeMergedImporter([
            'invoice_no' => '',
            'invoice_date' => '2026-06-01',
            'seller_id' => $seller->name,
            'main_currency_id' => $mainCurrency->name,
            'total_amount' => '0',
        ]);

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertSame(0, $record->items()->count());
    }

    public function test_import_duplicate_product_within_same_group_is_rejected(): void
    {
        $mainCurrency = Currency::factory()->create();
        $product = Product::factory()->create();

        $countBefore = ProformaInvoice::withTrashed()->count();

        try {
            $this->invokeMergedImporter([
                'invoice_no' => '',
                'invoice_date' => '2026-06-01',
                'main_currency_id' => $mainCurrency->name,
                'total_amount' => '10',
            ], [
                ['product_id' => $product->code, 'unit' => 'kg', 'quantity' => '1'],
                ['product_id' => $product->code, 'unit' => 'kg', 'quantity' => '2'],
            ]);

            $this->fail('Expected GroupRowFailedException was not thrown.');
        } catch (GroupRowFailedException) {
            $this->assertSame($countBefore, ProformaInvoice::withTrashed()->count(), 'A failed item row must roll back the parent record too — no orphan proforma invoice should be persisted.');
        }
    }

    public function test_import_reupload_of_existing_invoice_no_updates_in_place(): void
    {
        $mainCurrency = Currency::factory()->create();
        $product = Product::factory()->create();

        $importer = $this->invokeMergedImporter([
            'invoice_no' => '',
            'invoice_date' => '2026-06-01',
            'main_currency_id' => $mainCurrency->name,
            'total_amount' => '10',
            'notes' => 'Original',
        ], [
            ['product_id' => $product->code, 'unit' => 'kg', 'quantity' => '1'],
        ]);

        $record = $importer->getRecord();
        $newSeller = Company::factory()->seller()->create();

        $this->invokeMergedImporter([
            'invoice_no' => $record->invoice_no,
            'invoice_date' => '2026-06-02',
            'seller_id' => $newSeller->name,
            'main_currency_id' => $mainCurrency->name,
            'total_amount' => '20',
            'notes' => 'Updated via re-upload',
        ], [
            ['product_id' => $product->code, 'unit' => 'kg', 'quantity' => '9'],
        ]);

        $this->assertSame(1, ProformaInvoice::withTrashed()->where('invoice_no', $record->invoice_no)->count());

        $record->refresh();
        $this->assertSame($newSeller->id, $record->seller_id);
        $this->assertSame('Updated via re-upload', $record->notes);
        $this->assertSame(1, $record->items()->count());
        $this->assertSame('9.00000', (string) $record->items()->first()->quantity);
    }

    public function test_import_trashed_parent_match_refuses_cleanly(): void
    {
        $record = ProformaInvoice::factory()->create();
        $record->delete();

        $mainCurrency = Currency::factory()->create();

        try {
            $this->invokeMergedImporter([
                'invoice_no' => $record->invoice_no,
                'invoice_date' => '2026-06-01',
                'main_currency_id' => $mainCurrency->name,
                'total_amount' => '10',
            ]);

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertStringContainsString($record->invoice_no, $exception->getMessage());
        }
    }

    public function test_import_columns_are_labeled_with_the_db_column_name_in_parentheses(): void
    {
        $labels = collect(ProformaInvoiceImporter::getColumns())->mapWithKeys(fn ($column) => [$column->getName() => $column->getLabel()]);

        $this->assertStringEndsWith('(product_id)', $labels['product_id']);
        $this->assertStringEndsWith('(main_currency_id)', $labels['main_currency_id']);
        $this->assertStringEndsWith('(item_total_amount)', $labels['item_total_amount']);
        $this->assertStringEndsWith('(transport_mode)', $labels['transport_mode']);
    }

    public function test_import_modal_description_hides_no_columns_when_nothing_is_auto_filled(): void
    {
        $this->actingAsUserWithPermissions([
            'proforma_invoice.view', 'proforma_invoice.create', 'proforma_invoice.edit',
        ]);

        $instance = Livewire::test(ListProformaInvoices::class)
            ->mountAction('importProformaInvoices');

        $description = (string) $instance->instance()->getMountedAction()->getModalDescription();

        $this->assertSame([], ProformaInvoiceImporter::autoFilledColumnNames());
        $this->assertStringNotContainsString('data-field-wrapper', $description);
    }

    public function test_import_defaults_invoice_date_to_today_when_blank_on_a_new_record(): void
    {
        $mainCurrency = Currency::factory()->create();

        $importer = $this->invokeMergedImporter([
            'invoice_no' => '',
            'invoice_date' => '',
            'main_currency_id' => $mainCurrency->name,
            'total_amount' => '10',
        ]);

        $record = $importer->getRecord();

        $this->assertSame(now()->toDateString(), $record->invoice_date->toDateString());
    }

    public function test_import_rejects_a_new_record_when_main_currency_is_blank_instead_of_a_raw_sql_error(): void
    {
        try {
            $this->invokeMergedImporter([
                'invoice_no' => '',
                'invoice_date' => '2026-06-01',
                'main_currency_id' => null,
                'total_amount' => '10',
            ]);

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertStringContainsString(trans('resources/proformaInvoice/strings.form.main_currency', [], 'en'), $exception->getMessage());
            $this->assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    public function test_import_rejects_an_item_row_with_a_blank_unit_instead_of_a_raw_sql_error(): void
    {
        $mainCurrency = Currency::factory()->create();
        $product = Product::factory()->create();

        $this->expectException(GroupRowFailedException::class);

        $this->invokeMergedImporter([
            'invoice_no' => '',
            'invoice_date' => '2026-06-01',
            'main_currency_id' => $mainCurrency->name,
            'total_amount' => '10',
        ], [
            ['product_id' => $product->code, 'unit' => '', 'quantity' => '1'],
        ]);
    }

    public function test_import_still_rejects_an_unresolvable_seller_value(): void
    {
        $mainCurrency = Currency::factory()->create();

        try {
            $this->invokeMergedImporter([
                'invoice_no' => '',
                'invoice_date' => '2026-06-01',
                'seller_id' => 'NoSuchSellerAtAll',
                'main_currency_id' => $mainCurrency->name,
                'total_amount' => '10',
            ]);

            $this->fail('Expected ValidationException was not thrown.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertStringContainsString('NoSuchSellerAtAll', collect($exception->errors())->flatten()->implode(' '));
        }
    }

    public function test_import_sets_the_six_optional_match_columns_to_null_on_mismatch_and_logs_notes_instead_of_rejecting(): void
    {
        $seller = Company::factory()->seller()->create();
        $mainCurrency = Currency::factory()->create();

        $importer = $this->invokeMergedImporter([
            'invoice_no' => '',
            'invoice_date' => '2026-06-01',
            'seller_id' => $seller->name,
            'main_currency_id' => $mainCurrency->name,
            'secondary_currency_id' => 'NoSuchCurrencyXYZ',
            'beneficiary_country' => 'NowhereBeneficiary',
            'origin_country' => 'NowhereOrigin',
            'destination_country' => 'NowhereDestination',
            'transport_mode' => 'TeleportMode',
            'delivery_terms' => 'MagicTerms',
            'total_amount' => '10',
            'notes' => '',
        ]);

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertNull($record->secondary_currency_id);
        $this->assertNull($record->beneficiary_country);
        $this->assertNull($record->origin_country);
        $this->assertNull($record->destination_country);
        $this->assertNull($record->transport_mode);
        $this->assertNull($record->delivery_terms);
        $this->assertStringContainsString('NoSuchCurrencyXYZ', $record->notes);
        $this->assertStringContainsString('NowhereBeneficiary', $record->notes);
        $this->assertStringContainsString('NowhereOrigin', $record->notes);
        $this->assertStringContainsString('NowhereDestination', $record->notes);
        $this->assertStringContainsString('TeleportMode', $record->notes);
        $this->assertStringContainsString('MagicTerms', $record->notes);
    }

    public function test_import_reupload_with_an_unresolvable_secondary_currency_preserves_the_existing_value_and_logs_a_note(): void
    {
        $mainCurrency = Currency::factory()->create();
        $secondaryCurrency = Currency::factory()->create();

        $importer = $this->invokeMergedImporter([
            'invoice_no' => '',
            'invoice_date' => '2026-06-01',
            'main_currency_id' => $mainCurrency->name,
            'secondary_currency_id' => $secondaryCurrency->name,
            'total_amount' => '10',
            'notes' => 'Original',
        ]);

        $record = $importer->getRecord();

        $this->invokeMergedImporter([
            'invoice_no' => $record->invoice_no,
            'invoice_date' => '2026-06-02',
            'main_currency_id' => $mainCurrency->name,
            'secondary_currency_id' => 'NoSuchCurrencyOnReupload',
            'total_amount' => '20',
            'notes' => 'Original',
        ]);

        $record->refresh();

        $this->assertSame($secondaryCurrency->id, $record->secondary_currency_id);
        $this->assertStringContainsString('NoSuchCurrencyOnReupload', $record->notes);
    }

    // Export

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'pi_export_').'.csv';
        ProformaInvoiceExporter::write($query, $path);

        $csv = (string) file_get_contents($path);
        unlink($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", trim(ltrim($csv, "\xEF\xBB\xBF"))))));
        $header = str_getcsv($lines[0]);
        $rows = array_map(fn (string $line) => array_combine($header, str_getcsv($line)), array_slice($lines, 1));

        return ['header' => $header, 'rows' => $rows];
    }

    public function test_exporter_write_emits_a_parent_row_then_item_rows(): void
    {
        app()->setLocale('en');
        $seller = Company::factory()->seller()->create(['english_name' => 'Export Seller EN']);
        $mainCurrency = Currency::factory()->create(['english_name' => 'Export Currency EN']);
        $product = Product::factory()->create();

        $record = ProformaInvoice::factory()->create([
            'seller_id' => $seller->id,
            'main_currency_id' => $mainCurrency->id,
            'invoice_date' => '2026-01-15',
            'total_amount' => 1234.5,
            'notes' => null,
        ]);
        ProformaInvoiceItem::factory()->create([
            'proforma_invoice_id' => $record->id,
            'product_id' => $product->id,
            'quantity' => 3.5,
            'unit' => 'kg',
            'unit_price' => 12.25,
            'description' => null,
        ]);

        ['rows' => [$parentRow, $itemRow]] = $this->exportToRows(ProformaInvoice::query()->whereKey($record->id));

        $labels = ProformaInvoiceImporter::columnLabels();

        $this->assertSame($record->invoice_no, $parentRow[$labels['invoice_no']]);
        $this->assertSame('Export Seller EN', $parentRow[$labels['seller_id']]);
        $this->assertSame(jdate($record->invoice_date)->format('Y-m-d'), $parentRow[$labels['invoice_date']]);
        $this->assertSame((string) $record->total_amount, $parentRow[$labels['total_amount']]);
        $this->assertSame('', $parentRow[$labels['product_id']]);

        $this->assertSame('', $itemRow[$labels['invoice_no']]);
        $this->assertSame($product->english_name, $itemRow[$labels['product_id']]);
        $this->assertSame((string) $record->items->first()->quantity, $itemRow[$labels['quantity']]);
    }

    public function test_exporter_localizes_seller_and_currency_for_the_active_locale(): void
    {
        app()->setLocale('fa');
        $seller = Company::factory()->seller()->create(['name' => 'شرکت تست', 'english_name' => 'Test Co EN']);
        $currency = Currency::factory()->create(['name' => 'ریال', 'english_name' => 'Rial']);
        $record = ProformaInvoice::factory()->create(['seller_id' => $seller->id, 'main_currency_id' => $currency->id]);

        ['rows' => [$parentRow]] = $this->exportToRows(ProformaInvoice::query()->whereKey($record->id));
        $labels = ProformaInvoiceImporter::columnLabels();

        $this->assertSame('شرکت تست', $parentRow[$labels['seller_id']]);
        $this->assertSame('ریال', $parentRow[$labels['main_currency_id']]);
    }

    public function test_exporter_strips_html_tags_from_notes(): void
    {
        $record = ProformaInvoice::factory()->create(['notes' => '<p>Payment confirmed <strong>urgently</strong></p>']);
        ProformaInvoiceItem::factory()->create(['proforma_invoice_id' => $record->id, 'description' => null]);

        ['rows' => [$parentRow]] = $this->exportToRows(ProformaInvoice::query()->whereKey($record->id));
        $labels = ProformaInvoiceImporter::columnLabels();

        $this->assertSame('Payment confirmed urgently', $parentRow[$labels['notes']]);
    }

    public function test_export_then_reimport_round_trip_produces_no_changes_and_no_failures(): void
    {
        $seller = Company::factory()->seller()->create(['english_name' => 'RoundTrip Seller EN']);
        $mainCurrency = Currency::factory()->create(['english_name' => 'RoundTrip Currency EN']);
        $product = Product::factory()->create();

        $record = ProformaInvoice::factory()->create([
            'seller_id' => $seller->id,
            'main_currency_id' => $mainCurrency->id,
            'invoice_date' => '2026-02-10',
            'transport_mode' => 'sea',
            'delivery_terms' => 'fob',
            'total_amount' => 500,
            'notes' => 'Round trip note',
        ]);
        ProformaInvoiceItem::factory()->create([
            'proforma_invoice_id' => $record->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'unit' => 'kg',
            'unit_price' => 8,
            'description' => 'Round trip item note',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'pi_export_').'.csv';
        ProformaInvoiceExporter::write(ProformaInvoice::query()->whereKey($record->id), $path);
        $csv = (string) file_get_contents($path);
        unlink($path);

        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", trim(ltrim($csv, "\xEF\xBB\xBF"))))));
        $header = str_getcsv($lines[0]);
        $dataLines = array_slice($lines, 1);

        $labels = ProformaInvoiceImporter::columnLabels();
        $columnMap = $labels;

        $physicalRows = [];
        foreach ($dataLines as $i => $line) {
            $physicalRows[] = ['physicalRow' => $i + 2, 'data' => array_combine($header, str_getcsv($line))];
        }

        $action = $this->groupedImportAction();
        $groupRowsMethod = new ReflectionMethod($action, 'groupRows');
        $groupRowsMethod->setAccessible(true);
        [$groups, $failures] = $groupRowsMethod->invoke($action, $physicalRows, $columnMap);

        $this->assertSame([], $failures);
        $this->assertCount(1, $groups);

        $import = $this->makePersistedImport(count($physicalRows));

        $job = new ImportGroupedCsv($import, $groups, $columnMap, ['locale' => 'en', 'jalali' => true, 'date_format' => 'Y-m-d']);
        $job->handle();

        $import->refresh();

        $this->assertSame(count($physicalRows), $import->successful_rows);
        $this->assertSame(0, $import->failedRows()->count());

        $this->assertSame(1, ProformaInvoice::where('invoice_no', $record->invoice_no)->count());

        $record->refresh();
        $this->assertSame($seller->id, $record->seller_id);
        $this->assertSame('sea', $record->transport_mode);
        $this->assertSame(1, $record->items()->count());
        $this->assertSame($product->id, $record->items()->first()->product_id);
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Pinned count — a silent column drop during a future refactor (exactly what happened to BankProfile's exporter this session) must fail this test, not slip through unnoticed.
        $this->assertCount(32, ProformaInvoiceImporter::getColumns());
        $this->assertCount(11, ProformaInvoiceItemImporter::getColumns());
        $this->assertCount(32, ProformaInvoiceImporter::columnLabels());
    }

    // Global search contract

    public function test_global_search_title_uses_document_emoji_prefix_and_invoice_no(): void
    {
        $record = ProformaInvoice::factory()->create();

        $this->assertSame('📝 '.$record->invoice_no, ProformaInvoiceResource::getGlobalSearchResultTitle($record));
    }

    public function test_globally_searchable_attributes_include_invoice_no_and_eav_columns(): void
    {
        $attributes = ProformaInvoiceResource::getGloballySearchableAttributes();

        $this->assertContains('invoice_no', $attributes);
        $this->assertContains('extraAttributes.key', $attributes);
        $this->assertContains('extraAttributes.value', $attributes);
    }
}
