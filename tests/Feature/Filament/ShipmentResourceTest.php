<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Operational\ShipmentResource\Pages\CreateShipment;
use App\Filament\Resources\Operational\ShipmentResource\Pages\EditShipment;
use App\Filament\Resources\Operational\ShipmentResource\RelationManagers\CustomsRelationManager;
use App\Filament\Resources\ShipmentResource;
use App\Models\EntityAttribute;
use App\Models\Permission;
use App\Models\ProformaInvoice;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Services\StatusWorkflow;
use Filament\Actions\Testing\TestAction;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
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
        // fillForm()'s documented plain-scalar drop bug (testPattern.md §3d) —
        // _inv_pi_id is a plain Select, not ->relationship()-bound. saveInvoice
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
}
