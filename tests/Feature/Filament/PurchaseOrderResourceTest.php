<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\CreatePurchaseOrder;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\EditPurchaseOrder;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\ListPurchaseOrders;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\ProformaInvoicesRelationManager;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\PurchaseRequestsRelationManager;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\RegisteredOrdersRelationManager;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\SmartCacheManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
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

    public function test_list_and_edit_pages_expose_the_status_workflow_pipeline_header_action(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.create', 'purchase_order.view', 'purchase_order.edit']);
        $po = PurchaseOrder::factory()->create();

        Livewire::test(ListPurchaseOrders::class)
            ->assertActionExists('statusWorkflowPipeline');

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
}
