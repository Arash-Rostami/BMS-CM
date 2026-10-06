<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BankProfileResource;
use App\Filament\Resources\Operational\BankProfileResource\Exports\BankProfileExporter;
use App\Filament\Resources\Operational\BankProfileResource\Imports\BankProfileImporter;
use App\Filament\Resources\Operational\BankProfileResource\Pages\CreateBankProfile;
use App\Filament\Resources\Operational\BankProfileResource\Pages\EditBankProfile;
use App\Filament\Resources\Operational\BankProfileResource\Pages\ListBankProfiles;
use App\Filament\Resources\Operational\BankProfileResource\RelationManagers\RegisteredOrdersRelationManager;
use App\Filament\Resources\Operational\RegisteredOrderResource\Pages\EditRegisteredOrder;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\BankProfilesRelationManager;
use App\Models\Attachment;
use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Product;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
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

class BankProfileResourceTest extends TestCase
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

    private function bpStatus(string $englishName): Status
    {
        return Status::factory()->create([
            'type' => BankProfile::TYPE_BANK_PROFILE,
            'english_type' => BankProfile::TYPE_BANK_PROFILE,
            'name' => $englishName,
            'english_name' => $englishName,
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

    private function repeatableItemComponents(RepeatableEntry $entry): array
    {
        $reflection = new ReflectionProperty($entry, 'childComponents');
        $reflection->setAccessible(true);

        return $reflection->getValue($entry)['default'];
    }

    public function test_infolist_status_history_tab_renders_and_badge_matches_history_count(): void
    {
        app()->setLocale('en');
        $bp = BankProfile::factory()->create();
        $bp->update(['status_id' => $this->bpStatus('Allocated')->id]);
        $bp->load('statusHistories');

        $tab = $this->statusHistoryTab(BankProfileResource::infolist(Schema::make()));

        $this->assertNotNull($tab);
        $this->assertSame($bp->statusHistories->count(), $this->statusHistoryTabBadge($tab, $bp));
    }

    // Permissions

    public function test_full_permissions_allow_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([
            'bank_profile.view',
            'bank_profile.create',
            'bank_profile.edit',
            'bank_profile.delete',
            'bank_profile.restore',
        ]);

        $record = BankProfile::factory()->create();

        $this->assertTrue(BankProfileResource::canViewAny());
        $this->assertTrue(BankProfileResource::canCreate());
        $this->assertTrue(BankProfileResource::canEdit($record));
        $this->assertTrue(BankProfileResource::canDelete($record));
        $this->assertTrue(BankProfileResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = BankProfile::factory()->create();

        $this->assertFalse(BankProfileResource::canViewAny());
        $this->assertFalse(BankProfileResource::canCreate());
        $this->assertFalse(BankProfileResource::canEdit($record));
        $this->assertFalse(BankProfileResource::canDelete($record));
        $this->assertFalse(BankProfileResource::canRestore($record));
    }

    // List — search

    public function test_list_page_renders_and_search_finds_by_bp_number(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view']);

        $target = BankProfile::factory()->create();
        $other = BankProfile::factory()->create();

        BankProfile::whereKey($target->id)->update(['bp_number' => 'BP-SEARCH-TARGET-'.$target->id]);
        BankProfile::whereKey($other->id)->update(['bp_number' => 'BP-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();
        $term = 'TARGET-'.$target->id;

        Livewire::test(ListBankProfiles::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_excludes_records_whose_status_does_not_match_the_term(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view']);

        $matchingStatus = $this->bpStatus('Approved');
        $otherStatus = $this->bpStatus('Rejected');
        $target = BankProfile::factory()->create(['status_id' => $matchingStatus->id]);
        $other = BankProfile::factory()->create(['status_id' => $otherStatus->id]);

        Livewire::test(ListBankProfiles::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable('Approved')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_finds_records_by_extra_attribute_key_and_value(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view']);

        $target = BankProfile::factory()->create();
        $other = BankProfile::factory()->create();
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(ListBankProfiles::class)
            ->searchTable('contract_reference')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_bank_profiles_relation_manager_search_finds_records_by_extra_attribute(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view', 'bank_profile.view']);

        $ro = RegisteredOrder::factory()->create();
        $target = BankProfile::factory()->create(['registered_order_id' => $ro->id]);
        $other = BankProfile::factory()->create(['registered_order_id' => $ro->id]);
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(BankProfilesRelationManager::class, [
            'ownerRecord' => $ro,
            'pageClass' => EditRegisteredOrder::class,
        ])
            ->assertSuccessful()
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_globally_searchable_attributes_include_extra_attribute_columns(): void
    {
        $attributes = BankProfileResource::getGloballySearchableAttributes();

        $this->assertContains('bp_number', $attributes);
        $this->assertContains('order_number', $attributes);
        $this->assertContains('extraAttributes.key', $attributes);
        $this->assertContains('extraAttributes.value', $attributes);
    }

    // Table columns — financial figures

    public function test_requested_amount_and_currency_columns_render_delimited_amount_and_localized_currency_name(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['bank_profile.view']);

        $currency = Currency::factory()->create(['english_name' => 'United States Dollar']);
        $record = BankProfile::factory()->create([
            'requested_amount' => 12345.6789,
            'requested_currency_id' => $currency->id,
        ]);

        Livewire::test(ListBankProfiles::class)
            ->assertTableColumnFormattedStateSet('requested_amount', delimiter($record->requested_amount), $record)
            ->assertTableColumnFormattedStateSet('requestedCurrency.name', 'United States Dollar', $record);
    }

    // Sorting — polymorphic targetable

    public function test_targetable_column_sorts_by_resolved_name_across_product_and_category(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['bank_profile.view']);

        $product = Product::factory()->create(['english_name' => 'Alpha Product']);
        $category = Category::factory()->create(['english_name' => 'Beta Category']);

        $withProduct = BankProfile::factory()->create(['targetable_type' => Product::class, 'targetable_id' => $product->id]);
        $withCategory = BankProfile::factory()->create(['targetable_type' => Category::class, 'targetable_id' => $category->id]);
        $withoutTargetable = BankProfile::factory()->create(['targetable_type' => null, 'targetable_id' => null]);

        Livewire::test(ListBankProfiles::class)
            ->sortTable('targetable.name')
            ->assertCanSeeTableRecords([$withoutTargetable, $withProduct, $withCategory], inOrder: true)
            ->sortTable('targetable.name', 'desc')
            ->assertCanSeeTableRecords([$withCategory, $withProduct, $withoutTargetable], inOrder: true);
    }

    // Filters

    public function test_bank_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view']);

        $bankA = Bank::factory()->create();
        $bankB = Bank::factory()->create();
        $withA = BankProfile::factory()->create(['bank_id' => $bankA->id]);
        $withB = BankProfile::factory()->create(['bank_id' => $bankB->id]);

        Livewire::test(ListBankProfiles::class)
            ->filterTable('bank_id', $bankA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_company_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view']);

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $withA = BankProfile::factory()->create(['company_id' => $companyA->id]);
        $withB = BankProfile::factory()->create(['company_id' => $companyB->id]);

        Livewire::test(ListBankProfiles::class)
            ->filterTable('company_id', $companyA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view']);

        $statusA = $this->bpStatus('FilterStatusA');
        $statusB = $this->bpStatus('FilterStatusB');
        $withA = BankProfile::factory()->create(['status_id' => $statusA->id]);
        $withB = BankProfile::factory()->create(['status_id' => $statusB->id]);

        Livewire::test(ListBankProfiles::class)
            ->filterTable('status_id', $statusA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_registered_order_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view']);

        $roA = RegisteredOrder::factory()->create();
        $roB = RegisteredOrder::factory()->create();
        $withA = BankProfile::factory()->create(['registered_order_id' => $roA->id]);
        $withB = BankProfile::factory()->create(['registered_order_id' => $roB->id]);

        Livewire::test(ListBankProfiles::class)
            ->filterTable('registered_order_id', $roA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_creator_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['bank_profile.view']);
        $withA = BankProfile::factory()->create();

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $withB = BankProfile::factory()->create();

        $this->actingAs($userA);

        Livewire::test(ListBankProfiles::class)
            ->filterTable('user_id', $userA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_payment_due_date_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view']);

        $withinRange = BankProfile::factory()->create(['payment_due_date' => '2026-01-10']);
        $outsideRange = BankProfile::factory()->create(['payment_due_date' => '2026-06-10']);

        Livewire::test(ListBankProfiles::class)
            ->filterTable('payment_due_date', ['payment_due_from' => '2026-01-01', 'payment_due_until' => '2026-01-31'])
            ->assertCanSeeTableRecords([$withinRange])
            ->assertCanNotSeeTableRecords([$outsideRange]);
    }

    public function test_creation_date_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view']);

        $withinRange = BankProfile::factory()->create();
        $outsideRange = BankProfile::factory()->create();
        BankProfile::whereKey($withinRange->id)->update(['created_at' => '2026-01-10']);
        BankProfile::whereKey($outsideRange->id)->update(['created_at' => '2026-06-10']);

        Livewire::test(ListBankProfiles::class)
            ->filterTable('created_at', ['created_from' => '2026-01-01', 'created_until' => '2026-01-31'])
            ->assertCanSeeTableRecords([$withinRange])
            ->assertCanNotSeeTableRecords([$outsideRange]);
    }

    // Overdue payment/commitment badge

    public function test_payment_and_commitment_overdue_helpers_flag_past_dates_only_when_status_is_not_settled(): void
    {
        $openStatus = $this->bpStatus('Allocated');
        $receivedStatus = $this->bpStatus('Received');
        $rejectedStatus = $this->bpStatus('Rejected');

        $overdueOpen = BankProfile::factory()->create([
            'status_id' => $openStatus->id,
            'payment_due_date' => now()->subDay(),
            'commitment_payment_date' => now()->subDay(),
        ]);
        $overdueReceived = BankProfile::factory()->create([
            'status_id' => $receivedStatus->id,
            'payment_due_date' => now()->subDay(),
            'commitment_payment_date' => now()->subDay(),
        ]);
        $overdueRejected = BankProfile::factory()->create([
            'status_id' => $rejectedStatus->id,
            'payment_due_date' => now()->subDay(),
            'commitment_payment_date' => now()->subDay(),
        ]);
        $futureOpen = BankProfile::factory()->create([
            'status_id' => $openStatus->id,
            'payment_due_date' => now()->addDay(),
            'commitment_payment_date' => now()->addDay(),
        ]);

        $this->assertTrue(BankProfileResource::isPaymentOverdue($overdueOpen));
        $this->assertTrue(BankProfileResource::isCommitmentOverdue($overdueOpen));
        $this->assertFalse(BankProfileResource::isPaymentOverdue($overdueReceived));
        $this->assertFalse(BankProfileResource::isCommitmentOverdue($overdueReceived));
        $this->assertFalse(BankProfileResource::isPaymentOverdue($overdueRejected));
        $this->assertFalse(BankProfileResource::isCommitmentOverdue($overdueRejected));
        $this->assertFalse(BankProfileResource::isPaymentOverdue($futureOpen));
        $this->assertFalse(BankProfileResource::isCommitmentOverdue($futureOpen));
    }

    // Computed financial attributes — infolist correctness (accessor correctness itself is BankProfileModelTest's job)

    public function test_commission_equivalent_and_remaining_commitment_infolist_entries_render_the_precise_computed_value(): void
    {
        $record = BankProfile::factory()->create([
            'purchased_equivalent' => 1000,
            'requested_amount' => 2000,
            'commission_amount_purchased' => 50,
            'documents_amount' => 300,
        ]);

        $commissionEntry = BankProfileResource::viewCommissionEquivalent()->model($record);
        $remainingEntry = BankProfileResource::viewRemainingCommitment()->model($record);

        $this->assertSame(preciseNumber($record->commission_equivalent), $commissionEntry->formatState($record->commission_equivalent));
        $this->assertSame(preciseNumber($record->remaining_commitment), $remainingEntry->formatState($record->remaining_commitment));
        $this->assertEquals(100, $record->commission_equivalent);
        $this->assertEquals(1700, $record->remaining_commitment);
    }

    public function test_remaining_commitment_infolist_entry_color_reflects_over_drawn_zero_and_healthy_balances(): void
    {
        $overDrawn = BankProfile::factory()->create(['requested_amount' => 1000, 'documents_amount' => 1500]);
        $exact = BankProfile::factory()->create(['requested_amount' => 1000, 'documents_amount' => 1000]);
        $healthy = BankProfile::factory()->create(['requested_amount' => 1000, 'documents_amount' => 500]);

        $overDrawnEntry = BankProfileResource::viewRemainingCommitment()->model($overDrawn);
        $exactEntry = BankProfileResource::viewRemainingCommitment()->model($exact);
        $healthyEntry = BankProfileResource::viewRemainingCommitment()->model($healthy);

        $this->assertSame('danger', $overDrawnEntry->getColor($overDrawn->remaining_commitment));
        $this->assertSame('gray', $exactEntry->getColor($exact->remaining_commitment));
        $this->assertSame('success', $healthyEntry->getColor($healthy->remaining_commitment));
    }

    public function test_total_rial_remittance_infolist_entry_renders_the_precise_computed_value(): void
    {
        $record = BankProfile::factory()->create([
            'purchased_currency_id' => 1,
            'purchased_equivalent' => 800,
            'exchange_rate' => 50,
        ]);

        $entry = BankProfileResource::viewTotalRialRemittance()->model($record);

        $this->assertSame(preciseNumber($record->total_rial_remittance), $entry->formatState($record->total_rial_remittance));
        $this->assertEquals(40000, $record->total_rial_remittance);
    }

    // EAV custom attributes

    public function test_custom_attributes_map_does_not_render_a_blank_value_as_the_literal_word_null(): void
    {
        $record = BankProfile::factory()->create();
        $record->syncCustomAttributes(['blank_field' => null, 'filled_field' => 'hello']);

        $map = $record->getCustomAttributesMap();

        $this->assertSame('', $map['blank_field']);
        $this->assertSame('hello', $map['filled_field']);
    }

    // Create — validation only (§3d fillForm() quirk resolved 2026-09-26; this test predates it)

    public function test_create_requires_registered_order_currencies_amount_and_status(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.create', 'bank_profile.view']);

        Livewire::test(CreateBankProfile::class)
            ->fillForm([
                'registered_order_id' => null,
                'requested_currency_id' => null,
                'purchased_currency_id' => null,
                'requested_amount' => null,
                'status_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'registered_order_id' => 'required',
                'requested_currency_id' => 'required',
                'purchased_currency_id' => 'required',
                'requested_amount' => 'required',
                'status_id' => 'required',
            ]);
    }

    // Create — Registered Order field auto-populates and locks when reached from the RO's relation manager

    public function test_registered_order_field_is_auto_filled_and_disabled_when_created_from_a_registered_order(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.create', 'bank_profile.view']);
        $ro = RegisteredOrder::factory()->create([
            'buyer_id' => Company::factory()->create()->id,
            'currency_id' => Currency::factory()->create()->id,
        ]);

        $instance = Livewire::withQueryParams(['registered_order_id' => (string) $ro->id])
            ->test(CreateBankProfile::class);

        $this->assertEquals($ro->id, $instance->get('data.registered_order_id'));
        $this->assertTrue($instance->instance()->form->getComponent('registered_order_id')->isDisabled());
    }

    public function test_registered_order_field_stays_a_manual_required_picker_on_a_standalone_create(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.create', 'bank_profile.view']);

        $instance = Livewire::test(CreateBankProfile::class);

        $this->assertFalse($instance->instance()->form->getComponent('registered_order_id')->isDisabled());
    }

    // Edit — relationship-bound field only (§3d fillForm() quirk resolved 2026-09-26; plain scalars may be added here again)

    public function test_edit_page_loads_existing_values_and_persists_a_status_update(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.edit', 'bank_profile.view']);
        $statusA = $this->bpStatus('EditStatusA');
        $statusB = $this->bpStatus('EditStatusB');
        $product = Product::factory()->create();
        $record = BankProfile::factory()->create([
            'status_id' => $statusA->id,
            'targetable_type' => Product::class,
            'targetable_id' => $product->id,
            'supply_source' => 'nimayi',
            'creation_date' => '2026-01-01',
            'allocation_date' => '2026-01-05',
            'purchase_date' => '2026-01-05',
            'delivery_date' => '2026-01-05',
        ]);

        Livewire::test(EditBankProfile::class, ['record' => $record->getRouteKey()])
            ->assertFormSet(['status_id' => $statusA->id])
            ->fillForm(['status_id' => $statusB->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($statusB->id, $record->fresh()->status_id);
    }

    // Status workflow (HasStatusWorkflow) — safe no-op today, no admin-configured stage_order/approval_permission

    public function test_only_the_edit_page_exposes_the_status_workflow_pipeline_header_action(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.create', 'bank_profile.view', 'bank_profile.edit']);
        $record = BankProfile::factory()->create();

        Livewire::test(ListBankProfiles::class)
            ->assertActionDoesNotExist('statusWorkflowPipeline');

        Livewire::test(EditBankProfile::class, ['record' => $record->getRouteKey()])
            ->assertActionExists('statusWorkflowPipeline');
    }

    public function test_available_status_workflow_ids_include_every_status_while_workflow_is_unconfigured(): void
    {
        $statusA = $this->bpStatus('NoOpStatusA');
        $statusB = $this->bpStatus('NoOpStatusB');

        $method = new ReflectionMethod(BankProfileResource::class, 'availableStatusWorkflowIds');
        $method->setAccessible(true);
        $ids = $method->invoke(null, 'status_id', null);

        $this->assertContains($statusA->id, $ids);
        $this->assertContains($statusB->id, $ids);
    }

    public function test_apply_initial_status_on_create_is_a_no_op_while_workflow_is_unconfigured(): void
    {
        $this->bpStatus('NoOpStatusA');

        $result = BankProfileResource::applyInitialStatusOnCreate(['bp_number' => 'BP-NOOP']);

        $this->assertArrayNotHasKey('status_id', $result);
    }

    public function test_status_field_stays_enabled_and_manually_pickable_on_create_while_workflow_is_unconfigured(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.create', 'bank_profile.view']);

        $instance = Livewire::test(CreateBankProfile::class);

        $this->assertFalse($instance->instance()->form->getComponent('status_id')->isDisabled());
        $this->assertNull($instance->get('data.status_id'));
    }

    public function test_assert_status_transition_allowed_permits_any_status_while_workflow_is_unconfigured(): void
    {
        $statusA = $this->bpStatus('NoOpStatusA');
        $statusB = $this->bpStatus('NoOpStatusB');
        $record = BankProfile::factory()->create(['status_id' => $statusA->id]);

        BankProfileResource::assertStatusTransitionAllowed($record, 'status_id', $statusB->id);

        $this->assertTrue(true);
    }

    public function test_status_field_locks_to_the_configured_initial_stage_once_stage_order_is_set(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.create', 'bank_profile.view']);
        $stage1 = $this->bpStatus('ConfiguredStage1');
        $stage1->update(['stage_order' => 1]);
        $this->bpStatus('ConfiguredStage2')->update(['stage_order' => 2]);

        $instance = Livewire::test(CreateBankProfile::class);

        $this->assertTrue($instance->instance()->form->getComponent('status_id')->isDisabled());
        $this->assertEquals($stage1->id, $instance->get('data.status_id'));
    }

    public function test_assert_status_transition_allowed_blocks_skipping_a_configured_stage(): void
    {
        $stage1 = $this->bpStatus('ConfiguredStage1');
        $stage1->update(['stage_order' => 1]);
        $stage3 = $this->bpStatus('ConfiguredStage3');
        $stage3->update(['stage_order' => 3]);
        $record = BankProfile::factory()->create(['status_id' => $stage1->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        BankProfileResource::assertStatusTransitionAllowed($record, 'status_id', $stage3->id);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view', 'bank_profile.delete', 'bank_profile.restore']);
        $record = BankProfile::factory()->create();

        Livewire::test(ListBankProfiles::class)
            ->callTableAction('delete', $record);

        $this->assertNull(BankProfile::find($record->id));
        $this->assertTrue(BankProfile::withTrashed()->find($record->id)->trashed());

        Livewire::test(ListBankProfiles::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(BankProfile::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view', 'bank_profile.delete']);
        $one = BankProfile::factory()->create();
        $two = BankProfile::factory()->create();

        Livewire::test(ListBankProfiles::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(BankProfile::find($one->id));
        $this->assertNull(BankProfile::find($two->id));
    }

    // Global search contract

    public function test_global_search_title_uses_bank_emoji_prefix_and_bp_number(): void
    {
        $record = BankProfile::factory()->create();

        $this->assertSame('🏦 '.$record->bp_number, BankProfileResource::getGlobalSearchResultTitle($record));
    }

    // RelationManager smoke

    public function test_registered_orders_relation_manager_renders_and_exports_without_error(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['bank_profile.view', 'registered_order.view']);

        $bankProfile = BankProfile::factory()->create();

        Livewire::test(RegisteredOrdersRelationManager::class, [
            'ownerRecord' => $bankProfile,
            'pageClass' => EditBankProfile::class,
        ])
            ->assertSuccessful()
            ->callTableBulkAction('exportRegisteredOrders', [$bankProfile->registeredOrder])
            ->assertHasNoTableBulkActionErrors();

        Queue::assertPushed(\App\Jobs\ExportRegisteredOrders::class);
    }

    // Import / Export — flat single-row bulk transfer

    private function importColumnMap(): array
    {
        $names = collect(BankProfileImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeImporter(array $data, array $options = []): BankProfileImporter
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $importer = new BankProfileImporter(new Import, $this->importColumnMap(), array_merge([
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
            'bp_number' => '',
            'registered_order_id' => '',
            'company_id' => '',
            'bank_id' => '',
            'status_id' => '',
            'requested_amount' => '',
            'requested_currency_id' => '',
            'purchased_equivalent' => '',
            'purchased_currency_id' => '',
            'commission_rate' => '',
            'payment_due_date' => '',
            'notes' => '',
        ], $overrides);
    }

    public function test_import_creates_a_new_record_with_bp_number_auto_generated_when_blank(): void
    {
        $ro = RegisteredOrder::factory()->create();

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
        ]));

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertMatchesRegularExpression('/^BP-\d{6}(-\d+)?$/', $record->bp_number);
        $this->assertSame($ro->id, $record->registered_order_id);
    }

    public function test_import_reupload_of_existing_bp_number_updates_in_place(): void
    {
        $ro = RegisteredOrder::factory()->create();

        $row = $this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
            'notes' => 'First upload',
        ]);

        $first = $this->invokeImporter($row);
        $id = $first->getRecord()->id;

        $row['bp_number'] = $first->getRecord()->bp_number;
        $row['notes'] = 'Second upload';

        $second = $this->invokeImporter($row);

        $this->assertSame($id, $second->getRecord()->id);
        $this->assertSame('Second upload', $second->getRecord()->notes);
    }

    public function test_import_rejects_an_unresolvable_registered_order_value(): void
    {
        try {
            $this->invokeImporter($this->baseImportRow([
                'registered_order_id' => 'RO-DOES-NOT-EXIST',
            ]));

            $this->fail('Expected ValidationException was not thrown.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertStringContainsString('RO-DOES-NOT-EXIST', collect($exception->errors())->flatten()->implode(' '));
        }
    }

    public function test_import_rejects_a_blank_registered_order_on_a_new_record_cleanly(): void
    {
        app()->setLocale('en');
        $before = BankProfile::count();

        try {
            $this->invokeImporter($this->baseImportRow([]));

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertSame(__('resources/general/strings.import.required_for_new_record', [
                'label' => __('resources/bankProfile/strings.form.registered_order'),
            ]), $exception->getMessage());
        }

        $this->assertSame($before, BankProfile::count());
    }

    public function test_import_reupload_with_a_blank_registered_order_preserves_the_existing_value(): void
    {
        $ro = RegisteredOrder::factory()->create();

        $first = $this->invokeImporter($this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
        ]));
        $record = $first->getRecord();

        $row = $this->baseImportRow([
            'bp_number' => $record->bp_number,
            'notes' => 'Second upload, registered order left blank',
        ]);

        $second = $this->invokeImporter($row);

        $this->assertSame($record->id, $second->getRecord()->id);
        $this->assertSame($ro->id, $second->getRecord()->registered_order_id);
    }

    public function test_import_new_record_with_unresolvable_company_saves_via_null_fallback_and_logs_a_note(): void
    {
        $ro = RegisteredOrder::factory()->create();

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
            'company_id' => 'Nonexistent Company XYZ',
        ]));

        $record = $importer->getRecord();

        $this->assertNull($record->company_id);
        $this->assertStringContainsString('Nonexistent Company XYZ', $record->notes);
    }

    public function test_import_blank_payment_due_date_falls_back_to_two_weeks_after_the_import_date(): void
    {
        $ro = RegisteredOrder::factory()->create();

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
        ]));

        $record = $importer->getRecord();

        $this->assertSame(now()->addWeeks(2)->format('Y-m-d'), $record->payment_due_date->format('Y-m-d'));
    }

    public function test_import_blank_numeric_columns_fall_back_to_zero(): void
    {
        $ro = RegisteredOrder::factory()->create();

        $importer = $this->invokeImporter($this->baseImportRow([
            'registered_order_id' => $ro->ro_number,
        ]));

        $record = $importer->getRecord();

        $this->assertSame('0.00', number_format((float) $record->requested_amount, 2));
        $this->assertSame('0.00', number_format((float) $record->purchased_equivalent, 2));
        $this->assertSame('0.00', number_format((float) $record->commission_rate, 2));
    }

    public function test_filled_example_file_exists_and_matches_the_import_shape(): void
    {
        $path = BankProfileImporter::filledExamplePath();

        $this->assertNotNull($path);

        $fullPath = storage_path('app/'.$path);
        $this->assertFileExists($fullPath);

        $contents = file_get_contents($fullPath);

        $this->assertStringContainsString('registered_order_id', $contents);
        $this->assertStringContainsString('bp_number', $contents);
    }

    public function test_auto_generated_empty_example_has_a_real_header_for_every_import_column(): void
    {
        // The "download example" action Filament auto-generates (header + a synthetic example row,
        // distinct from our own filled-example CSV above) must have a real, non-blank header label
        // for every column — an empty/placeholder example header would ship a useless template file.
        foreach (BankProfileImporter::getColumns() as $column) {
            $this->assertNotSame('', trim($column->getExampleHeader()), "Column [{$column->getName()}] has a blank example header.");
        }
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Pinned count — a silent column drop during a future refactor (exactly what happened to
        // BankProfile's exporter this session) must fail this test, not slip through unnoticed.
        $this->assertCount(12, BankProfileImporter::getColumns());
        $this->assertCount(12, BankProfileImporter::columnLabels());
        $this->assertCount(41, BankProfileExporter::columnLabels());
    }

    public function test_filled_example_file_imports_cleanly_end_to_end(): void
    {
        RegisteredOrder::factory()->create(['ro_number' => 'RO-260714']);
        RegisteredOrder::factory()->create(['ro_number' => 'RO-260718']);
        RegisteredOrder::factory()->create(['ro_number' => 'RO-260718-1']);
        Company::factory()->create(['english_name' => 'Persol', 'is_active' => true]);
        Company::factory()->create(['english_name' => 'Tejarat Oraman Pars', 'is_active' => true]);
        Bank::factory()->create(['english_name' => 'Melli', 'is_active' => true]);
        Bank::factory()->create(['english_name' => 'Sepah', 'is_active' => true]);
        Currency::factory()->create(['english_name' => 'United States Dollar']);
        Currency::factory()->create(['english_name' => 'Euro']);
        Currency::factory()->create(['english_name' => 'Japanese Yen']);
        $this->bpStatus('Allocated');
        $this->bpStatus('Purchased');
        $this->bpStatus('Documentary Bill');

        $fullPath = storage_path('app/'.BankProfileImporter::filledExamplePath());
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

        $this->assertCount(3, $imported);
        $this->assertSame('Persol', $imported[0]->getRecord()->company->english_name);
        $this->assertSame('Allocated', $imported[0]->getRecord()->status->english_name);
        $this->assertNull($imported[2]->getRecord()->company_id);
        $this->assertStringContainsString('NONEXISTENT COMPANY XYZ', $imported[2]->getRecord()->notes);
    }

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'bp_export_').'.csv';
        BankProfileExporter::write($query, $path);

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
        $company = Company::factory()->create(['english_name' => 'Export Company EN', 'is_active' => true]);
        $bank = Bank::factory()->create(['english_name' => 'Export Bank EN', 'is_active' => true]);
        $status = $this->bpStatus('ExportStatusCheck');
        $currency = Currency::factory()->create(['english_name' => 'EXU1']);

        $record = BankProfile::factory()->create([
            'registered_order_id' => $ro->id,
            'company_id' => $company->id,
            'bank_id' => $bank->id,
            'status_id' => $status->id,
            'requested_amount' => 1000,
            'requested_currency_id' => $currency->id,
            'payment_due_date' => '2026-01-15',
            'notes' => null,
        ]);

        ['rows' => [$row]] = $this->exportToRows(BankProfile::query()->whereKey($record->id));

        $labels = BankProfileExporter::columnLabels();

        $this->assertSame($record->bp_number, $row[$labels['bp_number']]);
        $this->assertSame($ro->ro_number, $row[$labels['registered_order']]);
        $this->assertSame('Export Company EN', $row[$labels['company']]);
        $this->assertSame('Export Bank EN', $row[$labels['bank']]);
        $this->assertSame($status->english_name, $row[$labels['status']]);
        $this->assertSame($status->english_name, $row[$labels['status_english']]);
        $this->assertSame(jdate($record->payment_due_date)->format('Y-m-d'), $row[$labels['payment_due_date']]);
    }

    public function test_exporter_escapes_formula_injection_in_creator_and_updater_names(): void
    {
        app()->setLocale('en');
        $ro = RegisteredOrder::factory()->create();
        $company = Company::factory()->create(['is_active' => true]);
        $bank = Bank::factory()->create(['is_active' => true]);
        $status = $this->bpStatus('CreatorEscapeCheck');
        $currency = Currency::factory()->create();

        $creator = User::factory()->create(['name' => '=1+1']);
        $this->actingAs($creator);
        $record = BankProfile::factory()->create([
            'registered_order_id' => $ro->id,
            'company_id' => $company->id,
            'bank_id' => $bank->id,
            'status_id' => $status->id,
            'requested_amount' => 1000,
            'requested_currency_id' => $currency->id,
            'payment_due_date' => '2026-01-15',
        ]);

        $updater = User::factory()->create(['name' => '+SUM(1,2)']);
        $this->actingAs($updater);
        $record->update(['notes' => 'triggers updated_by_id']);

        ['rows' => [$row]] = $this->exportToRows(BankProfile::query()->whereKey($record->id));
        $labels = BankProfileExporter::columnLabels();

        $this->assertSame("'=1+1", $row[$labels['creator']]);
        $this->assertSame("'+SUM(1,2)", $row[$labels['updater']]);
    }

    public function test_exporter_restores_the_full_column_set_wider_than_import(): void
    {
        app()->setLocale('en');
        $ro = RegisteredOrder::factory()->create();
        $company = Company::factory()->create(['english_name' => 'Full Export Company', 'is_active' => true]);
        $bank = Bank::factory()->create(['english_name' => 'Full Export Bank', 'is_active' => true]);
        $status = $this->bpStatus('FullExportStatus');
        $requestedCurrency = Currency::factory()->create(['english_name' => 'FEC1']);
        $purchasedCurrency = Currency::factory()->create(['english_name' => 'FEC2']);
        $creator = User::factory()->create(['name' => 'Full Export Creator']);
        $updater = User::factory()->create(['name' => 'Full Export Updater']);
        $product = Product::factory()->create();

        $record = BankProfile::factory()->create([
            'registered_order_id' => $ro->id,
            'company_id' => $company->id,
            'bank_id' => $bank->id,
            'status_id' => $status->id,
            'requested_currency_id' => $requestedCurrency->id,
            'purchased_currency_id' => $purchasedCurrency->id,
            'targetable_type' => Product::class,
            'targetable_id' => $product->id,
            'requested_amount' => 1000,
            'purchased_equivalent' => 2000,
            'commission_rate' => 10,
            'documents_amount' => 500,
            'user_id' => $creator->id,
            'updated_by_id' => $updater->id,
        ]);

        $fresh = BankProfile::with(['targetable', 'creator', 'updater'])->find($record->id);

        ['header' => $header, 'rows' => [$row]] = $this->exportToRows(BankProfile::query()->whereKey($record->id));

        $labels = BankProfileExporter::columnLabels();

        $this->assertCount(41, $header);
        $this->assertSame((string) $fresh->commission_equivalent, $row[$labels['commission_equivalent']]);
        $this->assertSame($fresh->getTargetableFormatted('export'), $row[$labels['targetable']]);
        $this->assertSame('Full Export Creator', $row[$labels['creator']]);
        $this->assertSame('Full Export Updater', $row[$labels['updater']]);
    }

    // Bulk-action ordering convention

    public function test_bulk_actions_toolbar_orders_export_before_delete_and_restore(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view', 'bank_profile.delete', 'bank_profile.restore']);

        Livewire::test(ListBankProfiles::class)
            ->assertTableBulkActionsExistInOrder(['exportBankProfiles', 'delete', 'restore']);
    }

    public function test_edit_form_shows_a_status_select_for_each_attachment(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.view', 'bank_profile.edit']);
        $record = BankProfile::factory()->create();
        $uploaded = Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_UPLOADED);
        $attachment = Attachment::factory()->forAttachable($record)->create(['status_id' => $uploaded->id]);

        Livewire::test(EditBankProfile::class, ['record' => $record->getRouteKey()])
            ->assertSee($attachment->name ?: basename($attachment->path))
            ->assertSee($uploaded->getLocalizedNameAttribute());
    }

    public function test_attachments_infolist_entry_wires_the_supersede_and_revert_actions(): void
    {
        $entry = collect($this->repeatableItemComponents(BankProfileResource::viewAttachments()))
            ->first(fn ($component) => $component->getName() === 'status.name');

        $reflection = new ReflectionProperty($entry, 'suffixActions');
        $reflection->setAccessible(true);

        $names = collect($reflection->getValue($entry))->map(fn ($action) => $action->getName())->all();

        $this->assertSame(['supersedeAttachment', 'revertAttachment'], $names);
    }

    public function test_attachments_infolist_entry_splits_filename_and_status_three_to_two(): void
    {
        $entry = BankProfileResource::viewAttachments();

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
