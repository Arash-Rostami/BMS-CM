<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Operational\BankProfileResource\Pages\EditBankProfile;
use App\Filament\Resources\Operational\BankProfileResource\RelationManagers\RegisteredOrdersRelationManager as BankProfileRegisteredOrdersRelationManager;
use App\Filament\Resources\Operational\CustomResource\Pages\EditCustom;
use App\Filament\Resources\Operational\CustomResource\RelationManagers\RegisteredOrderRelationManager as CustomRegisteredOrderRelationManager;
use App\Filament\Resources\Operational\PaymentResource\Pages\EditPayment;
use App\Filament\Resources\Operational\PaymentResource\RelationManagers\RegisteredOrderRelationManager as PaymentRegisteredOrderRelationManager;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Pages\EditProformaInvoice;
use App\Filament\Resources\Operational\ProformaInvoiceResource\RelationManagers\RegisteredOrderRelationManager as ProformaInvoiceRegisteredOrderRelationManager;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\EditPurchaseOrder;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\RegisteredOrdersRelationManager as PurchaseOrderRegisteredOrdersRelationManager;
use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\EditPurchaseRequest;
use App\Filament\Resources\Operational\PurchaseRequestResource\RelationManagers\RegisteredOrderRelationManager;
use App\Filament\Resources\Operational\RegisteredOrderResource\Exports\RegisteredOrderExporter;
use App\Filament\Resources\Operational\RegisteredOrderResource\Imports\RegisteredOrderImporter;
use App\Filament\Resources\Operational\RegisteredOrderResource\Imports\RegisteredOrderItemImporter;
use App\Filament\Resources\Operational\RegisteredOrderResource\Pages\CreateRegisteredOrder;
use App\Filament\Resources\Operational\RegisteredOrderResource\Pages\EditRegisteredOrder;
use App\Filament\Resources\Operational\RegisteredOrderResource\Pages\ListRegisteredOrders;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\PurchaseRequestsRelationManager;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\HandleStatusMutation;
use App\Filament\Resources\Operational\ShipmentResource\Pages\EditShipment;
use App\Filament\Resources\Operational\ShipmentResource\RelationManagers\RegisteredOrderRelationManager as ShipmentRegisteredOrderRelationManager;
use App\Filament\Resources\RegisteredOrderResource;
use App\Models\BankProfile;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Custom;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\RegisteredOrderItem;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Services\Imports\GroupRowFailedException;
use App\Services\SmartCacheManager;
use Filament\Actions\Imports\Models\Import;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class RegisteredOrderResourceTest extends TestCase
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

    private function roStatus(string $englishName, ?int $stageOrder = null): Status
    {
        return Status::factory()->create([
            'type' => RegisteredOrder::TYPE_REGISTERED_ORDER,
            'english_type' => RegisteredOrder::TYPE_REGISTERED_ORDER,
            'name' => $englishName,
            'english_name' => $englishName,
            'stage_order' => $stageOrder,
        ]);
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

    // Permissions

    public function test_full_permissions_allow_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([
            'registered_order.view',
            'registered_order.create',
            'registered_order.edit',
            'registered_order.delete',
            'registered_order.restore',
        ]);

        $record = RegisteredOrder::factory()->create();

        $this->assertTrue(RegisteredOrderResource::canViewAny());
        $this->assertTrue(RegisteredOrderResource::canCreate());
        $this->assertTrue(RegisteredOrderResource::canEdit($record));
        $this->assertTrue(RegisteredOrderResource::canDelete($record));
        $this->assertTrue(RegisteredOrderResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = RegisteredOrder::factory()->create();

        $this->assertFalse(RegisteredOrderResource::canViewAny());
        $this->assertFalse(RegisteredOrderResource::canCreate());
        $this->assertFalse(RegisteredOrderResource::canEdit($record));
        $this->assertFalse(RegisteredOrderResource::canDelete($record));
        $this->assertFalse(RegisteredOrderResource::canRestore($record));
    }

    // List — search, filters

    public function test_list_page_renders_and_search_finds_by_ro_number(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);

        $target = RegisteredOrder::factory()->create();
        $other = RegisteredOrder::factory()->create();

        RegisteredOrder::whereKey($target->id)->update(['ro_number' => 'RO-SEARCH-TARGET-'.$target->id]);
        RegisteredOrder::whereKey($other->id)->update(['ro_number' => 'RO-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();
        $term = 'TARGET-'.$target->id;

        Livewire::test(ListRegisteredOrders::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_finds_records_by_extra_attribute_key_and_value(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);

        $target = RegisteredOrder::factory()->create();
        $other = RegisteredOrder::factory()->create();
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(ListRegisteredOrders::class)
            ->searchTable('contract_reference')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_registered_order_relation_manager_search_finds_records_by_extra_attribute(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'registered_order.view']);

        $pr = PurchaseRequest::factory()->create();
        $target = RegisteredOrder::factory()->create();
        $other = RegisteredOrder::factory()->create();
        $pr->registeredOrders()->attach([$target->id, $other->id]);
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(RegisteredOrderRelationManager::class, [
            'ownerRecord' => $pr,
            'pageClass' => EditPurchaseRequest::class,
        ])
            ->assertSuccessful()
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_purchase_requests_relation_manager_search_by_id_has_no_ambiguous_column_error(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view', 'purchase_request.view']);

        $ro = RegisteredOrder::factory()->create();
        $target = PurchaseRequest::factory()->create();
        $other = PurchaseRequest::factory()->create();
        $ro->purchaseRequests()->attach([$target->id, $other->id]);

        Livewire::test(PurchaseRequestsRelationManager::class, [
            'ownerRecord' => $ro,
            'pageClass' => EditRegisteredOrder::class,
        ])
            ->assertSuccessful()
            ->searchTable((string) $target->id)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_shipment_registered_order_relation_manager_search_finds_records_by_extra_attribute(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'registered_order.view']);

        $target = Shipment::factory()->create();
        $other = Shipment::factory()->create();
        $target->registeredOrder->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(ShipmentRegisteredOrderRelationManager::class, [
            'ownerRecord' => $target,
            'pageClass' => EditShipment::class,
        ])
            ->assertSuccessful()
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target->registeredOrder])
            ->assertCanNotSeeTableRecords([$other->registeredOrder]);
    }

    public function test_custom_registered_order_relation_manager_search_finds_records_by_extra_attribute(): void
    {
        $this->actingAsUserWithPermissions(['custom.view', 'registered_order.view']);

        $target = Custom::factory()->create();
        $other = Custom::factory()->create();
        $target->registeredOrder->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(CustomRegisteredOrderRelationManager::class, [
            'ownerRecord' => $target,
            'pageClass' => EditCustom::class,
        ])
            ->assertSuccessful()
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target->registeredOrder])
            ->assertCanNotSeeTableRecords([$other->registeredOrder]);
    }

    public function test_bank_profile_registered_orders_relation_manager_search_finds_records_by_extra_attribute(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view', 'registered_order.view']);

        $target = BankProfile::factory()->create();
        $other = BankProfile::factory()->create();
        $target->registeredOrder->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(BankProfileRegisteredOrdersRelationManager::class, [
            'ownerRecord' => $target,
            'pageClass' => EditBankProfile::class,
        ])
            ->assertSuccessful()
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target->registeredOrder])
            ->assertCanNotSeeTableRecords([$other->registeredOrder]);
    }

    public function test_payment_registered_order_relation_manager_search_finds_records_by_extra_attribute(): void
    {
        $this->actingAsUserWithPermissions(['payment.view', 'registered_order.view']);

        $target = RegisteredOrder::factory()->create();
        $other = RegisteredOrder::factory()->create();
        $payment = Payment::factory()->forTargetable($target)->create();
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(PaymentRegisteredOrderRelationManager::class, [
            'ownerRecord' => $payment,
            'pageClass' => EditPayment::class,
        ])
            ->assertSuccessful()
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_purchase_order_registered_orders_relation_manager_search_finds_records_by_extra_attribute(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view', 'registered_order.view']);

        $po = PurchaseOrder::factory()->create();
        $target = RegisteredOrder::factory()->create();
        $other = RegisteredOrder::factory()->create();
        $po->registeredOrders()->attach([$target->id, $other->id]);
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(PurchaseOrderRegisteredOrdersRelationManager::class, [
            'ownerRecord' => $po,
            'pageClass' => EditPurchaseOrder::class,
        ])
            ->assertSuccessful()
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_proforma_invoice_registered_order_relation_manager_search_finds_records_by_extra_attribute(): void
    {
        $this->actingAsUserWithPermissions(['proforma_invoice.view', 'registered_order.view']);

        $pi = ProformaInvoice::factory()->create();
        $target = RegisteredOrder::factory()->create();
        $other = RegisteredOrder::factory()->create();
        $pi->registeredOrders()->attach([$target->id, $other->id]);
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(ProformaInvoiceRegisteredOrderRelationManager::class, [
            'ownerRecord' => $pi,
            'pageClass' => EditProformaInvoice::class,
        ])
            ->assertSuccessful()
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_purchase_requests_relation_manager_shows_the_gated_empty_state_when_owner_has_none(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view', 'purchase_request.view']);

        $ro = RegisteredOrder::factory()->create();

        Livewire::test(PurchaseRequestsRelationManager::class, [
            'ownerRecord' => $ro,
            'pageClass' => EditRegisteredOrder::class,
        ])
            ->assertSee(__('resources/general/strings.empty_state.gated_heading'));
    }

    public function test_globally_searchable_attributes_include_extra_attribute_columns(): void
    {
        $this->assertContains('extraAttributes.key', RegisteredOrderResource::getGloballySearchableAttributes());
        $this->assertContains('extraAttributes.value', RegisteredOrderResource::getGloballySearchableAttributes());
    }

    public function test_seller_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);

        $sellerA = Company::factory()->seller()->create();
        $sellerB = Company::factory()->seller()->create();
        $withSellerA = RegisteredOrder::factory()->create(['seller_id' => $sellerA->id]);
        $withSellerB = RegisteredOrder::factory()->create(['seller_id' => $sellerB->id]);

        Livewire::test(ListRegisteredOrders::class)
            ->filterTable('seller_id', $sellerA->id)
            ->assertCanSeeTableRecords([$withSellerA])
            ->assertCanNotSeeTableRecords([$withSellerB]);
    }

    public function test_buyer_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);

        $buyerA = Company::factory()->buyer()->create();
        $buyerB = Company::factory()->buyer()->create();
        $withBuyerA = RegisteredOrder::factory()->create(['buyer_id' => $buyerA->id]);
        $withBuyerB = RegisteredOrder::factory()->create(['buyer_id' => $buyerB->id]);

        Livewire::test(ListRegisteredOrders::class)
            ->filterTable('buyer_id', $buyerA->id)
            ->assertCanSeeTableRecords([$withBuyerA])
            ->assertCanNotSeeTableRecords([$withBuyerB]);
    }

    public function test_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);

        $statusA = $this->roStatus('FilterStatusA');
        $statusB = $this->roStatus('FilterStatusB');
        $withA = RegisteredOrder::factory()->create(['status_id' => $statusA->id]);
        $withB = RegisteredOrder::factory()->create(['status_id' => $statusB->id]);

        Livewire::test(ListRegisteredOrders::class)
            ->filterTable('status_id', $statusA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_incoterms_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);

        $withFob = RegisteredOrder::factory()->create(['incoterms' => 'fob']);
        $withExw = RegisteredOrder::factory()->create(['incoterms' => 'exw']);

        Livewire::test(ListRegisteredOrders::class)
            ->filterTable('incoterms', 'fob')
            ->assertCanSeeTableRecords([$withFob])
            ->assertCanNotSeeTableRecords([$withExw]);
    }

    // Validity-lapsed badge (validity_date + purchase_orders_count)

    public function test_validity_date_shows_expired_badge_when_stale_and_unconverted(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);
        $record = RegisteredOrder::factory()->create(['validity_date' => now()->subDay()]);

        Livewire::test(ListRegisteredOrders::class)
            ->assertTableColumnFormattedStateSet('validity_date', __('resources/registeredOrder/strings.table.validity_expired'), $record);
    }

    public function test_validity_date_shows_plain_date_when_already_converted_to_a_registered_order(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);
        $record = RegisteredOrder::factory()->create(['validity_date' => now()->subDay()]);
        $po = PurchaseOrder::factory()->create();
        $record->purchaseOrders()->attach($po->id);
        $record = RegisteredOrder::withCount('purchaseOrders')->findOrFail($record->id);

        Livewire::test(ListRegisteredOrders::class)
            ->assertTableColumnFormattedStateSet('validity_date', adaptiveDate($record->validity_date), $record);
    }

    public function test_validity_date_shows_plain_date_when_still_valid(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);
        $record = RegisteredOrder::factory()->create(['validity_date' => now()->addDays(30)]);

        Livewire::test(ListRegisteredOrders::class)
            ->assertTableColumnFormattedStateSet('validity_date', adaptiveDate($record->validity_date), $record);
    }

    // Infolist — total_amount / total_quantity entries (accessor correctness itself is RegisteredOrderModelTest's job)

    public function test_total_amount_and_total_quantity_infolist_entries_render_the_precise_computed_total(): void
    {
        $record = RegisteredOrder::factory()->create();
        RegisteredOrderItem::factory()->create([
            'registered_order_id' => $record->id,
            'quantity' => 4,
            'unit_price' => 25,
            'shipping_cost' => 6,
            'extra_cost' => 4,
            'line_total' => 110,
        ]);
        $record->refresh();

        $amountEntry = RegisteredOrderResource::viewTotalAmount()->model($record);
        $quantityEntry = RegisteredOrderResource::viewTotalQuantity()->model($record);

        $this->assertSame(preciseNumber($record->total_amount), $amountEntry->formatState($record->total_amount));
        $this->assertSame(preciseNumber($record->total_quantity), $quantityEntry->formatState($record->total_quantity));
        $this->assertEquals(110, $record->total_amount);
    }

    // EAV custom attributes

    public function test_custom_attributes_map_does_not_render_a_blank_value_as_the_literal_word_null(): void
    {
        $record = RegisteredOrder::factory()->create();
        $record->syncCustomAttributes(['blank_field' => null, 'filled_field' => 'hello']);

        $map = $record->getCustomAttributesMap();

        $this->assertSame('', $map['blank_field']);
        $this->assertSame('hello', $map['filled_field']);
    }

    // Create — validation only (relationship-bound fields; §3d plain-scalar fillForm() quirk doesn't affect assertHasFormErrors)

    public function test_create_requires_seller_buyer_status_and_order_date(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.create', 'registered_order.view']);

        Livewire::test(CreateRegisteredOrder::class)
            ->fillForm([
                'seller_id' => null,
                'buyer_id' => null,
                'status_id' => null,
                'order_date' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['seller_id' => 'required', 'buyer_id' => 'required', 'status_id' => 'required', 'order_date' => 'required']);
    }

    public function test_seller_and_buyer_must_differ(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.create', 'registered_order.view']);
        $company = Company::factory()->create();

        Livewire::test(CreateRegisteredOrder::class)
            ->fillForm([
                'seller_id' => $company->id,
                'buyer_id' => $company->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['seller_id' => 'different']);
    }

    // Edit — relationship-bound field only; see testPattern.md §3d for why plain-scalar fields are skipped here

    public function test_edit_page_loads_existing_values_and_persists_a_status_update(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.edit', 'registered_order.view']);
        $statusA = $this->roStatus('EditStatusA');
        $statusB = $this->roStatus('EditStatusB');
        $record = RegisteredOrder::factory()->create([
            'status_id' => $statusA->id,
            'seller_id' => Company::factory()->seller()->create()->id,
            'buyer_id' => Company::factory()->buyer()->create()->id,
        ]);

        Livewire::test(EditRegisteredOrder::class, ['record' => $record->getRouteKey()])
            ->assertFormSet(['status_id' => $statusA->id])
            ->fillForm(['status_id' => $statusB->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($statusB->id, $record->fresh()->status_id);
    }

    // HasStatusWorkflow wiring — safe no-op today (no admin-configured stage_order exists for Registered Order Status), activates once configured

    public function test_list_and_edit_pages_expose_the_status_workflow_pipeline_header_action(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.create', 'registered_order.view', 'registered_order.edit']);
        $record = RegisteredOrder::factory()->create();

        Livewire::test(ListRegisteredOrders::class)
            ->assertActionExists('statusWorkflowPipeline');

        Livewire::test(EditRegisteredOrder::class, ['record' => $record->getRouteKey()])
            ->assertActionExists('statusWorkflowPipeline');
    }

    public function test_status_workflow_option_scoping_is_unrestricted_before_any_admin_configured_stage_order(): void
    {
        SmartCacheManager::invalidate('Status');

        $allIds = Status::where('english_type', RegisteredOrder::TYPE_REGISTERED_ORDER)->pluck('id')->sort()->values()->all();

        $method = new ReflectionMethod(RegisteredOrderResource::class, 'availableStatusWorkflowIds');
        $method->setAccessible(true);
        $ids = collect($method->invoke(null, 'status_id', null))->sort()->values()->all();

        $this->assertSame($allIds, $ids);

        SmartCacheManager::invalidate('Status');
    }

    public function test_create_page_status_mutation_is_a_no_op_before_any_admin_configured_stage_order(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.create', 'registered_order.view']);
        $status = $this->roStatus('NoOpSubmittedCheck');

        $page = new CreateRegisteredOrder;
        $method = new ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $data = $method->invoke($page, [
            'seller_id' => Company::factory()->create()->id,
            'buyer_id' => Company::factory()->create()->id,
            'status_id' => $status->id,
            'order_date' => now()->format('Y-m-d'),
        ]);

        $this->assertSame($status->id, $data['status_id']);
    }

    public function test_edit_page_status_mutation_is_a_no_op_before_any_admin_configured_stage_order(): void
    {
        $statusA = $this->roStatus('MutationNoOpA');
        $statusB = $this->roStatus('MutationNoOpB');
        $record = RegisteredOrder::factory()->create(['status_id' => $statusA->id]);

        $mutator = new class
        {
            use HandleStatusMutation;

            public function run(array $data, ?RegisteredOrder $record): array
            {
                return $this->mutateStatusData($data, $record);
            }
        };

        $data = $mutator->run(['status_id' => $statusB->id], $record);

        $this->assertSame($statusB->id, $data['status_id']);
    }

    public function test_configuring_stage_order_activates_gating_and_scopes_the_select_and_rejects_a_skipped_transition(): void
    {
        SmartCacheManager::invalidate('Status');

        try {
            $stageOne = $this->roStatus('ActivationStageOne', 1);
            $stageTwo = $this->roStatus('ActivationStageTwo', 2);
            $stageThree = $this->roStatus('ActivationStageThree', 3);
            $record = RegisteredOrder::factory()->create(['status_id' => $stageOne->id]);

            $method = new ReflectionMethod(RegisteredOrderResource::class, 'availableStatusWorkflowIds');
            $method->setAccessible(true);
            $ids = $method->invoke(null, 'status_id', $record->fresh());

            $this->assertContains($stageOne->id, $ids);
            $this->assertContains($stageTwo->id, $ids);
            $this->assertNotContains($stageThree->id, $ids);

            $mutator = new class
            {
                use HandleStatusMutation;

                public function run(array $data, ?RegisteredOrder $record): array
                {
                    return $this->mutateStatusData($data, $record);
                }
            };

            try {
                $mutator->run(['status_id' => $stageThree->id], $record->fresh());
                $this->fail('Expected a ValidationException for a skipped stage transition.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('status_id', $e->errors());
            }
        } finally {
            SmartCacheManager::invalidate('Status');
        }
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view', 'registered_order.delete', 'registered_order.restore']);
        $record = RegisteredOrder::factory()->create();

        Livewire::test(ListRegisteredOrders::class)
            ->callTableAction('delete', $record);

        $this->assertNull(RegisteredOrder::find($record->id));
        $this->assertTrue(RegisteredOrder::withTrashed()->find($record->id)->trashed());

        Livewire::test(ListRegisteredOrders::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(RegisteredOrder::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view', 'registered_order.delete']);
        $one = RegisteredOrder::factory()->create();
        $two = RegisteredOrder::factory()->create();

        Livewire::test(ListRegisteredOrders::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(RegisteredOrder::find($one->id));
        $this->assertNull(RegisteredOrder::find($two->id));
    }

    // Import / Export — grouped single-file bulk transfer

    private function mergedColumnMap(): array
    {
        $names = collect(RegisteredOrderImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeMergedImporter(array $parentData, array $itemRows = [], array $options = []): RegisteredOrderImporter
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $importer = new RegisteredOrderImporter(new Import, $this->mergedColumnMap(), array_merge([
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
            'ro_number' => '',
            'contract_no' => '',
            'official_registration_no' => '',
            'order_date' => '2026-01-15',
            'validity_date' => '',
            'expected_delivery_date' => '',
            'incoterms' => '',
            'currency_type' => '',
            'insurance_number' => '',
            'insurance_provider' => '',
            'insurance_date' => '',
            'notes' => '',
            'pr_numbers' => '',
            'invoice_nos' => '',
            'po_numbers' => '',
        ], $overrides);
    }

    public function test_import_creates_a_new_record_with_ro_number_and_contract_no_auto_generated_when_blank(): void
    {
        $seller = Company::factory()->create(['english_name' => 'Import Seller One']);
        $buyer = Company::factory()->create(['english_name' => 'Import Buyer One']);
        $currency = Currency::factory()->create(['english_name' => 'IMU1']);
        $status = $this->roStatus('ImportNewOrder');

        $importer = $this->invokeMergedImporter($this->baseParentRow([
            'seller_id' => $seller->english_name,
            'buyer_id' => $buyer->english_name,
            'status_id' => $status->english_name,
            'currency_id' => $currency->english_name,
        ]));

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertMatchesRegularExpression('/^RO-\d{6}(-\d+)?$/', $record->ro_number);
        $this->assertMatchesRegularExpression('/^CT-\d{6}(-\d+)?$/', $record->contract_no);
    }

    public function test_import_reupload_of_existing_ro_number_updates_in_place(): void
    {
        $seller = Company::factory()->create(['english_name' => 'Reupload Seller']);
        $buyer = Company::factory()->create(['english_name' => 'Reupload Buyer']);
        $currency = Currency::factory()->create(['english_name' => 'IMU2']);
        $status = $this->roStatus('ImportReuploadStatus');

        $row = $this->baseParentRow([
            'seller_id' => $seller->english_name,
            'buyer_id' => $buyer->english_name,
            'status_id' => $status->english_name,
            'currency_id' => $currency->english_name,
            'notes' => 'First upload',
        ]);

        $first = $this->invokeMergedImporter($row);
        $id = $first->getRecord()->id;

        $row['ro_number'] = $first->getRecord()->ro_number;
        $row['notes'] = 'Second upload';

        $second = $this->invokeMergedImporter($row);

        $this->assertSame($id, $second->getRecord()->id);
        $this->assertSame('Second upload', $second->getRecord()->notes);
    }

    public function test_import_still_rejects_an_unresolvable_seller_value(): void
    {
        $buyer = Company::factory()->create(['english_name' => 'Strict Buyer']);
        $currency = Currency::factory()->create(['english_name' => 'IMU3']);
        $status = $this->roStatus('ImportStrictSellerStatus');

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

    public function test_import_new_record_with_unresolvable_incoterms_saves_via_null_fallback_and_logs_a_note(): void
    {
        $seller = Company::factory()->create(['english_name' => 'Note Seller']);
        $buyer = Company::factory()->create(['english_name' => 'Note Buyer']);
        $currency = Currency::factory()->create(['english_name' => 'IMU4']);
        $status = $this->roStatus('ImportNoteIncotermsStatus');

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
        $currency = Currency::factory()->create(['english_name' => 'IMU5']);
        $status = $this->roStatus('ImportUnitStatus');
        $product = Product::factory()->create();

        $countBefore = RegisteredOrder::withTrashed()->count();

        try {
            $this->invokeMergedImporter($this->baseParentRow([
                'seller_id' => $seller->english_name,
                'buyer_id' => $buyer->english_name,
                'status_id' => $status->english_name,
                'currency_id' => $currency->english_name,
            ]), [
                ['product_id' => $product->code, 'quantity' => '2', 'unit' => '', 'unit_price' => '10', 'net_weight' => '', 'gross_weight' => '', 'entrance_fee' => '', 'shipping_cost' => '', 'extra_cost' => '', 'packing_details' => '', 'description' => ''],
            ]);

            $this->fail('Expected GroupRowFailedException was not thrown.');
        } catch (GroupRowFailedException) {
            $this->assertSame($countBefore, RegisteredOrder::withTrashed()->count(), 'A failed item row must roll back the parent record too — no orphan registered order should be persisted.');
        }
    }

    public function test_import_item_with_blank_quantity_or_unit_price_is_rejected_cleanly(): void
    {
        $seller = Company::factory()->create(['english_name' => 'QtyPrice Seller']);
        $buyer = Company::factory()->create(['english_name' => 'QtyPrice Buyer']);
        $currency = Currency::factory()->create(['english_name' => 'IMU6']);
        $status = $this->roStatus('ImportQtyPriceStatus');
        $product = Product::factory()->create();

        $countBefore = RegisteredOrder::withTrashed()->count();

        try {
            $this->invokeMergedImporter($this->baseParentRow([
                'seller_id' => $seller->english_name,
                'buyer_id' => $buyer->english_name,
                'status_id' => $status->english_name,
                'currency_id' => $currency->english_name,
            ]), [
                ['product_id' => $product->code, 'quantity' => '', 'unit' => 'kg', 'unit_price' => '10', 'net_weight' => '', 'gross_weight' => '', 'entrance_fee' => '', 'shipping_cost' => '', 'extra_cost' => '', 'packing_details' => '', 'description' => ''],
            ]);

            $this->fail('Expected GroupRowFailedException was not thrown for a blank quantity.');
        } catch (GroupRowFailedException) {
            $this->assertSame($countBefore, RegisteredOrder::withTrashed()->count(), 'A failed item row must roll back the parent record too — no orphan registered order should be persisted.');
        }

        try {
            $this->invokeMergedImporter($this->baseParentRow([
                'seller_id' => $seller->english_name,
                'buyer_id' => $buyer->english_name,
                'status_id' => $status->english_name,
                'currency_id' => $currency->english_name,
            ]), [
                ['product_id' => $product->code, 'quantity' => '2', 'unit' => 'kg', 'unit_price' => '', 'net_weight' => '', 'gross_weight' => '', 'entrance_fee' => '', 'shipping_cost' => '', 'extra_cost' => '', 'packing_details' => '', 'description' => ''],
            ]);

            $this->fail('Expected GroupRowFailedException was not thrown for a blank unit_price.');
        } catch (GroupRowFailedException) {
            $this->assertSame($countBefore, RegisteredOrder::withTrashed()->count());
        }
    }

    public function test_import_attaches_purchase_requests_from_the_pr_numbers_column(): void
    {
        $seller = Company::factory()->create(['english_name' => 'Pivot Seller']);
        $buyer = Company::factory()->create(['english_name' => 'Pivot Buyer']);
        $currency = Currency::factory()->create(['english_name' => 'IMU6']);
        $status = $this->roStatus('ImportPivotStatus');
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
        $currency = Currency::factory()->create(['english_name' => 'IMU7']);
        $status = $this->roStatus('ImportPivotNoteStatus');
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

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'ro_export_').'.csv';
        RegisteredOrderExporter::write($query, $path);

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
        $buyer = Company::factory()->buyer()->create(['english_name' => 'Export Buyer EN']);
        $currency = Currency::factory()->create(['english_name' => 'EXU1', 'is_active' => true]);
        $status = $this->roStatus('ExportStatusCheck');
        $product = Product::factory()->create();

        $record = RegisteredOrder::factory()->create([
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'currency_id' => $currency->id,
            'status_id' => $status->id,
            'order_date' => '2026-01-15',
            'notes' => null,
        ]);
        RegisteredOrderItem::factory()->create([
            'registered_order_id' => $record->id,
            'product_id' => $product->id,
            'quantity' => 3.5,
            'unit' => 'kg',
            'unit_price' => 12.25,
        ]);

        ['rows' => [$parentRow, $itemRow]] = $this->exportToRows(RegisteredOrder::query()->whereKey($record->id));

        $labels = RegisteredOrderImporter::columnLabels();

        $this->assertSame($record->ro_number, $parentRow[$labels['ro_number']]);
        $this->assertSame('Export Seller EN', $parentRow[$labels['seller_id']]);
        $this->assertSame('Export Buyer EN', $parentRow[$labels['buyer_id']]);
        $this->assertSame($status->english_name, $parentRow[$labels['status_id']]);
        $this->assertSame(jdate($record->order_date)->format('Y-m-d'), $parentRow[$labels['order_date']]);
        $this->assertSame('', $parentRow[$labels['product_id']]);

        $this->assertSame('', $itemRow[$labels['ro_number']]);
        $this->assertSame($product->english_name, $itemRow[$labels['product_id']]);
        $this->assertSame((string) $record->items->first()->quantity, $itemRow[$labels['quantity']]);
        $this->assertSame('kg', $itemRow[$labels['unit']]);
    }

    public function test_filled_example_file_exists_and_matches_the_grouped_import_shape(): void
    {
        $path = RegisteredOrderImporter::filledExamplePath();

        $this->assertNotNull($path);

        $fullPath = storage_path('app/'.$path);
        $this->assertFileExists($fullPath);

        $contents = file_get_contents($fullPath);

        $this->assertStringContainsString('product_id', $contents);
        $this->assertStringContainsString('pr_numbers', $contents);
    }

    public function test_filled_example_file_imports_cleanly_end_to_end(): void
    {
        Company::factory()->seller()->create(['english_name' => 'Persol']);
        Company::factory()->buyer()->create(['english_name' => 'Persore']);
        Company::factory()->seller()->create(['english_name' => 'Tejarat Oraman Pars']);
        Company::factory()->buyer()->create(['english_name' => 'Jamin Choob Iranian']);
        Currency::factory()->create(['english_name' => 'United States Dollar']);
        Currency::factory()->create(['english_name' => 'Euro']);
        Currency::factory()->create(['english_name' => 'Japanese Yen']);
        Product::factory()->create(['code' => '340263']);
        Product::factory()->create(['code' => '340189']);
        Product::factory()->create(['code' => '321313']);
        Product::factory()->create(['code' => '321292']);
        Product::factory()->create(['code' => '321246']);
        PurchaseRequest::factory()->create(['pr_number' => 'PR-260628']);

        $fullPath = storage_path('app/'.RegisteredOrderImporter::filledExamplePath());
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

        $groups = [];
        foreach ($records as $row) {
            if (filled($row['seller_id'] ?? null)) {
                $groups[] = ['parent' => $row, 'items' => []];
            } elseif ($groups) {
                $groups[array_key_last($groups)]['items'][] = $row;
            }
        }

        $this->assertCount(3, $groups);

        $imported = [];
        foreach ($groups as $group) {
            $imported[] = $this->invokeMergedImporter($group['parent'], $group['items']);
        }

        $this->assertSame('Persol', $imported[0]->getRecord()->sellerCompany->english_name);
        $this->assertCount(2, $imported[0]->getRecord()->items);
        $this->assertSame('Submitted', $imported[0]->getRecord()->status->english_name);
        $this->assertTrue($imported[0]->getRecord()->purchaseRequests->pluck('pr_number')->contains('PR-260628'));

        $this->assertSame('Draft', $imported[1]->getRecord()->status->english_name);
        $this->assertCount(1, $imported[1]->getRecord()->items);

        $this->assertNull($imported[2]->getRecord()->incoterms);
        $this->assertStringContainsString('NOT-A-REAL-INCOTERM', $imported[2]->getRecord()->notes);
        $this->assertCount(2, $imported[2]->getRecord()->items);
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Pinned count — a silent column drop during a future refactor (exactly what happened to BankProfile's exporter this session) must fail this test, not slip through unnoticed.
        $this->assertCount(30, RegisteredOrderImporter::getColumns());
        $this->assertCount(11, RegisteredOrderItemImporter::getColumns());
        $this->assertCount(30, RegisteredOrderImporter::columnLabels());
    }

    // Infolist — status history tab

    public function test_infolist_status_history_tab_renders_and_badge_matches_history_count(): void
    {
        app()->setLocale('en');
        $ro = RegisteredOrder::factory()->create();
        $ro->update(['status_id' => $this->roStatus('Submitted')->id]);
        $ro->load('statusHistories');

        $tab = $this->statusHistoryTab(RegisteredOrderResource::infolist(Schema::make()));

        $this->assertNotNull($tab);
        $this->assertSame($ro->statusHistories->count(), $this->statusHistoryTabBadge($tab, $ro));
    }

    // Global search contract

    public function test_global_search_title_uses_clipboard_emoji_prefix_and_ro_number(): void
    {
        $record = RegisteredOrder::factory()->create();

        $this->assertSame('📋 '.$record->ro_number, RegisteredOrderResource::getGlobalSearchResultTitle($record));
    }

    public function test_globally_searchable_attributes_include_ro_number(): void
    {
        $this->assertContains('ro_number', RegisteredOrderResource::getGloballySearchableAttributes());
    }
}
