<?php

namespace Tests\Feature\Filament;

use App\Filament\Actions\ImportAction;
use App\Filament\Resources\Operational\ShipmentResource\Exports\ShipmentExporter;
use App\Filament\Resources\Operational\ShipmentResource\Imports\ShipmentImporter;
use App\Filament\Resources\Operational\ShipmentResource\Pages\CreateShipment;
use App\Filament\Resources\Operational\ShipmentResource\Pages\EditShipment;
use App\Filament\Resources\Operational\ShipmentResource\Pages\ListShipments;
use App\Filament\Resources\Operational\ShipmentResource\RelationManagers\CustomsRelationManager;
use App\Filament\Resources\Operational\ShipmentResource\RelationManagers\RegisteredOrderRelationManager;
use App\Filament\Resources\ShipmentResource;
use App\Jobs\ExportRegisteredOrders;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\EntityAttribute;
use App\Models\Permission;
use App\Models\ProformaInvoice;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Services\StatusWorkflow;
use Filament\Actions\ActionGroup;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class ShipmentResourceTest extends TestCase
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

    public function test_infolist_status_history_tab_renders_and_badge_matches_history_count(): void
    {
        app()->setLocale('en');
        $shipment = Shipment::factory()->create();
        $shipment->update(['container_status_id' => Status::factory()->create()->id]);
        $shipment->load('statusHistories');

        $tab = $this->statusHistoryTab(ShipmentResource::infolist(Schema::make()));

        $this->assertNotNull($tab);
        $this->assertSame($shipment->statusHistories->count(), $this->statusHistoryTabBadge($tab, $shipment));
    }

    public function test_commercial_invoice_tab_label_includes_packing_list(): void
    {
        app()->setLocale('en');

        $this->assertSame(
            'Commercial Invoice & Packing List',
            ShipmentResource::getInvoiceFormTab()->getLabel()
        );
    }

    public function test_save_invoice_action_persists_the_live_form_state_to_eav(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'shipment.edit']);

        $ro = RegisteredOrder::factory()->create();
        $shipment = Shipment::factory()->create(['registered_order_id' => $ro->id]);
        $pi = ProformaInvoice::factory()->create();
        $pi->registeredOrders()->attach($ro->id);

        // ->set() writes directly into the Livewire 'data' property, bypassing
        // fillForm() (was a §3d plain-scalar drop workaround — resolved 2026-09-26;
        // ->set() kept as a valid pattern). _inv_pi_id is a plain Select, not
        // ->relationship()-bound. saveInvoice
        // lives in EditRecord::getFormActions(), a schema-embedded action (keyed
        // 'form-actions' on the page's 'content' schema, not a cached header
        // action), so it must be targeted via TestAction::schemaComponent().
        Livewire::test(EditShipment::class, ['record' => $shipment->getRouteKey()])
            ->set('data._inv_pi_id', $pi->id)
            ->set('data._inv_invoice_no', 'INV-TEST-001')
            ->callAction(TestAction::make('saveInvoice')->schemaComponent('form-actions', 'content'));

        $attr = EntityAttribute::where('entity_type', Shipment::class)
            ->where('entity_id', $shipment->id)
            ->where('key', 'commercial_invoice')
            ->first();

        $this->assertNotNull($attr);
        $this->assertSame($pi->id, $attr->value['proforma_invoice_id']);
        $this->assertSame('INV-TEST-001', $attr->value['invoice_no']);
    }

    public function test_saving_with_an_unsaved_commercial_invoice_persists_it_alongside_the_record(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'shipment.edit']);
        $ro = RegisteredOrder::factory()->create();
        $shipment = Shipment::factory()->create([
            'registered_order_id' => $ro->id,
            'contract_no' => null,
            'part' => 1,
            'bl_number' => 'OLD-BL',
            'status_id' => $this->shipmentStatus(Shipment::TYPE_SHIPMENT_STATUS, 'Processing')->id,
            'container_status_id' => $this->shipmentStatus(Shipment::TYPE_CONTAINER_STATUS, 'Stripped')->id,
            'operation_status_id' => $this->shipmentStatus(Shipment::TYPE_OPERATION_STATUS, 'In Exit Process')->id,
            'shipment_status_id' => $this->shipmentStatus(Shipment::TYPE_TRACKING_STATUS, 'In Transit')->id,
            'doc_status_id' => $this->shipmentStatus(Shipment::TYPE_DOC_STATUS, 'Preparing')->id,
        ]);
        $pi = ProformaInvoice::factory()->create();
        $pi->registeredOrders()->attach($ro->id);

        Livewire::test(EditShipment::class, ['record' => $shipment->getRouteKey()])
            ->set('data._inv_pi_id', $pi->id)
            ->set('data._inv_invoice_no', 'INV-AUTOSAVE-001')
            ->set('data.bl_number', 'NEW-BL')
            ->call('save')
            ->assertHasNoFormErrors();

        $attr = EntityAttribute::where('entity_type', Shipment::class)
            ->where('entity_id', $shipment->id)
            ->where('key', 'commercial_invoice')
            ->first();

        $this->assertNotNull($attr, 'beforeSave() must persist the dirty invoice even though saveInvoice was never clicked');
        $this->assertSame('INV-AUTOSAVE-001', $attr->value['invoice_no']);
        $this->assertSame('NEW-BL', $shipment->refresh()->bl_number);
    }

    public function test_saving_with_an_unsaved_commercial_invoice_does_not_wipe_an_unrelated_custom_attribute(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'shipment.edit']);
        $ro = RegisteredOrder::factory()->create();
        $shipment = Shipment::factory()->create([
            'registered_order_id' => $ro->id,
            'contract_no' => null,
            'part' => 1,
            'status_id' => $this->shipmentStatus(Shipment::TYPE_SHIPMENT_STATUS, 'Processing')->id,
            'container_status_id' => $this->shipmentStatus(Shipment::TYPE_CONTAINER_STATUS, 'Stripped')->id,
            'operation_status_id' => $this->shipmentStatus(Shipment::TYPE_OPERATION_STATUS, 'In Exit Process')->id,
            'shipment_status_id' => $this->shipmentStatus(Shipment::TYPE_TRACKING_STATUS, 'In Transit')->id,
            'doc_status_id' => $this->shipmentStatus(Shipment::TYPE_DOC_STATUS, 'Preparing')->id,
        ]);
        $pi = ProformaInvoice::factory()->create();
        $pi->registeredOrders()->attach($ro->id);

        $shipment->syncCustomAttributes(['my_custom_key' => 'my_custom_value']);

        Livewire::test(EditShipment::class, ['record' => $shipment->getRouteKey()])
            ->set('data._inv_pi_id', $pi->id)
            ->set('data._inv_invoice_no', 'INV-COEXIST-001')
            ->call('save')
            ->assertHasNoFormErrors();

        $invoiceAttr = EntityAttribute::where('entity_type', Shipment::class)
            ->where('entity_id', $shipment->id)
            ->where('key', 'commercial_invoice')
            ->first();

        $customAttr = EntityAttribute::where('entity_type', Shipment::class)
            ->where('entity_id', $shipment->id)
            ->where('key', 'my_custom_key')
            ->first();

        $this->assertNotNull($invoiceAttr, 'the extra-attributes sync must not delete the reserved commercial_invoice key');
        $this->assertSame('INV-COEXIST-001', $invoiceAttr->value['invoice_no']);
        $this->assertNotNull($customAttr, 'an unrelated custom attribute must survive a save alongside a dirty invoice');
        $this->assertSame('my_custom_value', $customAttr->value);
    }

    public function test_commercial_invoice_eav_round_trip_preserves_proforma_invoice_id(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $shipment = Shipment::factory()->create(['registered_order_id' => $ro->id]);
        $pi = ProformaInvoice::factory()->create();
        $pi->registeredOrders()->attach($ro->id);

        EntityAttribute::create([
            'entity_type' => Shipment::class,
            'entity_id' => $shipment->id,
            'key' => 'commercial_invoice',
            'value' => [
                'proforma_invoice_id' => $pi->id,
                'invoice_no' => $pi->invoice_no,
            ],
            'user_id' => null,
            'updated_by_id' => null,
        ]);

        $reread = EntityAttribute::where('entity_type', Shipment::class)
            ->where('entity_id', $shipment->id)
            ->where('key', 'commercial_invoice')
            ->first();

        $this->assertNotNull($reread);
        $this->assertSame($pi->id, $reread->value['proforma_invoice_id']);
    }

    public function test_commercial_invoice_pi_selector_options_query_finds_a_pi_linked_to_the_shipments_registered_order(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $shipment = Shipment::factory()->create(['registered_order_id' => $ro->id]);
        $linkedPi = ProformaInvoice::factory()->create();
        $linkedPi->registeredOrders()->attach($ro->id);

        $unrelatedPi = ProformaInvoice::factory()->create();

        $options = ProformaInvoice::whereHas(
            'registeredOrders',
            fn ($q) => $q->where('registered_orders.id', $shipment->registered_order_id)
        )->get();

        $this->assertTrue($options->contains('id', $linkedPi->id));
        $this->assertFalse($options->contains('id', $unrelatedPi->id));
    }

    public function test_edit_shipment_hydration_reads_back_the_saved_commercial_invoice_pi_id(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $shipment = Shipment::factory()->create(['registered_order_id' => $ro->id]);
        $pi = ProformaInvoice::factory()->create();
        $pi->registeredOrders()->attach($ro->id);

        EntityAttribute::create([
            'entity_type' => Shipment::class,
            'entity_id' => $shipment->id,
            'key' => 'commercial_invoice',
            'value' => ['proforma_invoice_id' => $pi->id],
            'user_id' => null,
            'updated_by_id' => null,
        ]);

        $page = new EditShipment;
        $reflection = new ReflectionMethod($page, 'mutateFormDataBeforeFill');
        $reflection->setAccessible(true);

        $recordProp = new ReflectionProperty($page, 'record');
        $recordProp->setAccessible(true);
        $recordProp->setValue($page, $shipment);

        $result = $reflection->invoke($page, $shipment->toArray());

        $this->assertSame($pi->id, $result['_inv_pi_id']);
    }

    public function test_hydrate_invoice_data_nulls_out_invoice_fields_when_no_saved_invoice_exists(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $shipment = Shipment::factory()->create(['registered_order_id' => $ro->id]);

        $page = new EditShipment;
        $reflection = new ReflectionMethod($page, 'hydrateInvoiceData');
        $reflection->setAccessible(true);

        $recordProp = new ReflectionProperty($page, 'record');
        $recordProp->setAccessible(true);
        $recordProp->setValue($page, $shipment);

        $result = $reflection->invoke($page, [
            '_inv_pi_id' => 999,
            '_inv_invoice_no' => 'stale-unsaved-value',
            '_inv_items' => [['description' => 'stale item']],
        ]);

        $this->assertNull($result['_inv_pi_id']);
        $this->assertNull($result['_inv_invoice_no']);
        $this->assertSame([], $result['_inv_items']);
        $this->assertSame(0, $result['_inv_grand_total']);
    }

    public function test_customs_relation_manager_shows_the_gated_empty_state_when_owner_has_none(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'custom.view']);

        $shipment = Shipment::factory()->create();

        Livewire::test(CustomsRelationManager::class, [
            'ownerRecord' => $shipment,
            'pageClass' => EditShipment::class,
        ])
            ->assertSee(__('resources/general/strings.empty_state.gated_heading'));
    }

    private function shipmentStatus(string $type, string $englishName): Status
    {
        return Status::where('english_type', $type)->where('english_name', $englishName)->firstOrFail();
    }

    public function test_create_mutation_leaves_status_columns_untouched_when_no_workflow_is_configured(): void
    {
        $this->assertNull(StatusWorkflow::initialFor(Shipment::TYPE_SHIPMENT_STATUS));
        $this->assertNull(StatusWorkflow::initialFor(Shipment::TYPE_CONTAINER_STATUS));

        $page = new CreateShipment;
        $method = new ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $data = $method->invoke($page, ['shipment_no' => 'SHP-TEST-001']);

        $this->assertArrayNotHasKey('status_id', $data);
        $this->assertArrayNotHasKey('container_status_id', $data);
        $this->assertArrayNotHasKey('operation_status_id', $data);
        $this->assertArrayNotHasKey('shipment_status_id', $data);
        $this->assertArrayNotHasKey('doc_status_id', $data);
    }

    private function editPageFor(Shipment $shipment): EditShipment
    {
        $page = new EditShipment;
        $recordProp = new ReflectionProperty($page, 'record');
        $recordProp->setAccessible(true);
        $recordProp->setValue($page, $shipment);

        return $page;
    }

    public function test_edit_mutation_allows_any_status_transition_on_a_non_primary_column_when_no_workflow_is_configured(): void
    {
        $this->assertNull(StatusWorkflow::initialFor(Shipment::TYPE_CONTAINER_STATUS));

        $from = $this->shipmentStatus(Shipment::TYPE_CONTAINER_STATUS, 'Stripped');
        $to = $this->shipmentStatus(Shipment::TYPE_CONTAINER_STATUS, 'Fully Discharged');
        $shipment = Shipment::factory()->create(['container_status_id' => $from->id]);

        $page = $this->editPageFor($shipment);
        $method = new ReflectionMethod($page, 'mutateFormDataBeforeSave');
        $method->setAccessible(true);

        $data = $method->invoke($page, ['container_status_id' => $to->id]);

        $this->assertSame($to->id, $data['container_status_id']);
    }

    public function test_edit_mutation_blocks_the_transition_when_a_stage_order_is_temporarily_configured(): void
    {
        $stageOne = $this->shipmentStatus(Shipment::TYPE_CONTAINER_STATUS, 'Stripped');
        $stageTwo = $this->shipmentStatus(Shipment::TYPE_CONTAINER_STATUS, 'Not Stripped');
        $stageThree = $this->shipmentStatus(Shipment::TYPE_CONTAINER_STATUS, '10% Not Discharged');
        $stageOne->update(['stage_order' => 1]);
        $stageTwo->update(['stage_order' => 2]);
        $stageThree->update(['stage_order' => 3]);

        $shipment = Shipment::factory()->create(['container_status_id' => $stageOne->id]);

        $page = $this->editPageFor($shipment);
        $method = new ReflectionMethod($page, 'mutateFormDataBeforeSave');
        $method->setAccessible(true);

        try {
            $method->invoke($page, ['container_status_id' => $stageThree->id]);
            $this->fail('Expected a ValidationException for the skipped-stage transition.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('container_status_id', $e->errors());
        }

        $method->invoke($page, ['container_status_id' => $stageTwo->id]);
        $this->assertTrue(true);
    }

    public function test_edit_form_shows_a_status_select_for_each_attachment(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'shipment.edit']);
        $shipment = Shipment::factory()->create();
        $uploaded = Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_UPLOADED);
        $attachment = Attachment::factory()->forAttachable($shipment)->create(['status_id' => $uploaded->id]);

        Livewire::test(EditShipment::class, ['record' => $shipment->getRouteKey()])
            ->assertSee($attachment->name ?: basename($attachment->path))
            ->assertSee($uploaded->getLocalizedNameAttribute());
    }

    public function test_attachments_infolist_entry_wires_the_supersede_and_revert_actions(): void
    {
        $entry = collect($this->repeatableItemComponents(ShipmentResource::viewAttachments()))
            ->first(fn ($component) => $component->getName() === 'status.name');

        $reflection = new ReflectionProperty($entry, 'suffixActions');
        $reflection->setAccessible(true);

        $names = collect($reflection->getValue($entry))->map(fn ($action) => $action->getName())->all();

        $this->assertSame(['supersedeAttachment', 'revertAttachment'], $names);
    }

    public function test_attachments_infolist_entry_splits_filename_and_status_three_to_two(): void
    {
        $entry = ShipmentResource::viewAttachments();

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

    // Permissions

    public function test_full_permissions_allow_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([
            'shipment.view',
            'shipment.create',
            'shipment.edit',
            'shipment.delete',
            'shipment.restore',
        ]);

        $record = Shipment::factory()->create();

        $this->assertTrue(ShipmentResource::canViewAny());
        $this->assertTrue(ShipmentResource::canCreate());
        $this->assertTrue(ShipmentResource::canEdit($record));
        $this->assertTrue(ShipmentResource::canDelete($record));
        $this->assertTrue(ShipmentResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = Shipment::factory()->create();

        $this->assertFalse(ShipmentResource::canViewAny());
        $this->assertFalse(ShipmentResource::canCreate());
        $this->assertFalse(ShipmentResource::canEdit($record));
        $this->assertFalse(ShipmentResource::canDelete($record));
        $this->assertFalse(ShipmentResource::canRestore($record));
    }

    // List — search / EAV

    public function test_list_page_renders_and_search_finds_by_shipment_no(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);

        $target = Shipment::factory()->create();
        $other = Shipment::factory()->create();

        Shipment::whereKey($target->id)->update(['shipment_no' => 'SHP-SEARCH-TARGET-'.$target->id]);
        Shipment::whereKey($other->id)->update(['shipment_no' => 'SHP-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();
        $term = 'TARGET-'.$target->id;

        Livewire::test(ListShipments::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_finds_records_by_extra_attribute_key_and_value(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);

        $target = Shipment::factory()->create();
        $other = Shipment::factory()->create();
        $target->syncCustomAttributes(['vessel_reference' => 'MV-ACME-9981']);

        Livewire::test(ListShipments::class)
            ->searchTable('vessel_reference')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('MV-ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_globally_searchable_attributes_include_extra_attribute_columns(): void
    {
        $attributes = ShipmentResource::getGloballySearchableAttributes();

        $this->assertContains('shipment_no', $attributes);
        $this->assertContains('bl_number', $attributes);
        $this->assertContains('extraAttributes.key', $attributes);
        $this->assertContains('extraAttributes.value', $attributes);
    }

    // Filters

    public function test_carrier_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);

        $carrierA = Company::factory()->create(['is_active' => true]);
        $carrierB = Company::factory()->create(['is_active' => true]);
        $withA = Shipment::factory()->create(['company_id' => $carrierA->id]);
        $withB = Shipment::factory()->create(['company_id' => $carrierB->id]);

        Livewire::test(ListShipments::class)
            ->filterTable('company_id', $carrierA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);

        $statusA = Status::factory()->create([
            'type' => Shipment::TYPE_SHIPMENT_STATUS, 'english_type' => Shipment::TYPE_SHIPMENT_STATUS,
            'name' => 'FilterShipmentStatusA', 'english_name' => 'FilterShipmentStatusA',
        ]);
        $statusB = Status::factory()->create([
            'type' => Shipment::TYPE_SHIPMENT_STATUS, 'english_type' => Shipment::TYPE_SHIPMENT_STATUS,
            'name' => 'FilterShipmentStatusB', 'english_name' => 'FilterShipmentStatusB',
        ]);
        $withA = Shipment::factory()->create(['status_id' => $statusA->id]);
        $withB = Shipment::factory()->create(['status_id' => $statusB->id]);

        Livewire::test(ListShipments::class)
            ->filterTable('status_id', $statusA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_tracking_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);

        $statusA = Status::factory()->create([
            'type' => Shipment::TYPE_TRACKING_STATUS, 'english_type' => Shipment::TYPE_TRACKING_STATUS,
            'name' => 'FilterTrackingStatusA', 'english_name' => 'FilterTrackingStatusA',
        ]);
        $statusB = Status::factory()->create([
            'type' => Shipment::TYPE_TRACKING_STATUS, 'english_type' => Shipment::TYPE_TRACKING_STATUS,
            'name' => 'FilterTrackingStatusB', 'english_name' => 'FilterTrackingStatusB',
        ]);
        $withA = Shipment::factory()->create(['shipment_status_id' => $statusA->id]);
        $withB = Shipment::factory()->create(['shipment_status_id' => $statusB->id]);

        Livewire::test(ListShipments::class)
            ->filterTable('shipment_status_id', $statusA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_container_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);

        $statusA = Status::factory()->create([
            'type' => Shipment::TYPE_CONTAINER_STATUS, 'english_type' => Shipment::TYPE_CONTAINER_STATUS,
            'name' => 'FilterContainerStatusA', 'english_name' => 'FilterContainerStatusA',
        ]);
        $statusB = Status::factory()->create([
            'type' => Shipment::TYPE_CONTAINER_STATUS, 'english_type' => Shipment::TYPE_CONTAINER_STATUS,
            'name' => 'FilterContainerStatusB', 'english_name' => 'FilterContainerStatusB',
        ]);
        $withA = Shipment::factory()->create(['container_status_id' => $statusA->id]);
        $withB = Shipment::factory()->create(['container_status_id' => $statusB->id]);

        Livewire::test(ListShipments::class)
            ->filterTable('container_status_id', $statusA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_doc_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);

        $statusA = Status::factory()->create([
            'type' => Shipment::TYPE_DOC_STATUS, 'english_type' => Shipment::TYPE_DOC_STATUS,
            'name' => 'FilterDocStatusA', 'english_name' => 'FilterDocStatusA',
        ]);
        $statusB = Status::factory()->create([
            'type' => Shipment::TYPE_DOC_STATUS, 'english_type' => Shipment::TYPE_DOC_STATUS,
            'name' => 'FilterDocStatusB', 'english_name' => 'FilterDocStatusB',
        ]);
        $withA = Shipment::factory()->create(['doc_status_id' => $statusA->id]);
        $withB = Shipment::factory()->create(['doc_status_id' => $statusB->id]);

        Livewire::test(ListShipments::class)
            ->filterTable('doc_status_id', $statusA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_operation_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);

        $statusA = Status::factory()->create([
            'type' => Shipment::TYPE_OPERATION_STATUS, 'english_type' => Shipment::TYPE_OPERATION_STATUS,
            'name' => 'FilterOperationStatusA', 'english_name' => 'FilterOperationStatusA',
        ]);
        $statusB = Status::factory()->create([
            'type' => Shipment::TYPE_OPERATION_STATUS, 'english_type' => Shipment::TYPE_OPERATION_STATUS,
            'name' => 'FilterOperationStatusB', 'english_name' => 'FilterOperationStatusB',
        ]);
        $withA = Shipment::factory()->create(['operation_status_id' => $statusA->id]);
        $withB = Shipment::factory()->create(['operation_status_id' => $statusB->id]);

        Livewire::test(ListShipments::class)
            ->filterTable('operation_status_id', $statusA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_eta_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);

        $withinRange = Shipment::factory()->create(['eta' => '2026-01-15']);
        $outsideRange = Shipment::factory()->create(['eta' => '2026-06-15']);

        Livewire::test(ListShipments::class)
            ->filterTable('eta', ['eta_from' => '2026-01-01', 'eta_until' => '2026-01-31'])
            ->assertCanSeeTableRecords([$withinRange])
            ->assertCanNotSeeTableRecords([$outsideRange]);
    }

    public function test_creation_date_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);

        $withinRange = Shipment::factory()->create();
        $outsideRange = Shipment::factory()->create();
        Shipment::whereKey($withinRange->id)->update(['created_at' => '2026-01-10']);
        Shipment::whereKey($outsideRange->id)->update(['created_at' => '2026-06-10']);

        Livewire::test(ListShipments::class)
            ->filterTable('created_at', ['created_from' => '2026-01-01', 'created_until' => '2026-01-31'])
            ->assertCanSeeTableRecords([$withinRange])
            ->assertCanNotSeeTableRecords([$outsideRange]);
    }

    public function test_trashed_filter_narrows_the_table_to_soft_deleted_records(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'shipment.delete']);

        $active = Shipment::factory()->create();
        $deleted = Shipment::factory()->create();
        $deleted->delete();

        Livewire::test(ListShipments::class)
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$deleted])
            ->assertCanNotSeeTableRecords([$active]);
    }

    // UX — ETA overdue/due-soon badge (Idea 1)

    public function test_eta_status_badge_shows_overdue_when_exit_date_is_null_and_eta_is_past(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['shipment.view']);
        $record = Shipment::factory()->create(['eta' => now()->subDays(2)->toDateString(), 'exit_date' => null]);

        Livewire::test(ListShipments::class)
            ->assertTableColumnStateSet('eta_status', 'overdue', $record)
            ->assertTableColumnFormattedStateSet('eta_status', '🔴 '.__('resources/shipment/strings.table.eta_status_overdue'), $record);
    }

    public function test_eta_status_badge_shows_due_soon_within_the_threshold(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['shipment.view']);
        $record = Shipment::factory()->create(['eta' => now()->addDays(2)->toDateString(), 'exit_date' => null]);

        Livewire::test(ListShipments::class)
            ->assertTableColumnStateSet('eta_status', 'due_soon', $record)
            ->assertTableColumnFormattedStateSet('eta_status', '🟡 '.__('resources/shipment/strings.table.eta_status_due_soon'), $record);
    }

    public function test_eta_status_badge_shows_on_track_beyond_the_threshold_or_once_exited(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['shipment.view']);
        $farFuture = Shipment::factory()->create(['eta' => now()->addDays(10)->toDateString(), 'exit_date' => null]);
        $alreadyExited = Shipment::factory()->create(['eta' => now()->subDays(5)->toDateString(), 'exit_date' => now()->toDateString()]);

        Livewire::test(ListShipments::class)
            ->assertTableColumnStateSet('eta_status', 'on_track', $farFuture)
            ->assertTableColumnStateSet('eta_status', 'on_track', $alreadyExited);
    }

    public function test_eta_status_badge_is_blank_when_eta_is_not_set(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);
        $record = Shipment::factory()->create(['eta' => null]);

        Livewire::test(ListShipments::class)
            ->assertTableColumnStateSet('eta_status', null, $record);
    }

    public function test_eta_status_badge_is_not_overdue_when_eta_is_exactly_today(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);
        $record = Shipment::factory()->create(['eta' => now()->toDateString(), 'exit_date' => null]);

        Livewire::test(ListShipments::class)
            ->assertTableColumnStateSet('eta_status', 'due_soon', $record);
    }

    public function test_overdue_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);
        $overdue = Shipment::factory()->create(['eta' => now()->subDays(1)->toDateString(), 'exit_date' => null]);
        $onTrack = Shipment::factory()->create(['eta' => now()->addDays(20)->toDateString(), 'exit_date' => null]);

        Livewire::test(ListShipments::class)
            ->filterTable('overdue', true)
            ->assertCanSeeTableRecords([$overdue])
            ->assertCanNotSeeTableRecords([$onTrack]);
    }

    public function test_overdue_filter_excludes_a_shipment_whose_eta_is_exactly_today(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);
        $dueToday = Shipment::factory()->create(['eta' => now()->toDateString(), 'exit_date' => null]);

        Livewire::test(ListShipments::class)
            ->filterTable('overdue', true)
            ->assertCanNotSeeTableRecords([$dueToday]);
    }

    // UX — document checklist completion badge (Idea 2)

    public function test_docs_progress_badge_is_danger_when_nothing_is_received(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);
        $record = Shipment::factory()->create(['docs' => [
            ['name' => 'ci', 'received' => false],
            ['name' => 'bl', 'received' => false],
        ]]);

        Livewire::test(ListShipments::class)
            ->assertTableColumnStateSet('docs_progress', '0/2', $record);
    }

    public function test_docs_progress_badge_reflects_a_partial_ratio(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);
        $record = Shipment::factory()->create(['docs' => [
            ['name' => 'ci', 'received' => true],
            ['name' => 'bl', 'received' => false],
        ]]);

        Livewire::test(ListShipments::class)
            ->assertTableColumnStateSet('docs_progress', '1/2', $record);
    }

    public function test_docs_progress_badge_shows_complete_when_every_document_is_received(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);
        $record = Shipment::factory()->create(['docs' => [
            ['name' => 'ci', 'received' => true],
            ['name' => 'bl', 'received' => true],
        ]]);

        Livewire::test(ListShipments::class)
            ->assertTableColumnStateSet('docs_progress', '2/2', $record);
    }

    public function test_docs_progress_badge_is_blank_when_document_tracking_is_disabled(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);
        $record = Shipment::factory()->create(['docs' => [
            ['name' => 'track', 'received' => false],
            ['name' => 'ci', 'received' => false],
        ]]);

        Livewire::test(ListShipments::class)
            ->assertTableColumnStateSet('docs_progress', null, $record);
    }

    public function test_docs_progress_badge_is_blank_when_the_checklist_has_no_real_document_rows(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);
        $record = Shipment::factory()->create(['docs' => [
            ['name' => 'track', 'received' => true],
        ]]);

        Livewire::test(ListShipments::class)
            ->assertTableColumnStateSet('docs_progress', null, $record);
    }

    public function test_the_two_status_workflow_progress_columns_have_distinct_labels(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['shipment.view']);

        $columns = Livewire::test(ListShipments::class)->instance()->getTable()->getColumns();

        $statusProgress = collect($columns)->first(fn ($c) => $c->getName() === 'status_id_progress');
        $trackingProgress = collect($columns)->first(fn ($c) => $c->getName() === 'shipment_status_id_progress');

        $this->assertNotNull($statusProgress);
        $this->assertNotNull($trackingProgress);
        $this->assertNotSame($statusProgress->getLabel(), $trackingProgress->getLabel());
        $this->assertSame('Status Progress', $statusProgress->getLabel());
        $this->assertSame('Tracking Progress', $trackingProgress->getLabel());
    }

    // UX — container/operation/doc status columns (Idea 3)

    public function test_container_operation_and_doc_status_columns_render_their_localized_names(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['shipment.view']);

        $container = $this->shipmentStatus(Shipment::TYPE_CONTAINER_STATUS, 'Stripped');
        $operation = $this->shipmentStatus(Shipment::TYPE_OPERATION_STATUS, 'In Exit Process');
        $doc = $this->shipmentStatus(Shipment::TYPE_DOC_STATUS, 'Preparing');
        $record = Shipment::factory()->create([
            'container_status_id' => $container->id,
            'operation_status_id' => $operation->id,
            'doc_status_id' => $doc->id,
        ]);

        Livewire::test(ListShipments::class)
            ->assertTableColumnFormattedStateSet('containerStatus.name', $container->getLocalizedNameAttribute(), $record)
            ->assertTableColumnFormattedStateSet('operationStatus.name', $operation->getLocalizedNameAttribute(), $record)
            ->assertTableColumnFormattedStateSet('docStatus.name', $doc->getLocalizedNameAttribute(), $record);
    }

    public function test_container_operation_and_doc_status_columns_are_toggleable_hidden_by_default(): void
    {
        $this->assertTrue(ShipmentResource::showContainerStatus()->isToggledHiddenByDefault());
        $this->assertTrue(ShipmentResource::showOperationStatus()->isToggledHiddenByDefault());
        $this->assertTrue(ShipmentResource::showDocStatus()->isToggledHiddenByDefault());
    }

    // UX — duplicate B/L number soft warning (Idea 4)

    public function test_bl_number_warns_on_blur_when_another_shipment_already_uses_it(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['shipment.create', 'shipment.view']);
        Shipment::factory()->create(['bl_number' => 'BL-DUP-001']);

        Livewire::test(CreateShipment::class)
            ->fillForm(['bl_number' => 'BL-DUP-001'])
            ->assertSee(__('resources/shipment/strings.form.bl_number_duplicate_warning'));
    }

    public function test_bl_number_stays_silent_when_blank_or_unique(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['shipment.create', 'shipment.view']);
        Shipment::factory()->create(['bl_number' => 'BL-DUP-002']);

        Livewire::test(CreateShipment::class)
            ->fillForm(['bl_number' => 'BL-UNIQUE-999'])
            ->assertDontSee(__('resources/shipment/strings.form.bl_number_duplicate_warning'));
    }

    public function test_bl_number_warning_excludes_the_current_record_on_edit(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['shipment.edit', 'shipment.view']);
        $shipment = Shipment::factory()->create(['bl_number' => 'BL-DUP-003']);

        Livewire::test(EditShipment::class, ['record' => $shipment->getRouteKey()])
            ->fillForm(['bl_number' => 'BL-DUP-003'])
            ->assertDontSee(__('resources/shipment/strings.form.bl_number_duplicate_warning'));
    }

    // Create — validation

    public function test_create_requires_registered_order_carrier_and_part(): void
    {
        $this->actingAsUserWithPermissions(['shipment.create', 'shipment.view']);

        Livewire::test(CreateShipment::class)
            ->fillForm([
                'registered_order_id' => null,
                'company_id' => null,
                'part' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'registered_order_id' => 'required',
                'company_id' => 'required',
                'part' => 'required',
            ]);
    }

    public function test_create_rejects_a_nonexistent_registered_order_id_with_translated_message(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['shipment.create', 'shipment.view']);

        $test = Livewire::test(CreateShipment::class)
            ->fillForm(['registered_order_id' => 999999])
            ->call('create');

        $this->assertSame(
            [__('resources/shipment/strings.form.validation.in')],
            $test->errors()->get('data.registered_order_id')
        );
    }

    public function test_shipment_no_is_auto_generated_on_create(): void
    {
        $this->actingAsUserWithPermissions(['shipment.create', 'shipment.view']);
        $ro = RegisteredOrder::factory()->create();
        $carrier = Company::factory()->create(['is_active' => true, 'english_name' => 'Import Target Carrier A']);

        Livewire::test(CreateShipment::class)
            ->fillForm([
                'registered_order_id' => $ro->id,
                'company_id' => $carrier->id,
                'part' => 1,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $record = Shipment::latest('id')->first();

        $this->assertMatchesRegularExpression('/^S-\d{6}(-\d+)?$/', $record->shipment_no);
    }

    // Edit

    public function test_edit_page_loads_existing_values_and_persists_a_bl_number_update(): void
    {
        $this->actingAsUserWithPermissions(['shipment.edit', 'shipment.view']);
        $shipment = Shipment::factory()->create([
            'company_id' => Company::factory()->create(['is_active' => true])->id,
            'bl_number' => 'OLD-BL-001',
            'part' => '1',
            'contract_no' => null,
            'container_no' => '5',
            'container_type' => '20ft Standard',
            'status_id' => $this->shipmentStatus(Shipment::TYPE_SHIPMENT_STATUS, 'Processing')->id,
            'container_status_id' => $this->shipmentStatus(Shipment::TYPE_CONTAINER_STATUS, 'Stripped')->id,
            'operation_status_id' => $this->shipmentStatus(Shipment::TYPE_OPERATION_STATUS, 'In Exit Process')->id,
            'shipment_status_id' => $this->shipmentStatus(Shipment::TYPE_TRACKING_STATUS, 'In Transit')->id,
            'doc_status_id' => $this->shipmentStatus(Shipment::TYPE_DOC_STATUS, 'Preparing')->id,
        ]);

        Livewire::test(EditShipment::class, ['record' => $shipment->getRouteKey()])
            ->assertFormSet(['bl_number' => 'OLD-BL-001'])
            ->fillForm(['bl_number' => 'NEW-BL-002'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('NEW-BL-002', $shipment->fresh()->bl_number);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'shipment.delete', 'shipment.restore']);
        $record = Shipment::factory()->create();

        Livewire::test(ListShipments::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Shipment::find($record->id));
        $this->assertTrue(Shipment::withTrashed()->find($record->id)->trashed());

        Livewire::test(ListShipments::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(Shipment::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'shipment.delete']);
        $one = Shipment::factory()->create();
        $two = Shipment::factory()->create();

        Livewire::test(ListShipments::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(Shipment::find($one->id));
        $this->assertNull(Shipment::find($two->id));
    }

    // Bulk-action ordering convention

    public function test_bulk_actions_toolbar_orders_export_before_delete_and_restore(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'shipment.delete', 'shipment.restore']);

        Livewire::test(ListShipments::class)
            ->assertTableBulkActionsExistInOrder(['exportShipments', 'delete', 'restore']);
    }

    // Global search contract

    public function test_global_search_title_uses_ship_emoji_prefix_and_shipment_no(): void
    {
        $record = Shipment::factory()->create();

        $this->assertSame('🚢 '.$record->shipment_no, ShipmentResource::getGlobalSearchResultTitle($record));
    }

    public function test_global_search_result_details_returns_bl_number_carrier_and_status(): void
    {
        app()->setLocale('en');
        $carrier = Company::factory()->create(['is_active' => true, 'english_name' => 'Global Search Carrier']);
        $status = Status::factory()->create([
            'type' => Shipment::TYPE_SHIPMENT_STATUS, 'english_type' => Shipment::TYPE_SHIPMENT_STATUS,
            'name' => 'GlobalSearchStatus', 'english_name' => 'GlobalSearchStatus',
        ]);
        $record = Shipment::factory()->create([
            'company_id' => $carrier->id,
            'status_id' => $status->id,
            'bl_number' => 'BL-GLOBAL-001',
        ]);
        $record->load(['carrier', 'status']);

        $details = ShipmentResource::getGlobalSearchResultDetails($record);

        $this->assertSame('BL-GLOBAL-001', $details[__('resources/shipment/strings.form.bl_number')]);
        $this->assertSame('Global Search Carrier', $details[__('resources/shipment/strings.form.carrier')]);
        $this->assertSame('GlobalSearchStatus', $details[__('resources/shipment/strings.form.status')]);
    }

    // Import / Export — flat single-row bulk transfer

    private function importColumnMap(): array
    {
        $names = collect(ShipmentImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeImporter(array $data, array $options = []): ShipmentImporter
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $importer = new ShipmentImporter(new Import, $this->importColumnMap(), array_merge([
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
            'shipment_no' => '',
            'registered_order_id' => '',
            'company_id' => '',
            'contract_no' => '',
            'part' => '',
            'status_id' => '',
            'container_status_id' => '',
            'operation_status_id' => '',
            'shipment_status_id' => '',
            'doc_status_id' => '',
            'warehouse_date' => '',
            'exit_date' => '',
            'eta' => '',
            'etd' => '',
            'remittance_amount' => '',
            'customs_quantity' => '',
            'shipped_quantity' => '',
            'bl_number' => '',
            'booking_no' => '',
            'container_no' => '',
            'container_type' => '',
            'notes' => '',
        ], $overrides);
    }

    public function test_import_creates_a_new_record_with_shipment_no_auto_generated_when_blank(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $carrier = Company::factory()->create(['is_active' => true, 'english_name' => 'Import Target Carrier B']);

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
            'company_id' => $carrier->english_name,
        ]));

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertMatchesRegularExpression('/^S-\d{6}(-\d+)?$/', $record->shipment_no);
        $this->assertSame($ro->id, $record->registered_order_id);
        $this->assertSame($carrier->id, $record->company_id);
    }

    public function test_import_reupload_of_existing_shipment_no_updates_in_place(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $carrier = Company::factory()->create(['is_active' => true, 'english_name' => 'Import Target Carrier C']);

        $row = $this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
            'company_id' => $carrier->english_name,
            'notes' => 'First upload',
        ]);

        $first = $this->invokeImporter($row);
        $id = $first->getRecord()->id;

        $row['shipment_no'] = $first->getRecord()->shipment_no;
        $row['notes'] = 'Second upload';

        $second = $this->invokeImporter($row);

        $this->assertSame($id, $second->getRecord()->id);
        $this->assertSame('Second upload', $second->getRecord()->notes);
    }

    public function test_import_rejects_an_unresolvable_registered_order_value(): void
    {
        $carrier = Company::factory()->create(['is_active' => true]);

        try {
            $this->invokeImporter($this->baseImportRow([
                'registered_order_id' => 'RO-DOES-NOT-EXIST',
                'company_id' => $carrier->english_name,
            ]));

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('RO-DOES-NOT-EXIST', collect($exception->errors())->flatten()->implode(' '));
        }
    }

    public function test_import_rejects_a_blank_registered_order_on_a_new_record_cleanly(): void
    {
        app()->setLocale('en');
        $carrier = Company::factory()->create(['is_active' => true]);
        $before = Shipment::count();

        try {
            $this->invokeImporter($this->baseImportRow([
                'company_id' => $carrier->english_name,
            ]));

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertSame(__('resources/general/strings.import.required_for_new_record', [
                'label' => __('resources/shipment/strings.form.registered_order'),
            ]), $exception->getMessage());
        }

        $this->assertSame($before, Shipment::count());
    }

    public function test_import_rejects_a_blank_carrier_on_a_new_record_cleanly(): void
    {
        app()->setLocale('en');
        $ro = RegisteredOrder::factory()->create();
        $before = Shipment::count();

        try {
            $this->invokeImporter($this->baseImportRow([
                'registered_order_id' => $ro->ro_number,
            ]));

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertSame(__('resources/general/strings.import.required_for_new_record', [
                'label' => __('resources/shipment/strings.form.carrier'),
            ]), $exception->getMessage());
        }

        $this->assertSame($before, Shipment::count());
    }

    public function test_import_reupload_with_a_blank_registered_order_and_carrier_preserves_the_existing_values(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $carrier = Company::factory()->create(['is_active' => true, 'english_name' => 'Preserve Carrier A']);

        $first = $this->invokeImporter($this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
            'company_id' => $carrier->english_name,
        ]));
        $record = $first->getRecord();

        $row = $this->baseImportRow([
            'shipment_no' => $record->shipment_no,
            'notes' => 'Second upload, registered order and carrier left blank',
        ]);

        $second = $this->invokeImporter($row);

        $this->assertSame($record->id, $second->getRecord()->id);
        $this->assertSame($ro->id, $second->getRecord()->registered_order_id);
        $this->assertSame($carrier->id, $second->getRecord()->company_id);
    }

    public function test_import_rejects_an_unresolvable_carrier_value(): void
    {
        $ro = RegisteredOrder::factory()->create();

        try {
            $this->invokeImporter($this->baseImportRow([
                'registered_order_id' => $ro->ro_number,
                'company_id' => 'NONEXISTENT CARRIER XYZ',
            ]));

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('NONEXISTENT CARRIER XYZ', collect($exception->errors())->flatten()->implode(' '));
        }
    }

    public function test_import_blank_status_id_falls_back_to_processing(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $carrier = Company::factory()->create(['is_active' => true, 'english_name' => 'Import Target Carrier D']);
        $processing = $this->shipmentStatus(Shipment::TYPE_SHIPMENT_STATUS, 'Processing');

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
            'company_id' => $carrier->english_name,
        ]));

        $this->assertSame($processing->id, $importer->getRecord()->status_id);
    }

    public function test_import_blank_optional_status_columns_stay_null(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $carrier = Company::factory()->create(['is_active' => true, 'english_name' => 'Import Target Carrier E']);

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
            'company_id' => $carrier->english_name,
        ]));

        $record = $importer->getRecord();

        $this->assertNull($record->container_status_id);
        $this->assertNull($record->operation_status_id);
        $this->assertNull($record->shipment_status_id);
        $this->assertNull($record->doc_status_id);
    }

    public function test_import_new_record_with_unresolvable_container_status_saves_via_null_fallback_and_logs_a_note(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $carrier = Company::factory()->create(['is_active' => true, 'english_name' => 'Import Target Carrier F']);

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
            'company_id' => $carrier->english_name,
            'container_status_id' => 'Nonexistent Container Status',
        ]));

        $record = $importer->getRecord();

        $this->assertNull($record->container_status_id);
        $this->assertStringContainsString('Nonexistent Container Status', $record->notes);
    }

    public function test_import_blank_contract_no_falls_back_to_the_registered_orders_contract_no(): void
    {
        $ro = RegisteredOrder::factory()->create();
        RegisteredOrder::whereKey($ro->id)->update(['contract_no' => 'CT-FALLBACK-001']);
        $ro->refresh();
        $carrier = Company::factory()->create(['is_active' => true, 'english_name' => 'Import Target Carrier G']);

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
            'company_id' => $carrier->english_name,
        ]));

        $this->assertSame('CT-FALLBACK-001', $importer->getRecord()->contract_no);
    }

    public function test_import_container_type_matches_a_real_option_value(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $carrier = Company::factory()->create(['is_active' => true, 'english_name' => 'Import Target Carrier H']);

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
            'company_id' => $carrier->english_name,
            'container_type' => '40ft Standard',
        ]));

        $this->assertSame('40ft Standard', $importer->getRecord()->container_type);
    }

    public function test_filled_example_file_exists_and_matches_the_import_shape(): void
    {
        $path = ShipmentImporter::filledExamplePath();

        $this->assertNotNull($path);

        $fullPath = storage_path('app/'.$path);
        $this->assertFileExists($fullPath);

        $contents = file_get_contents($fullPath);

        $this->assertStringContainsString('registered_order_id', $contents);
        $this->assertStringContainsString('shipment_no', $contents);
    }

    public function test_auto_generated_empty_example_has_a_real_header_for_every_import_column(): void
    {
        foreach (ShipmentImporter::getColumns() as $column) {
            $this->assertNotSame('', trim($column->getExampleHeader()), "Column [{$column->getName()}] has a blank example header.");
        }
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Pinned counts — a silent column drop during a future refactor must fail this test, not slip through unnoticed.
        $this->assertCount(22, ShipmentImporter::getColumns());
        $this->assertCount(22, ShipmentImporter::columnLabels());
        $this->assertCount(34, ShipmentExporter::columnLabels());
    }

    public function test_filled_example_file_imports_cleanly_end_to_end(): void
    {
        foreach (['RO-260714', 'RO-260718', 'RO-260718-1'] as $roNumber) {
            $ro = RegisteredOrder::factory()->create();
            RegisteredOrder::whereKey($ro->id)->update(['ro_number' => $roNumber]);
        }
        Company::factory()->create(['english_name' => 'Persol', 'is_active' => true]);
        Company::factory()->create(['english_name' => 'Tejarat Oraman Pars', 'is_active' => true]);

        $fullPath = storage_path('app/'.ShipmentImporter::filledExamplePath());
        $csv = \League\Csv\Reader::createFromPath($fullPath, 'r');
        $csv->setHeaderOffset(0);

        $rows = [];
        foreach ($csv->getRecords() as $row) {
            $mapped = [];
            foreach ($row as $header => $value) {
                if (preg_match('/\(([a-z_]+)\)$/', $header, $matches)) {
                    $mapped[$matches[1]] = $value;
                }
            }
            $rows[] = $mapped;
        }

        $this->assertCount(3, $rows);

        $first = $this->invokeImporter($rows[0]);
        $second = $this->invokeImporter($rows[1]);

        $this->assertSame('Persol', $first->getRecord()->carrier->english_name);
        $this->assertSame('40ft Standard', $first->getRecord()->container_type);
        $this->assertSame('CT-CUSTOM-01', $second->getRecord()->contract_no);

        try {
            $this->invokeImporter($rows[2]);
            $this->fail('Expected ValidationException for the unresolvable carrier was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('NONEXISTENT CARRIER XYZ', collect($exception->errors())->flatten()->implode(' '));
        }
    }

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'shipment_export_').'.csv';
        ShipmentExporter::write($query, $path);

        $csv = (string) file_get_contents($path);
        unlink($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", trim(ltrim($csv, "\xEF\xBB\xBF"))))));
        $header = str_getcsv($lines[0]);
        $rows = array_map(fn (string $line) => array_combine($header, str_getcsv($line)), array_slice($lines, 1));

        return ['header' => $header, 'rows' => $rows];
    }

    public function test_exporter_write_emits_one_row_per_record(): void
    {
        app()->setLocale('en');
        $ro = RegisteredOrder::factory()->create();
        RegisteredOrder::whereKey($ro->id)->update(['ro_number' => 'RO-EXPORT-001']);
        $ro->refresh();
        $carrier = Company::factory()->create(['english_name' => 'Export Carrier EN', 'is_active' => true]);
        $status = Status::factory()->create([
            'type' => Shipment::TYPE_SHIPMENT_STATUS, 'english_type' => Shipment::TYPE_SHIPMENT_STATUS,
            'name' => 'ExportStatusCheck', 'english_name' => 'ExportStatusCheck',
        ]);

        $record = Shipment::factory()->create([
            'registered_order_id' => $ro->id,
            'company_id' => $carrier->id,
            'status_id' => $status->id,
            'remittance_amount' => 1500,
            'notes' => null,
        ]);

        ['rows' => [$row]] = $this->exportToRows(Shipment::query()->whereKey($record->id));

        $labels = ShipmentExporter::columnLabels();

        $this->assertSame($record->shipment_no, $row[$labels['shipment_no']]);
        $this->assertSame('RO-EXPORT-001', $row[$labels['registered_order']]);
        $this->assertSame('Export Carrier EN', $row[$labels['carrier']]);
        $this->assertSame($status->english_name, $row[$labels['status']]);
        $this->assertSame($status->english_name, $row[$labels['status_english']]);
        $this->assertSame('1500.00000', $row[$labels['remittance_amount']]);
    }

    public function test_exporter_escapes_formula_injection_in_free_text_fields(): void
    {
        $record = Shipment::factory()->create([
            'notes' => '=1+1',
            'bl_number' => '+SUM(1,2)',
            'booking_no' => '@SUM(1,2)',
            'contract_no' => '-2+3',
        ]);

        ['rows' => [$row]] = $this->exportToRows(Shipment::query()->whereKey($record->id));
        $labels = ShipmentExporter::columnLabels();

        $this->assertSame("'=1+1", $row[$labels['notes']]);
        $this->assertSame("'+SUM(1,2)", $row[$labels['bl_number']]);
        $this->assertSame("'@SUM(1,2)", $row[$labels['booking_no']]);
        $this->assertSame("'-2+3", $row[$labels['contract_no']]);
    }

    public function test_exporter_escapes_formula_injection_in_creator_and_updater_names(): void
    {
        $creator = User::factory()->create(['name' => '=1+1']);
        $this->actingAs($creator);
        $record = Shipment::factory()->create();

        $updater = User::factory()->create(['name' => '+SUM(1,2)']);
        $this->actingAs($updater);
        $record->update(['notes' => 'triggers updated_by_id']);

        ['rows' => [$row]] = $this->exportToRows(Shipment::query()->whereKey($record->id));
        $labels = ShipmentExporter::columnLabels();

        $this->assertSame("'=1+1", $row[$labels['creator']]);
        $this->assertSame("'+SUM(1,2)", $row[$labels['updater']]);
    }

    // RegisteredOrderRelationManager — the belongsTo hub tab on the Shipment edit page
    // (single record, no header actions; only the export bulk action in the toolbar)

    public function test_registered_order_relation_manager_renders_and_shows_only_the_order_linked_to_the_owner(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'registered_order.view']);

        $owner = Shipment::factory()->create();
        $unlinked = RegisteredOrder::factory()->create();

        Livewire::test(RegisteredOrderRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => EditShipment::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$owner->registeredOrder])
            ->assertCanNotSeeTableRecords([$unlinked]);
    }

    public function test_registered_order_relation_manager_search_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'registered_order.view']);

        $owner = Shipment::factory()->create();
        $match = $owner->registeredOrder;
        $noMatch = Shipment::factory()->create()->registeredOrder;

        RegisteredOrder::whereKey($match->id)->update(['ro_number' => 'SHPRM-RO-TARGET-'.$match->id]);
        RegisteredOrder::whereKey($noMatch->id)->update(['ro_number' => 'SHPRM-RO-OTHER-'.$noMatch->id]);

        // A belongsTo table shows only one record, so the negative control here is the
        // search actually filtering: searching the OTHER order's number must blank the table.
        Livewire::test(RegisteredOrderRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => EditShipment::class,
        ])
            ->assertCanSeeTableRecords([$match])
            ->searchTable('SHPRM-RO-OTHER-'.$noMatch->id)
            ->assertCanNotSeeTableRecords([$match])
            ->searchTable('SHPRM-RO-TARGET-'.$match->id)
            ->assertCanSeeTableRecords([$match]);
    }

    public function test_registered_order_relation_manager_sort_does_not_error_on_the_single_record_table(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'registered_order.view']);

        $owner = Shipment::factory()->create();

        Livewire::test(RegisteredOrderRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => EditShipment::class,
        ])
            ->sortTable('id', 'asc')
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$owner->registeredOrder]);
    }

    public function test_registered_order_relation_manager_export_bulk_action_runs_without_error(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['shipment.view', 'registered_order.view']);

        $owner = Shipment::factory()->create();
        $record = $owner->registeredOrder;

        Livewire::test(RegisteredOrderRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => EditShipment::class,
        ])
            ->callTableBulkAction('exportRegisteredOrders', [$record])
            ->assertHasNoTableActionErrors();

        Queue::assertPushed(ExportRegisteredOrders::class, fn (ExportRegisteredOrders $job) => $job->ids === [$record->id]);
    }

    public function test_registered_order_relation_manager_offers_no_bulk_import_action(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view', 'registered_order.view']);

        $owner = Shipment::factory()->create();

        $table = Livewire::test(RegisteredOrderRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => EditShipment::class,
        ])->instance()->getTable();

        $flatten = fn (array $actions) => collect($actions)
            ->flatMap(fn ($action) => $action instanceof ActionGroup ? $action->getActions() : [$action]);

        $headerActions = $flatten($table->getHeaderActions());
        $bulkActions = $flatten($table->getBulkActions());

        $this->assertCount(0, $headerActions);

        $this->assertContains('exportRegisteredOrders', $bulkActions->map(fn ($action) => $action->getName()));

        $this->assertCount(0, $bulkActions->filter(
            fn ($action) => $action instanceof ImportAction || str_contains(mb_strtolower($action->getName()), 'import')
        ), 'A RelationManager must never offer bulk import (importsPattern.md).');
    }
}
