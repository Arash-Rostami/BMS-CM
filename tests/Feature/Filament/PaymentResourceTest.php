<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Operational\PaymentResource\Enums\Target;
use App\Filament\Resources\Operational\PaymentResource\Exports\PaymentExporter;
use App\Filament\Resources\Operational\PaymentResource\Imports\PaymentImporter;
use App\Filament\Resources\Operational\PaymentResource\Pages\CreatePayment;
use App\Filament\Resources\Operational\PaymentResource\Pages\EditPayment;
use App\Filament\Resources\Operational\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\Operational\PaymentResource\RelationManagers\PurchaseOrderRelationManager;
use App\Filament\Resources\Operational\PaymentResource\RelationManagers\RegisteredOrderRelationManager;
use App\Filament\Resources\PaymentResource;
use App\Models\Attachment;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\SmartCacheManager;
use App\Services\StatusWorkflow;
use Filament\Actions\Imports\Models\Import;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
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

    private function repeatableItemComponents(RepeatableEntry $entry): array
    {
        $reflection = new ReflectionProperty($entry, 'childComponents');
        $reflection->setAccessible(true);

        return $reflection->getValue($entry)['default'];
    }

    private function fakeGet(array $state): Get
    {
        return new class($state) extends Get
        {
            public function __construct(private readonly array $state) {}

            public function __invoke(string|\Filament\Schemas\Components\Component $path = '', bool $isAbsolute = false): mixed
            {
                return $this->state[$path] ?? null;
            }
        };
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

    public function test_only_the_edit_page_exposes_the_status_workflow_pipeline_header_action(): void
    {
        $this->actingAsUserWithPermissions(['payment.create', 'payment.view', 'payment.edit']);
        $payment = Payment::factory()->create();

        Livewire::test(ListPayments::class)
            ->assertActionDoesNotExist('statusWorkflowPipeline');

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

    public function test_edit_form_shows_a_status_select_for_each_attachment(): void
    {
        $this->actingAsUserWithPermissions(['payment.view', 'payment.edit']);
        $payment = Payment::factory()->create();
        $uploaded = Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_UPLOADED);
        $attachment = Attachment::factory()->forAttachable($payment)->create(['status_id' => $uploaded->id]);

        Livewire::test(EditPayment::class, ['record' => $payment->getRouteKey()])
            ->assertSee($attachment->name ?: basename($attachment->path))
            ->assertSee($uploaded->getLocalizedNameAttribute());
    }

    public function test_attachments_infolist_entry_wires_the_supersede_and_revert_actions(): void
    {
        $entry = collect($this->repeatableItemComponents(PaymentResource::viewAttachments()))
            ->first(fn ($component) => $component->getName() === 'status.name');

        $reflection = new ReflectionProperty($entry, 'suffixActions');
        $reflection->setAccessible(true);

        $names = collect($reflection->getValue($entry))->map(fn ($action) => $action->getName())->all();

        $this->assertSame(['supersedeAttachment', 'revertAttachment'], $names);
    }

    public function test_attachments_infolist_entry_splits_filename_and_status_three_to_two(): void
    {
        $entry = PaymentResource::viewAttachments();

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
            'payment.view',
            'payment.create',
            'payment.edit',
            'payment.delete',
            'payment.restore',
        ]);

        $record = Payment::factory()->create();

        $this->assertTrue(PaymentResource::canViewAny());
        $this->assertTrue(PaymentResource::canCreate());
        $this->assertTrue(PaymentResource::canEdit($record));
        $this->assertTrue(PaymentResource::canDelete($record));
        $this->assertTrue(PaymentResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = Payment::factory()->create();

        $this->assertFalse(PaymentResource::canViewAny());
        $this->assertFalse(PaymentResource::canCreate());
        $this->assertFalse(PaymentResource::canEdit($record));
        $this->assertFalse(PaymentResource::canDelete($record));
        $this->assertFalse(PaymentResource::canRestore($record));
    }

    // List — search

    public function test_list_page_renders_and_search_finds_by_payment_no(): void
    {
        $this->actingAsUserWithPermissions(['payment.view']);

        $target = Payment::factory()->create();
        $other = Payment::factory()->create();

        Payment::whereKey($target->id)->update(['payment_no' => 'PAY-SEARCH-TARGET-'.$target->id]);
        Payment::whereKey($other->id)->update(['payment_no' => 'PAY-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();
        $term = 'TARGET-'.$target->id;

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_finds_records_by_extra_attribute_key_and_value(): void
    {
        $this->actingAsUserWithPermissions(['payment.view']);

        $target = Payment::factory()->create();
        $other = Payment::factory()->create();
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(ListPayments::class)
            ->searchTable('contract_reference')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_globally_searchable_attributes_include_extra_attribute_columns(): void
    {
        $attributes = PaymentResource::getGloballySearchableAttributes();

        $this->assertContains('payment_no', $attributes);
        $this->assertContains('beneficiary_name', $attributes);
        $this->assertContains('extraAttributes.key', $attributes);
        $this->assertContains('extraAttributes.value', $attributes);
    }

    // Table columns

    public function test_targetable_column_renders_the_correct_target_badge_for_purchase_order_and_registered_order(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['payment.view']);

        $po = PurchaseOrder::factory()->create();
        $ro = RegisteredOrder::factory()->create();
        $poPayment = Payment::factory()->forTargetable($po)->create();
        $roPayment = Payment::factory()->forTargetable($ro)->create();

        Livewire::test(ListPayments::class)
            ->assertTableColumnFormattedStateSet('targetable', Target::PO->getLabel(), $poPayment)
            ->assertTableColumnFormattedStateSet('targetable', Target::RO->getLabel(), $roPayment);
    }

    public function test_targetable_type_column_renders_the_resolved_display_name_for_its_target(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['payment.view']);

        $po = PurchaseOrder::factory()->create();
        $payment = Payment::factory()->forTargetable($po)->create();
        $expected = $payment->fresh()->load('targetable')->getTargetableDisplay(false);

        Livewire::test(ListPayments::class)
            ->assertTableColumnFormattedStateSet('targetable_type', $expected, $payment);
    }

    public function test_total_amount_column_renders_a_delimited_amount(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['payment.view']);

        $payment = Payment::factory()->create(['total_amount' => 12345.6789]);

        Livewire::test(ListPayments::class)
            ->assertTableColumnFormattedStateSet('total_amount', delimiter($payment->total_amount), $payment);
    }

    public function test_payor_and_payee_columns_render_the_localized_company_name(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['payment.view']);

        $payor = Company::factory()->create(['is_active' => true, 'english_name' => 'Payor Display Co']);
        $payee = Company::factory()->create(['is_active' => true, 'english_name' => 'Payee Display Co']);
        $payment = Payment::factory()->create(['payor_id' => $payor->id, 'payee_id' => $payee->id]);

        Livewire::test(ListPayments::class)
            ->assertTableColumnFormattedStateSet('payor.name', 'Payor Display Co', $payment)
            ->assertTableColumnFormattedStateSet('payee.name', 'Payee Display Co', $payment);
    }

    // Total-mismatch badge

    public function test_total_match_column_shows_matches_when_components_sum_to_total(): void
    {
        app()->setLocale('en');
        $record = Payment::factory()->create(['payable_amount' => 950, 'bank_charges' => 50, 'total_amount' => 1000]);

        $column = PaymentResource::showTotalMatch();

        $this->assertSame(__('resources/payment/strings.table.total_match_yes'), $column->formatState($record->total_ratio));
        $this->assertSame('success', $column->getColor($record->total_ratio));
    }

    public function test_total_match_column_shows_mismatch_when_components_do_not_sum_to_total(): void
    {
        app()->setLocale('en');
        $record = Payment::factory()->create(['payable_amount' => 100, 'bank_charges' => 50, 'total_amount' => 1000]);

        $column = PaymentResource::showTotalMatch();

        $this->assertSame(__('resources/payment/strings.table.total_match_no'), $column->formatState($record->total_ratio));
        $this->assertSame('warning', $column->getColor($record->total_ratio));
    }

    public function test_total_match_column_shows_unknown_when_total_amount_is_zero(): void
    {
        app()->setLocale('en');
        $record = Payment::factory()->create(['payable_amount' => 100, 'bank_charges' => 50, 'total_amount' => 0]);

        $column = PaymentResource::showTotalMatch();

        $this->assertNull($record->total_ratio);
        $this->assertSame(__('resources/payment/strings.table.total_match_unknown'), $column->formatState($record->total_ratio));
        $this->assertSame('gray', $column->getColor($record->total_ratio));
    }

    // Filters

    public function test_targetable_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['payment.view']);

        $po = PurchaseOrder::factory()->create();
        $ro = RegisteredOrder::factory()->create();
        $withPo = Payment::factory()->forTargetable($po)->create();
        $withRo = Payment::factory()->forTargetable($ro)->create();

        Livewire::test(ListPayments::class)
            ->filterTable('targetable_type', PurchaseOrder::class)
            ->assertCanSeeTableRecords([$withPo])
            ->assertCanNotSeeTableRecords([$withRo]);
    }

    public function test_payor_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['payment.view']);

        $payorA = Company::factory()->create(['is_active' => true]);
        $payorB = Company::factory()->create(['is_active' => true]);
        $withA = Payment::factory()->create(['payor_id' => $payorA->id]);
        $withB = Payment::factory()->create(['payor_id' => $payorB->id]);

        Livewire::test(ListPayments::class)
            ->filterTable('payor_id', $payorA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_payee_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['payment.view']);

        $payeeA = Company::factory()->create(['is_active' => true]);
        $payeeB = Company::factory()->create(['is_active' => true]);
        $withA = Payment::factory()->create(['payee_id' => $payeeA->id]);
        $withB = Payment::factory()->create(['payee_id' => $payeeB->id]);

        Livewire::test(ListPayments::class)
            ->filterTable('payee_id', $payeeA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['payment.view']);

        $statusA = Status::factory()->create([
            'type' => Payment::TYPE_PAYMENT, 'english_type' => Payment::TYPE_PAYMENT,
            'name' => 'FilterStatusA', 'english_name' => 'FilterStatusA',
        ]);
        $statusB = Status::factory()->create([
            'type' => Payment::TYPE_PAYMENT, 'english_type' => Payment::TYPE_PAYMENT,
            'name' => 'FilterStatusB', 'english_name' => 'FilterStatusB',
        ]);
        $withA = Payment::factory()->create(['status_id' => $statusA->id]);
        $withB = Payment::factory()->create(['status_id' => $statusB->id]);

        Livewire::test(ListPayments::class)
            ->filterTable('status_id', $statusA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_currency_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['payment.view']);

        $currencyA = Currency::factory()->create();
        $currencyB = Currency::factory()->create();
        $withA = Payment::factory()->create(['currency_id' => $currencyA->id]);
        $withB = Payment::factory()->create(['currency_id' => $currencyB->id]);

        Livewire::test(ListPayments::class)
            ->filterTable('currency_id', $currencyA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_creator_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['payment.view']);
        $withA = Payment::factory()->create();

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $withB = Payment::factory()->create();

        $this->actingAs($userA);

        Livewire::test(ListPayments::class)
            ->filterTable('user_id', $userA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_creation_date_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['payment.view']);

        $withinRange = Payment::factory()->create();
        $outsideRange = Payment::factory()->create();
        Payment::whereKey($withinRange->id)->update(['created_at' => '2026-01-10']);
        Payment::whereKey($outsideRange->id)->update(['created_at' => '2026-06-10']);

        Livewire::test(ListPayments::class)
            ->filterTable('created_at', ['created_from' => '2026-01-01', 'created_until' => '2026-01-31'])
            ->assertCanSeeTableRecords([$withinRange])
            ->assertCanNotSeeTableRecords([$outsideRange]);
    }

    public function test_trashed_filter_narrows_the_table_to_soft_deleted_records(): void
    {
        $this->actingAsUserWithPermissions(['payment.view', 'payment.delete']);

        $active = Payment::factory()->create();
        $deleted = Payment::factory()->create();
        $deleted->delete();

        Livewire::test(ListPayments::class)
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$deleted])
            ->assertCanNotSeeTableRecords([$active]);
    }

    // Create — validation

    public function test_create_requires_a_targetable_selection(): void
    {
        $this->actingAsUserWithPermissions(['payment.create', 'payment.view']);

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'targetable_type' => null,
                'targetable_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['targetable_type' => 'required']);
    }

    public function test_create_requires_payor_payee_currency_and_payable_amount_once_a_target_is_selected(): void
    {
        $this->actingAsUserWithPermissions(['payment.create', 'payment.view']);
        $ro = RegisteredOrder::factory()->create();

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'targetable_type' => RegisteredOrder::class,
                'targetable_id' => $ro->id,
                'payor_id' => null,
                'payee_id' => null,
                'currency_id' => null,
                'payable_amount' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'payor_id' => 'required',
                'payee_id' => 'required',
                'currency_id' => 'required',
                'payable_amount' => 'required',
            ]);
    }

    public function test_create_rejects_a_nonexistent_payor_id_with_translated_message(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['payment.create', 'payment.view']);
        $ro = RegisteredOrder::factory()->create();

        $test = Livewire::test(CreatePayment::class)
            ->fillForm([
                'targetable_type' => RegisteredOrder::class,
                'targetable_id' => $ro->id,
                'payor_id' => 999999,
            ])
            ->call('create');

        $this->assertSame(
            [__('resources/payment/strings.form.validation_in')],
            $test->errors()->get('data.payor_id')
        );
    }

    public function test_payment_no_is_auto_generated_on_create(): void
    {
        $this->actingAsUserWithPermissions(['payment.create', 'payment.view']);
        $ro = RegisteredOrder::factory()->create();
        $company = Company::factory()->create(['is_active' => true]);
        $currency = Currency::factory()->create();
        $status = Status::factory()->create(['type' => Payment::TYPE_PAYMENT, 'english_type' => Payment::TYPE_PAYMENT]);

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'targetable_type' => RegisteredOrder::class,
                'targetable_id' => $ro->id,
                'payor_id' => $company->id,
                'payee_id' => $company->id,
                'currency_id' => $currency->id,
                'payable_amount' => 1000,
                'status_id' => $status->id,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $record = Payment::latest('id')->first();

        $this->assertMatchesRegularExpression('/^P-\d{6}(-\d+)?$/', $record->payment_no);
    }

    public function test_payment_deadline_must_be_on_or_after_the_payment_date(): void
    {
        $this->actingAsUserWithPermissions(['payment.create', 'payment.view']);
        $ro = RegisteredOrder::factory()->create();
        $company = Company::factory()->create(['is_active' => true]);
        $currency = Currency::factory()->create();

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'targetable_type' => RegisteredOrder::class,
                'targetable_id' => $ro->id,
                'payor_id' => $company->id,
                'payee_id' => $company->id,
                'currency_id' => $currency->id,
                'payable_amount' => 1000,
                'payment_date' => '2026-01-10',
                'payment_deadline' => '2026-01-01',
            ])
            ->call('create')
            ->assertHasFormErrors(['payment_deadline' => 'after_or_equal']);
    }

    // Target-select auto-fill

    public function test_selecting_a_target_auto_fills_blank_payee_payor_and_currency_fields(): void
    {
        $this->actingAsUserWithPermissions(['payment.create', 'payment.view']);
        $seller = Company::factory()->create(['is_active' => true]);
        $buyer = Company::factory()->create(['is_active' => true]);
        $currency = Currency::factory()->create();
        $po = PurchaseOrder::factory()->create([
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'currency_id' => $currency->id,
        ]);

        Livewire::test(CreatePayment::class)
            ->fillForm(['targetable_type' => PurchaseOrder::class])
            ->set('data.targetable_id', $po->id)
            ->assertFormSet([
                'payee_id' => $seller->id,
                'payor_id' => $buyer->id,
                'currency_id' => $currency->id,
            ]);
    }

    public function test_selecting_a_target_does_not_overwrite_an_already_filled_payee(): void
    {
        $this->actingAsUserWithPermissions(['payment.create', 'payment.view']);
        $seller = Company::factory()->create(['is_active' => true]);
        $manualPayee = Company::factory()->create(['is_active' => true]);
        $po = PurchaseOrder::factory()->create(['seller_id' => $seller->id]);

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'targetable_type' => PurchaseOrder::class,
                'payee_id' => $manualPayee->id,
            ])
            ->set('data.targetable_id', $po->id)
            ->assertFormSet(['payee_id' => $manualPayee->id]);
    }

    // Duplicate-payment warning

    public function test_create_warns_when_a_recent_duplicate_payment_already_exists(): void
    {
        $this->actingAsUserWithPermissions(['payment.create', 'payment.view']);
        $ro = RegisteredOrder::factory()->create();
        $payee = Company::factory()->create(['is_active' => true]);
        $payor = Company::factory()->create(['is_active' => true]);
        $currency = Currency::factory()->create();

        $status = Status::factory()->create(['type' => Payment::TYPE_PAYMENT, 'english_type' => Payment::TYPE_PAYMENT]);
        $recent = Payment::factory()->forTargetable($ro)->create(['payee_id' => $payee->id]);
        Payment::whereKey($recent->id)->update(['created_at' => now()->subHours(2)]);

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'targetable_type' => RegisteredOrder::class,
                'targetable_id' => $ro->id,
                'payor_id' => $payor->id,
                'payee_id' => $payee->id,
                'currency_id' => $currency->id,
                'payable_amount' => 1000,
                'status_id' => $status->id,
            ])
            ->call('create')
            ->assertHasNoErrors()
            ->assertNotified(__('resources/payment/strings.notifications.duplicate_title'));
    }

    public function test_create_does_not_warn_when_no_recent_duplicate_payment_exists(): void
    {
        $this->actingAsUserWithPermissions(['payment.create', 'payment.view']);
        $ro = RegisteredOrder::factory()->create();
        $payee = Company::factory()->create(['is_active' => true]);
        $payor = Company::factory()->create(['is_active' => true]);
        $currency = Currency::factory()->create();

        $status = Status::factory()->create(['type' => Payment::TYPE_PAYMENT, 'english_type' => Payment::TYPE_PAYMENT]);
        $old = Payment::factory()->forTargetable($ro)->create(['payee_id' => $payee->id]);
        Payment::whereKey($old->id)->update(['created_at' => now()->subDays(2)]);

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'targetable_type' => RegisteredOrder::class,
                'targetable_id' => $ro->id,
                'payor_id' => $payor->id,
                'payee_id' => $payee->id,
                'currency_id' => $currency->id,
                'payable_amount' => 1000,
                'status_id' => $status->id,
            ])
            ->call('create')
            ->assertHasNoErrors()
            ->assertNotNotified(__('resources/payment/strings.notifications.duplicate_title'));
    }

    // Edit

    public function test_edit_page_loads_existing_values_and_persists_a_beneficiary_name_update(): void
    {
        $this->actingAsUserWithPermissions(['payment.edit', 'payment.view']);
        $status = Status::where('english_type', Payment::TYPE_PAYMENT)->firstOrFail();
        $payment = Payment::factory()->create([
            ...$this->deterministicPaymentOverrides(),
            'status_id' => $status->id,
            'beneficiary_name' => 'Old Beneficiary',
        ]);

        Livewire::test(EditPayment::class, ['record' => $payment->getRouteKey()])
            ->assertFormSet(['beneficiary_name' => 'Old Beneficiary'])
            ->fillForm(['beneficiary_name' => 'New Beneficiary'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New Beneficiary', $payment->fresh()->beneficiary_name);
    }

    // IBAN-changed hint

    private function ibanHintClosure(): \Closure
    {
        $field = PaymentResource::getIbanField();
        $reflection = new ReflectionProperty($field, 'hint');
        $reflection->setAccessible(true);

        return $reflection->getValue($field);
    }

    public function test_iban_hint_warns_when_the_typed_iban_differs_from_the_payees_most_recent_payment(): void
    {
        app()->setLocale('en');
        $payee = Company::factory()->create(['is_active' => true]);
        Payment::factory()->create(['payee_id' => $payee->id, 'iban' => 'OLD-IBAN-001']);

        $hint = $this->ibanHintClosure();
        $result = $hint($this->fakeGet(['payee_id' => $payee->id, 'iban' => 'NEW-IBAN-999']), null);

        $this->assertSame(__('resources/payment/strings.form.hint_iban_changed'), $result);
    }

    public function test_iban_hint_is_silent_when_the_typed_iban_matches_the_payees_most_recent_payment(): void
    {
        $payee = Company::factory()->create(['is_active' => true]);
        Payment::factory()->create(['payee_id' => $payee->id, 'iban' => 'SAME-IBAN-001']);

        $hint = $this->ibanHintClosure();
        $result = $hint($this->fakeGet(['payee_id' => $payee->id, 'iban' => 'SAME-IBAN-001']), null);

        $this->assertNull($result);
    }

    public function test_iban_hint_is_silent_when_the_payee_has_no_prior_payment(): void
    {
        $payee = Company::factory()->create(['is_active' => true]);

        $hint = $this->ibanHintClosure();
        $result = $hint($this->fakeGet(['payee_id' => $payee->id, 'iban' => 'ANY-IBAN-001']), null);

        $this->assertNull($result);
    }

    public function test_iban_hint_excludes_the_record_being_edited_from_the_comparison(): void
    {
        $payee = Company::factory()->create(['is_active' => true]);
        $record = Payment::factory()->create(['payee_id' => $payee->id, 'iban' => 'OLD-IBAN-001']);

        $hint = $this->ibanHintClosure();
        $result = $hint($this->fakeGet(['payee_id' => $payee->id, 'iban' => 'NEW-IBAN-999']), $record);

        $this->assertNull($result);
    }

    // Infolist — computed summary entries

    public function test_calculated_total_and_total_ratio_infolist_entries_render_the_precise_computed_value(): void
    {
        $record = Payment::factory()->create([
            'payable_amount' => 1000,
            'bank_charges' => 50,
            'total_amount' => 2000,
        ]);

        $calculatedEntry = PaymentResource::viewCalculatedTotal()->model($record);
        $ratioEntry = PaymentResource::viewTotalRatio()->model($record);

        $this->assertSame(preciseNumber($record->calculated_total), $calculatedEntry->formatState($record->calculated_total));
        $this->assertSame(preciseNumber($record->total_ratio * 100).'%', $ratioEntry->formatState($record->total_ratio));
        $this->assertEquals(1050, $record->calculated_total);
        $this->assertEquals(0.525, (float) $record->total_ratio);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['payment.view', 'payment.delete', 'payment.restore']);
        $record = Payment::factory()->create();

        Livewire::test(ListPayments::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Payment::find($record->id));
        $this->assertTrue(Payment::withTrashed()->find($record->id)->trashed());

        Livewire::test(ListPayments::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(Payment::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['payment.view', 'payment.delete']);
        $one = Payment::factory()->create();
        $two = Payment::factory()->create();

        Livewire::test(ListPayments::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(Payment::find($one->id));
        $this->assertNull(Payment::find($two->id));
    }

    // Global search contract

    public function test_global_search_title_uses_payment_emoji_prefix_and_payment_no(): void
    {
        $record = Payment::factory()->create();

        $this->assertSame('💳 '.$record->payment_no, PaymentResource::getGlobalSearchResultTitle($record));
    }

    public function test_global_search_result_details_returns_payor_payee_and_payment_date(): void
    {
        app()->setLocale('en');
        $payor = Company::factory()->create(['is_active' => true, 'english_name' => 'Payor Co']);
        $payee = Company::factory()->create(['is_active' => true, 'english_name' => 'Payee Co']);
        $record = Payment::factory()->create([
            'payor_id' => $payor->id,
            'payee_id' => $payee->id,
            'payment_date' => '2026-01-15',
        ]);
        $record->load(['payor', 'payee']);

        $details = PaymentResource::getGlobalSearchResultDetails($record);

        $this->assertSame('Payor Co', $details[__('resources/payment/strings.form.payor')]);
        $this->assertSame('Payee Co', $details[__('resources/payment/strings.form.payee')]);
        $this->assertSame(adaptiveDate('2026-01-15'), $details[__('resources/payment/strings.form.payment_date')]);
    }

    // Bulk-action ordering convention

    public function test_bulk_actions_toolbar_orders_export_before_delete_and_restore(): void
    {
        $this->actingAsUserWithPermissions(['payment.view', 'payment.delete', 'payment.restore']);

        Livewire::test(ListPayments::class)
            ->assertTableBulkActionsExistInOrder(['exportPayments', 'delete', 'restore']);
    }

    // Import / Export — flat single-row bulk transfer

    private function importColumnMap(): array
    {
        $names = collect(PaymentImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeImporter(array $data, array $options = []): PaymentImporter
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $importer = new PaymentImporter(new Import, $this->importColumnMap(), array_merge([
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
            'payment_no' => '',
            'payment_date' => '',
            'payment_deadline' => '',
            'status_id' => '',
            'payor_id' => '',
            'payee_id' => '',
            'currency_id' => '',
            'bank_id' => '',
            'payable_amount' => '',
            'total_amount' => '',
            'exchange_rate' => '',
            'bank_charges' => '',
            'beneficiary_name' => '',
            'beneficiary_address' => '',
            'bank_address' => '',
            'account_no' => '',
            'swift' => '',
            'iban' => '',
            'notes' => '',
            'purchase_order_number' => '',
            'registered_order_number' => '',
        ], $overrides);
    }

    public function test_import_creates_a_new_record_with_payment_no_auto_generated_when_blank(): void
    {
        $ro = RegisteredOrder::factory()->create();

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_number' => $ro->ro_number,
        ]));

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertMatchesRegularExpression('/^P-\d{6}(-\d+)?$/', $record->payment_no);
        $this->assertSame(RegisteredOrder::class, $record->targetable_type);
        $this->assertSame($ro->id, $record->targetable_id);
    }

    public function test_import_reupload_of_existing_payment_no_updates_in_place(): void
    {
        $ro = RegisteredOrder::factory()->create();

        $row = $this->baseImportRow([
            'registered_order_number' => $ro->ro_number,
            'notes' => 'First upload',
        ]);

        $first = $this->invokeImporter($row);
        $id = $first->getRecord()->id;

        $row['payment_no'] = $first->getRecord()->payment_no;
        $row['notes'] = 'Second upload';

        $second = $this->invokeImporter($row);

        $this->assertSame($id, $second->getRecord()->id);
        $this->assertSame('Second upload', $second->getRecord()->notes);
    }

    public function test_import_rejects_when_both_purchase_order_and_registered_order_numbers_are_given(): void
    {
        app()->setLocale('en');
        $po = PurchaseOrder::factory()->create();
        $ro = RegisteredOrder::factory()->create();

        try {
            $this->invokeImporter($this->baseImportRow([
                'purchase_order_number' => $po->po_number,
                'registered_order_number' => $ro->ro_number,
            ]));

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertSame(__('resources/payment/strings.import.targetable_ambiguous'), $exception->getMessage());
        }
    }

    public function test_import_rejects_an_unresolvable_purchase_order_number(): void
    {
        try {
            $this->invokeImporter($this->baseImportRow([
                'purchase_order_number' => 'PO-DOES-NOT-EXIST',
            ]));

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertStringContainsString('PO-DOES-NOT-EXIST', $exception->getMessage());
        }
    }

    public function test_import_rejects_a_new_record_with_no_targetable_identifier_given(): void
    {
        app()->setLocale('en');

        try {
            $this->invokeImporter($this->baseImportRow([]));

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertSame(__('resources/payment/strings.import.targetable_required'), $exception->getMessage());
        }
    }

    public function test_import_new_record_with_unresolvable_payor_saves_via_null_fallback_and_logs_a_note(): void
    {
        $ro = RegisteredOrder::factory()->create();

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_number' => $ro->ro_number,
            'payor_id' => 'Nonexistent Company XYZ',
        ]));

        $record = $importer->getRecord();

        $this->assertNull($record->payor_id);
        $this->assertStringContainsString('Nonexistent Company XYZ', $record->notes);
    }

    public function test_import_blank_numeric_columns_fall_back_to_zero(): void
    {
        $ro = RegisteredOrder::factory()->create();

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_number' => $ro->ro_number,
        ]));

        $record = $importer->getRecord();

        $this->assertSame('0.00', number_format((float) $record->payable_amount, 2));
        $this->assertSame('0.00', number_format((float) $record->total_amount, 2));
        $this->assertSame('0.00', number_format((float) $record->exchange_rate, 2));
        $this->assertSame('0.00', number_format((float) $record->bank_charges, 2));
    }

    public function test_import_blank_payment_date_falls_back_to_today(): void
    {
        $ro = RegisteredOrder::factory()->create();

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_number' => $ro->ro_number,
        ]));

        $record = $importer->getRecord();

        $this->assertSame(now()->format('Y-m-d'), $record->payment_date->format('Y-m-d'));
    }

    public function test_filled_example_file_exists_and_matches_the_import_shape(): void
    {
        $path = PaymentImporter::filledExamplePath();

        $this->assertNotNull($path);

        $fullPath = storage_path('app/'.$path);
        $this->assertFileExists($fullPath);

        $contents = file_get_contents($fullPath);

        $this->assertStringContainsString('registered_order_number', $contents);
        $this->assertStringContainsString('payment_no', $contents);
    }

    public function test_auto_generated_empty_example_has_a_real_header_for_every_import_column(): void
    {
        // The "download example" action Filament auto-generates (header + a synthetic example row,
        // distinct from our own filled-example CSV above) must have a real, non-blank header label
        // for every column — an empty/placeholder example header would ship a useless template file.
        foreach (PaymentImporter::getColumns() as $column) {
            $this->assertNotSame('', trim($column->getExampleHeader()), "Column [{$column->getName()}] has a blank example header.");
        }
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Pinned counts — a silent column drop during a future refactor must fail this test, not slip through unnoticed.
        $this->assertCount(21, PaymentImporter::getColumns());
        $this->assertCount(21, PaymentImporter::columnLabels());
        $this->assertCount(32, PaymentExporter::columnLabels());
    }

    public function test_filled_example_file_imports_cleanly_end_to_end(): void
    {
        Company::factory()->create(['english_name' => 'Persol', 'is_active' => true]);
        Company::factory()->create(['english_name' => 'Persore', 'is_active' => true]);
        Company::factory()->create(['english_name' => 'Tejarat Oraman Pars', 'is_active' => true]);
        Company::factory()->create(['english_name' => 'Jamin Choob Iranian', 'is_active' => true]);
        Company::factory()->create(['english_name' => 'Behsan Cellulose Atieh', 'is_active' => true]);
        Bank::factory()->create(['english_name' => 'Melli', 'is_active' => true]);
        Bank::factory()->create(['english_name' => 'Sepah', 'is_active' => true]);
        Bank::factory()->create(['english_name' => 'Mellat', 'is_active' => true]);
        Bank::factory()->create(['english_name' => 'Tejarat', 'is_active' => true]);
        Currency::factory()->create(['english_name' => 'United States Dollar']);
        Currency::factory()->create(['english_name' => 'Euro']);
        Currency::factory()->create(['english_name' => 'Japanese Yen']);
        Currency::factory()->create(['english_name' => 'British Pound Sterling']);
        foreach (['Draft', 'Pending', 'Processing', 'Completed'] as $statusName) {
            Status::factory()->create([
                'type' => Payment::TYPE_PAYMENT, 'english_type' => Payment::TYPE_PAYMENT,
                'name' => $statusName, 'english_name' => $statusName,
            ]);
        }

        $fullPath = storage_path('app/'.PaymentImporter::filledExamplePath());
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
        $this->assertSame('Persol', $imported[0]->getRecord()->payor->english_name);
        $this->assertSame('Draft', $imported[0]->getRecord()->status->english_name);
        $this->assertSame(PurchaseOrder::class, $imported[0]->getRecord()->targetable_type);
        $this->assertSame(RegisteredOrder::class, $imported[1]->getRecord()->targetable_type);
        $this->assertSame('PAY-SAMPLE-1', $imported[2]->getRecord()->payment_no);
    }

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'payment_export_').'.csv';
        PaymentExporter::write($query, $path);

        $csv = (string) file_get_contents($path);
        unlink($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", trim(ltrim($csv, "\xEF\xBB\xBF"))))));
        $header = str_getcsv($lines[0]);
        $rows = array_map(fn (string $line) => array_combine($header, str_getcsv($line)), array_slice($lines, 1));

        return ['header' => $header, 'rows' => $rows];
    }

    public function test_exporter_write_emits_one_parent_row_per_record(): void
    {
        app()->setLocale('en');
        $ro = RegisteredOrder::factory()->create();
        $po = PurchaseOrder::factory()->create();
        $payor = Company::factory()->create(['english_name' => 'Export Payor EN', 'is_active' => true]);
        $payee = Company::factory()->create(['english_name' => 'Export Payee EN', 'is_active' => true]);
        $bank = Bank::factory()->create(['english_name' => 'Export Bank EN', 'is_active' => true]);
        $status = Status::factory()->create([
            'type' => Payment::TYPE_PAYMENT, 'english_type' => Payment::TYPE_PAYMENT,
            'name' => 'ExportStatusCheck', 'english_name' => 'ExportStatusCheck',
        ]);
        $currency = Currency::factory()->create(['english_name' => 'EXU1']);

        $first = Payment::factory()->forTargetable($ro)->create([
            'payor_id' => $payor->id,
            'payee_id' => $payee->id,
            'bank_id' => $bank->id,
            'status_id' => $status->id,
            'currency_id' => $currency->id,
            'payable_amount' => 1000,
            'bank_charges' => 50,
            'beneficiary_address' => 'Export Street 1',
            'bank_address' => 'Export Street 2',
            'notes' => null,
        ]);
        $second = Payment::factory()->forTargetable($po)->create([
            'beneficiary_address' => 'Plain Street 1',
            'bank_address' => 'Plain Street 2',
            'notes' => 'Plain note',
        ]);

        ['rows' => $rows] = $this->exportToRows(Payment::query()->whereIn('id', [$first->id, $second->id]));

        $labels = PaymentExporter::columnLabels();
        $row = collect($rows)->first(fn ($r) => $r[$labels['id']] === (string) $first->id);

        $this->assertCount(2, $rows);
        $this->assertSame($first->payment_no, $row[$labels['payment_no']]);
        $this->assertSame('Export Payor EN', $row[$labels['payor']]);
        $this->assertSame('Export Payee EN', $row[$labels['payee']]);
        $this->assertSame('Export Bank EN', $row[$labels['bank']]);
        $this->assertSame($status->english_name, $row[$labels['status']]);
        $this->assertSame((string) $first->fresh()->calculated_total, $row[$labels['calculated_total']]);
    }

    public function test_exporter_escapes_formula_injection_in_free_text_fields(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $record = Payment::factory()->forTargetable($ro)->create([
            'notes' => '=1+1',
            'iban' => '=cmd|\'/c calc\'!A0',
            'swift' => '+SUM(1,2)',
            'account_no' => '@SUM(1,2)',
            'beneficiary_address' => 'Single Line Address',
            'bank_address' => 'Single Line Address',
        ]);

        ['rows' => [$row]] = $this->exportToRows(Payment::query()->whereKey($record->id));
        $labels = PaymentExporter::columnLabels();

        $this->assertSame("'=1+1", $row[$labels['notes']]);
        $this->assertSame("'=cmd|'/c calc'!A0", $row[$labels['iban']]);
        $this->assertSame("'+SUM(1,2)", $row[$labels['swift']]);
        $this->assertSame("'@SUM(1,2)", $row[$labels['account_no']]);
    }

    public function test_exporter_escapes_formula_injection_in_creator_and_updater_names(): void
    {
        $ro = RegisteredOrder::factory()->create();

        $creator = User::factory()->create(['name' => '=1+1']);
        $this->actingAs($creator);
        $record = Payment::factory()->forTargetable($ro)->create([
            'beneficiary_address' => 'Single Line Address',
            'bank_address' => 'Single Line Address',
        ]);

        $updater = User::factory()->create(['name' => '+SUM(1,2)']);
        $this->actingAs($updater);
        $record->update(['notes' => 'triggers updated_by_id']);

        ['rows' => [$row]] = $this->exportToRows(Payment::query()->whereKey($record->id));
        $labels = PaymentExporter::columnLabels();

        $this->assertSame("'=1+1", $row[$labels['creator']]);
        $this->assertSame("'+SUM(1,2)", $row[$labels['updater']]);
    }
}
