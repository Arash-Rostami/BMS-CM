<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Operational\PaymentResource\Pages\CreatePayment;
use App\Filament\Resources\Operational\PaymentResource\Pages\EditPayment;
use App\Filament\Resources\Operational\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\Operational\PaymentResource\RelationManagers\PurchaseOrderRelationManager;
use App\Filament\Resources\Operational\PaymentResource\RelationManagers\RegisteredOrderRelationManager;
use App\Filament\Resources\PaymentResource;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\SmartCacheManager;
use App\Services\StatusWorkflow;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class PaymentResourceTest extends TestCase
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
        $payment = Payment::factory()->create();
        $payment->update(['status_id' => Status::factory()->create()->id]);
        $payment->load('statusHistories');

        $tab = $this->statusHistoryTab(PaymentResource::infolist(Schema::make()));

        $this->assertNotNull($tab);
        $this->assertSame($payment->statusHistories->count(), $this->statusHistoryTabBadge($tab, $payment));
    }

    public function test_purchase_order_relation_manager_renders_when_payment_targets_a_purchase_order(): void
    {
        $this->actingAsUserWithPermissions(['payment.view', 'purchase_order.view']);

        $po = PurchaseOrder::factory()->create();
        $payment = Payment::factory()->forTargetable($po)->create();

        Livewire::test(PurchaseOrderRelationManager::class, [
            'ownerRecord' => $payment,
            'pageClass' => EditPayment::class,
        ])->assertSuccessful();
    }

    public function test_purchase_order_relation_manager_renders_empty_when_payment_targets_a_registered_order(): void
    {
        $this->actingAsUserWithPermissions(['payment.view', 'purchase_order.view']);

        $ro = RegisteredOrder::factory()->create();
        $payment = Payment::factory()->forTargetable($ro)->create();

        Livewire::test(PurchaseOrderRelationManager::class, [
            'ownerRecord' => $payment,
            'pageClass' => EditPayment::class,
        ])->assertSuccessful();
    }

    public function test_registered_order_relation_manager_renders_when_payment_targets_a_registered_order(): void
    {
        $this->actingAsUserWithPermissions(['payment.view', 'registered_order.view']);

        $ro = RegisteredOrder::factory()->create();
        $payment = Payment::factory()->forTargetable($ro)->create();

        Livewire::test(RegisteredOrderRelationManager::class, [
            'ownerRecord' => $payment,
            'pageClass' => EditPayment::class,
        ])->assertSuccessful();
    }

    private function deterministicPaymentOverrides(): array
    {
        return [
            'bank_id' => Bank::factory()->create(['is_active' => true])->id,
            'payor_id' => Company::factory()->create(['is_active' => true])->id,
            'payee_id' => Company::factory()->create(['is_active' => true])->id,
            'payment_date' => now()->subDay()->format('Y-m-d'),
            'payment_deadline' => now()->format('Y-m-d'),
        ];
    }

    // Status workflow — safe no-op today (Payment has no admin-configured ordering yet), mechanism verified with a temporary ordering

    public function test_list_and_edit_pages_expose_the_status_workflow_pipeline_header_action(): void
    {
        $this->actingAsUserWithPermissions(['payment.create', 'payment.view', 'payment.edit']);
        $payment = Payment::factory()->create();

        Livewire::test(ListPayments::class)
            ->assertActionExists('statusWorkflowPipeline');

        Livewire::test(EditPayment::class, ['record' => $payment->getRouteKey()])
            ->assertActionExists('statusWorkflowPipeline');
    }

    public function test_payment_status_workflow_has_no_configured_ordering_today(): void
    {
        $this->assertNull(StatusWorkflow::initialFor(Payment::TYPE_PAYMENT));
    }

    public function test_available_status_workflow_ids_returns_the_full_unrestricted_list_when_no_workflow_is_configured(): void
    {
        $expected = Status::where('english_type', Payment::TYPE_PAYMENT)->pluck('id')->sort()->values()->all();
        $payment = Payment::factory()->create(['status_id' => $expected[0]]);

        $method = new ReflectionMethod(PaymentResource::class, 'availableStatusWorkflowIds');
        $method->setAccessible(true);
        $ids = $method->invoke(null, 'status_id', $payment->fresh());
        sort($ids);

        $this->assertSame($expected, $ids);
    }

    public function test_apply_initial_status_on_create_is_a_no_op_without_an_ordered_workflow(): void
    {
        $data = PaymentResource::applyInitialStatusOnCreate(['payment_no' => 'PAY-TEST']);

        $this->assertArrayNotHasKey('status_id', $data);
    }

    public function test_create_page_leaves_an_explicitly_chosen_status_untouched_when_no_workflow_is_configured(): void
    {
        $this->actingAsUserWithPermissions(['payment.create', 'payment.view']);
        $status = Status::where('english_type', Payment::TYPE_PAYMENT)->firstOrFail();

        $page = new CreatePayment;
        $method = new ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $data = $method->invoke($page, ['status_id' => $status->id]);

        $this->assertSame($status->id, $data['status_id']);
    }

    public function test_editing_status_to_any_real_status_succeeds_when_no_workflow_is_configured(): void
    {
        $this->actingAsUserWithPermissions(['payment.edit', 'payment.view']);
        $statuses = Status::where('english_type', Payment::TYPE_PAYMENT)->pluck('id');
        $payment = Payment::factory()->create([...$this->deterministicPaymentOverrides(), 'status_id' => $statuses->first()]);
        $target = $statuses->last();

        Livewire::test(EditPayment::class, ['record' => $payment->getRouteKey()])
            ->set('data.status_id', $target)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($target, $payment->fresh()->status_id);
    }

    private function orderedPaymentStatuses(): array
    {
        $type = Payment::TYPE_PAYMENT;

        $stageOne = Status::factory()->create([
            'type' => $type, 'english_type' => $type,
            'name' => 'Stage One', 'english_name' => 'Stage One',
            'stage_order' => 1, 'approval_permission' => null,
        ]);
        $stageTwo = Status::factory()->create([
            'type' => $type, 'english_type' => $type,
            'name' => 'Stage Two', 'english_name' => 'Stage Two',
            'stage_order' => 2, 'approval_permission' => 'test_payment_stage_two_approval',
        ]);

        return [$stageOne, $stageTwo];
    }

    public function test_editing_status_forward_without_the_gating_permission_is_rejected_once_a_workflow_is_configured(): void
    {
        [$stageOne, $stageTwo] = $this->orderedPaymentStatuses();
        $this->actingAsUserWithPermissions(['payment.edit', 'payment.view']);
        $payment = Payment::factory()->create([...$this->deterministicPaymentOverrides(), 'status_id' => $stageOne->id]);

        Livewire::test(EditPayment::class, ['record' => $payment->getRouteKey()])
            ->set('data.status_id', $stageTwo->id)
            ->call('save')
            ->assertHasFormErrors(['status_id']);

        $this->assertSame('Stage One', $payment->fresh()->status->english_name);
    }

    public function test_editing_status_forward_with_the_gating_permission_succeeds_once_a_workflow_is_configured(): void
    {
        [$stageOne, $stageTwo] = $this->orderedPaymentStatuses();
        $this->actingAsUserWithPermissions(['payment.edit', 'payment.view', 'test_payment_stage_two_approval']);
        $payment = Payment::factory()->create([...$this->deterministicPaymentOverrides(), 'status_id' => $stageOne->id]);

        Livewire::test(EditPayment::class, ['record' => $payment->getRouteKey()])
            ->set('data.status_id', $stageTwo->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Stage Two', $payment->fresh()->status->english_name);
    }
}
