<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CustomResource;
use App\Filament\Resources\Operational\CustomResource\Enums\CommitmentStatus;
use App\Filament\Resources\Operational\CustomResource\Enums\GuaranteeStatus;
use App\Filament\Resources\Operational\CustomResource\Exports\CustomExporter;
use App\Filament\Resources\Operational\CustomResource\Imports\CustomImporter;
use App\Filament\Resources\Operational\CustomResource\Pages\CreateCustom;
use App\Filament\Resources\Operational\CustomResource\Pages\EditCustom;
use App\Filament\Resources\Operational\CustomResource\Pages\ListCustoms;
use App\Filament\Resources\Operational\CustomResource\RelationManagers\RegisteredOrderRelationManager;
use App\Filament\Resources\Operational\CustomResource\RelationManagers\ShipmentRelationManager;
use App\Filament\Resources\Operational\CustomResource\Traits\HandleStatusMutation;
use App\Models\Attachment;
use App\Models\Custom;
use App\Models\Permission;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Shipment;
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
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use ReflectionProperty;
use Tests\TestCase;

class CustomResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        SmartCacheManager::invalidate('Custom');
    }

    protected function tearDown(): void
    {
        SmartCacheManager::invalidate('Custom');
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
        $custom = Custom::factory()->create();
        $custom->update(['clearance_status_id' => Status::factory()->create()->id]);
        $custom->load('statusHistories');

        $tab = $this->statusHistoryTab(CustomResource::infolist(Schema::make()));

        $this->assertNotNull($tab);
        $this->assertSame($custom->statusHistories->count(), $this->statusHistoryTabBadge($tab, $custom));
    }

    public function test_registered_order_relation_manager_renders_and_exports_without_error(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['custom.view', 'registered_order.view']);

        $custom = Custom::factory()->create();

        Livewire::test(RegisteredOrderRelationManager::class, [
            'ownerRecord' => $custom,
            'pageClass' => EditCustom::class,
        ])
            ->assertSuccessful()
            ->callTableBulkAction('exportRegisteredOrders', [$custom->registeredOrder])
            ->assertHasNoTableBulkActionErrors();

        Queue::assertPushed(\App\Jobs\ExportRegisteredOrders::class);
    }

    public function test_shipment_relation_manager_renders_for_a_custom_with_a_shipment(): void
    {
        $this->actingAsUserWithPermissions(['custom.view', 'shipment.view']);

        $custom = Custom::factory()->create();

        Livewire::test(ShipmentRelationManager::class, [
            'ownerRecord' => $custom,
            'pageClass' => EditCustom::class,
        ])->assertSuccessful();
    }

    private function statusMutator(): object
    {
        return new class
        {
            use HandleStatusMutation;

            public function run(array $data, ?Custom $record = null): void
            {
                $this->assertCustomStatusTransitionsAllowed($data, $record);
            }
        };
    }

    public function test_status_transitions_are_unrestricted_today_for_all_three_columns_with_no_stage_order_configured(): void
    {
        $clearance = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'stage_order' => null]);
        $guarantee = Status::factory()->create(['english_type' => Custom::TYPE_BANK_GUARANTEE_STATUS, 'stage_order' => null]);
        $commitment = Status::factory()->create(['english_type' => Custom::TYPE_COMMITMENT_STATUS, 'stage_order' => null]);
        $custom = Custom::factory()->create();

        $this->statusMutator()->run([
            'clearance_status_id' => $clearance->id,
            'bank_guarantee_status_id' => $guarantee->id,
            'commitment_status_id' => $commitment->id,
        ], $custom);

        $this->assertTrue(true);
    }

    public function test_server_side_transition_guard_rejects_a_skipped_stage_once_stage_order_is_configured(): void
    {
        $stage1 = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'stage_order' => 1]);
        Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'stage_order' => 2]);
        $stage3 = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'stage_order' => 3]);
        $custom = Custom::factory()->create(['clearance_status_id' => $stage1->id]);

        $this->expectException(ValidationException::class);

        $this->statusMutator()->run(['clearance_status_id' => $stage3->id], $custom);
    }

    public function test_editing_clearance_and_guarantee_status_still_succeeds_with_no_stage_order_configured(): void
    {
        $this->actingAsUserWithPermissions(['custom.view', 'custom.edit']);

        $newClearance = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'stage_order' => null]);
        $newGuarantee = Status::factory()->create(['english_type' => Custom::TYPE_BANK_GUARANTEE_STATUS, 'stage_order' => null]);
        $custom = Custom::factory()->create([
            'clearance_status_id' => Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'stage_order' => null])->id,
            'bank_guarantee_status_id' => Status::factory()->create(['english_type' => Custom::TYPE_BANK_GUARANTEE_STATUS, 'stage_order' => null])->id,
            'commitment_status_id' => Status::factory()->create(['english_type' => Custom::TYPE_COMMITMENT_STATUS, 'stage_order' => null])->id,
        ]);

        Livewire::test(EditCustom::class, ['record' => $custom->getRouteKey()])
            ->set('data.clearance_status_id', $newClearance->id)
            ->set('data.bank_guarantee_status_id', $newGuarantee->id)
            ->call('save')
            ->assertHasNoErrors();

        $custom->refresh();
        $this->assertSame($newClearance->id, $custom->clearance_status_id);
        $this->assertSame($newGuarantee->id, $custom->bank_guarantee_status_id);
    }

    public function test_edit_form_shows_a_status_select_for_each_attachment(): void
    {
        $this->actingAsUserWithPermissions(['custom.view', 'custom.edit']);
        $custom = Custom::factory()->create();
        $uploaded = Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_UPLOADED);
        $attachment = Attachment::factory()->forAttachable($custom)->create(['status_id' => $uploaded->id]);

        Livewire::test(EditCustom::class, ['record' => $custom->getRouteKey()])
            ->assertSee($attachment->name ?: basename($attachment->path))
            ->assertSee($uploaded->getLocalizedNameAttribute());
    }

    public function test_attachments_infolist_entry_wires_the_supersede_and_revert_actions(): void
    {
        $entry = collect($this->repeatableItemComponents(CustomResource::viewAttachments()))
            ->first(fn ($component) => $component->getName() === 'status.name');

        $reflection = new ReflectionProperty($entry, 'suffixActions');
        $reflection->setAccessible(true);

        $names = collect($reflection->getValue($entry))->map(fn ($action) => $action->getName())->all();

        $this->assertSame(['supersedeAttachment', 'revertAttachment'], $names);
    }

    public function test_attachments_infolist_entry_splits_filename_and_status_three_to_two(): void
    {
        $entry = CustomResource::viewAttachments();

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
            'custom.view',
            'custom.create',
            'custom.edit',
            'custom.delete',
            'custom.restore',
        ]);

        $record = Custom::factory()->create();

        $this->assertTrue(CustomResource::canViewAny());
        $this->assertTrue(CustomResource::canCreate());
        $this->assertTrue(CustomResource::canEdit($record));
        $this->assertTrue(CustomResource::canDelete($record));
        $this->assertTrue(CustomResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = Custom::factory()->create();

        $this->assertFalse(CustomResource::canViewAny());
        $this->assertFalse(CustomResource::canCreate());
        $this->assertFalse(CustomResource::canEdit($record));
        $this->assertFalse(CustomResource::canDelete($record));
        $this->assertFalse(CustomResource::canRestore($record));
    }

    // List — search

    public function test_list_page_renders_and_search_finds_by_custom_no(): void
    {
        $this->actingAsUserWithPermissions(['custom.view']);

        $target = Custom::factory()->create();
        $other = Custom::factory()->create();

        Custom::whereKey($target->id)->update(['custom_no' => 'CU-SEARCH-TARGET-'.$target->id]);
        Custom::whereKey($other->id)->update(['custom_no' => 'CU-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();
        $term = 'TARGET-'.$target->id;

        Livewire::test(ListCustoms::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_finds_records_by_extra_attribute_key_and_value(): void
    {
        $this->actingAsUserWithPermissions(['custom.view']);

        $target = Custom::factory()->create();
        $other = Custom::factory()->create();
        $target->syncCustomAttributes(['broker_reference' => 'ACME-9981']);

        Livewire::test(ListCustoms::class)
            ->searchTable('broker_reference')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_globally_searchable_attributes_include_extra_attribute_columns(): void
    {
        $this->assertSame(
            ['declaration_no', 'shipment_no', 'contract_no', 'custom_no', 'extraAttributes.key', 'extraAttributes.value'],
            CustomResource::getGloballySearchableAttributes()
        );
    }

    // Filters

    public function test_registered_order_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['custom.view']);

        $roA = RegisteredOrder::factory()->create();
        $roB = RegisteredOrder::factory()->create();
        $withA = Custom::factory()->create(['registered_order_id' => $roA->id]);
        $withB = Custom::factory()->create(['registered_order_id' => $roB->id]);

        Livewire::test(ListCustoms::class)
            ->filterTable('registered_order_id', $roA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_clearance_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['custom.view']);

        $statusA = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS]);
        $statusB = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS]);
        $withA = Custom::factory()->create(['clearance_status_id' => $statusA->id]);
        $withB = Custom::factory()->create(['clearance_status_id' => $statusB->id]);

        Livewire::test(ListCustoms::class)
            ->filterTable('clearance_status_id', $statusA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_bank_guarantee_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['custom.view']);

        $statusA = Status::factory()->create(['english_type' => Custom::TYPE_BANK_GUARANTEE_STATUS]);
        $statusB = Status::factory()->create(['english_type' => Custom::TYPE_BANK_GUARANTEE_STATUS]);
        $withA = Custom::factory()->create(['bank_guarantee_status_id' => $statusA->id]);
        $withB = Custom::factory()->create(['bank_guarantee_status_id' => $statusB->id]);

        Livewire::test(ListCustoms::class)
            ->filterTable('bank_guarantee_status_id', $statusA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_commitment_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['custom.view']);

        $statusA = Status::factory()->create(['english_type' => Custom::TYPE_COMMITMENT_STATUS]);
        $statusB = Status::factory()->create(['english_type' => Custom::TYPE_COMMITMENT_STATUS]);
        $withA = Custom::factory()->create(['commitment_status_id' => $statusA->id]);
        $withB = Custom::factory()->create(['commitment_status_id' => $statusB->id]);

        Livewire::test(ListCustoms::class)
            ->filterTable('commitment_status_id', $statusA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_clearance_type_filter_uses_the_real_definitive_percentage_vocabulary(): void
    {
        // Regression: the filter used to offer '90_percent'/'10_percent', which never matched any
        // real saved value (the form only ever saves 'definitive'/'percentage') — filtering was a no-op.
        app()->setLocale('en');

        $this->assertSame(
            __('resources/custom/strings.general.clearance_types'),
            CustomResource::getClearanceTypeFilter()->getOptions()
        );

        $this->actingAsUserWithPermissions(['custom.view']);
        $definitive = Custom::factory()->create(['clearance_type' => 'definitive']);
        $percentage = Custom::factory()->create(['clearance_type' => 'percentage']);

        Livewire::test(ListCustoms::class)
            ->filterTable('clearance_type', 'definitive')
            ->assertCanSeeTableRecords([$definitive])
            ->assertCanNotSeeTableRecords([$percentage]);
    }

    public function test_clearance_type_table_column_and_infolist_render_the_localized_label(): void
    {
        // Regression: the table/infolist used to hardcode '90_percent'/'10_percent' => '90%'/'10%',
        // so a real 'definitive'/'percentage' value always rendered the gray '-' fallback instead.
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['custom.view', 'custom.edit']);
        $custom = Custom::factory()->create(['clearance_type' => 'definitive']);

        Livewire::test(ListCustoms::class)
            ->assertSee('Definitive Clearance')
            ->assertDontSee('90%');

        Livewire::test(EditCustom::class, ['record' => $custom->getRouteKey()])
            ->assertSee('Definitive Clearance');
    }

    public function test_contract_no_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['custom.view']);

        $withA = Custom::factory()->create(['contract_no' => 'CT-FILTER-A']);
        $withB = Custom::factory()->create(['contract_no' => 'CT-FILTER-B']);

        Livewire::test(ListCustoms::class)
            ->filterTable('contract_no', 'CT-FILTER-A')
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_creation_date_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['custom.view']);

        $old = Custom::factory()->create();
        Custom::whereKey($old->id)->update(['created_at' => now()->subDays(30)]);

        $recent = Custom::factory()->create();

        Livewire::test(ListCustoms::class)
            ->filterTable('created_at', [
                'created_from' => now()->subDays(2)->format('Y-m-d'),
                'created_until' => now()->format('Y-m-d'),
            ])
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_trashed_filter_shows_only_soft_deleted_records(): void
    {
        $this->actingAsUserWithPermissions(['custom.view', 'custom.delete']);

        $active = Custom::factory()->create();
        $deleted = Custom::factory()->create();
        $deleted->delete();

        Livewire::test(ListCustoms::class)
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$deleted])
            ->assertCanNotSeeTableRecords([$active]);
    }

    // Create / validation

    public function test_create_requires_shipment(): void
    {
        $this->actingAsUserWithPermissions(['custom.create', 'custom.view']);

        Livewire::test(CreateCustom::class)
            ->fillForm(['shipment_id' => null])
            ->call('create')
            ->assertHasFormErrors(['shipment_id' => 'required']);
    }

    public function test_an_invalid_shipment_shows_the_translated_message_not_the_raw_laravel_one(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['custom.create', 'custom.view']);

        Livewire::test(CreateCustom::class)
            ->fillForm(['shipment_id' => 999999])
            ->call('create')
            ->assertHasFormErrors(['shipment_id' => 'in'])
            ->assertSee(__('resources/custom/strings.form.validation_exists'))
            ->assertDontSee('The selected');
    }

    public function test_an_invalid_registered_order_shows_the_translated_message_not_the_raw_laravel_one(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['custom.create', 'custom.view']);
        $shipment = Shipment::factory()->create();

        Livewire::test(CreateCustom::class)
            ->fillForm([
                'shipment_id' => $shipment->id,
                'registered_order_id' => 999999,
            ])
            ->call('create')
            ->assertHasFormErrors(['registered_order_id' => 'in'])
            ->assertSee(__('resources/custom/strings.form.validation_exists'))
            ->assertDontSee('The selected');
    }

    public function test_an_invalid_clearance_type_shows_the_translated_message_not_the_raw_laravel_one(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['custom.create', 'custom.view']);
        $shipment = Shipment::factory()->create();

        Livewire::test(CreateCustom::class)
            ->fillForm([
                'shipment_id' => $shipment->id,
                'registered_order_id' => $shipment->registered_order_id,
                'clearance_type' => 'totally-bogus-clearance-type',
            ])
            ->call('create')
            ->assertHasFormErrors(['clearance_type' => 'in'])
            ->assertSee(__('resources/custom/strings.form.validation_exists'))
            ->assertDontSee('The selected');
    }

    public function test_creating_from_a_shipment_query_param_prefills_custom_no_shipment_and_clearance_status(): void
    {
        $this->actingAsUserWithPermissions(['custom.create', 'custom.view']);
        Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'english_name' => 'Pending']);
        $shipment = Shipment::factory()->create();

        $instance = Livewire::withQueryParams(['shipment_id' => (string) $shipment->id])
            ->test(CreateCustom::class);

        $this->assertEquals($shipment->id, $instance->get('data.shipment_id'));
        $this->assertEquals($shipment->registered_order_id, $instance->get('data.registered_order_id'));
        $this->assertNotEmpty($instance->get('data.custom_no'));
    }

    // Declaration no. — uniqueness guard (idea 4)

    public function test_declaration_no_unique_validation_rejects_a_duplicate_value(): void
    {
        $this->actingAsUserWithPermissions(['custom.create', 'custom.view']);
        Custom::factory()->create(['declaration_no' => 'DUP-DECL-1']);
        $shipment = Shipment::factory()->create();

        Livewire::test(CreateCustom::class)
            ->fillForm([
                'shipment_id' => $shipment->id,
                'declaration_no' => 'DUP-DECL-1',
            ])
            ->call('create')
            ->assertHasFormErrors(['declaration_no' => 'unique']);
    }

    public function test_declaration_no_unique_validation_allows_reusing_a_soft_deleted_records_value(): void
    {
        $this->actingAsUserWithPermissions(['custom.create', 'custom.view']);
        $deleted = Custom::factory()->create(['declaration_no' => 'DUP-DECL-2']);
        $deleted->delete();
        $shipment = Shipment::factory()->create();

        Livewire::test(CreateCustom::class)
            ->fillForm([
                'shipment_id' => $shipment->id,
                'declaration_no' => 'DUP-DECL-2',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    // Open-exposure flag — percentage clearances still carrying risk (idea 1)

    private function makeCustomWithGuaranteeAndCommitment(string $clearanceType, string $guaranteeName, string $commitmentName): Custom
    {
        $custom = Custom::factory()->create([
            'clearance_type' => $clearanceType,
            'bank_guarantee_status_id' => Status::factory()->create([
                'english_type' => Custom::TYPE_BANK_GUARANTEE_STATUS,
                'english_name' => $guaranteeName,
            ])->id,
            'commitment_status_id' => Status::factory()->create([
                'english_type' => Custom::TYPE_COMMITMENT_STATUS,
                'english_name' => $commitmentName,
            ])->id,
        ]);

        return $custom->load(['bankGuaranteeStatus', 'commitmentStatus']);
    }

    public function test_is_open_exposure_is_false_for_every_guarantee_and_commitment_combination_on_a_definitive_clearance(): void
    {
        foreach ([GuaranteeStatus::Returned, GuaranteeStatus::NotReturned] as $guarantee) {
            foreach ([CommitmentStatus::Completed, CommitmentStatus::NotCompleted] as $commitment) {
                $custom = $this->makeCustomWithGuaranteeAndCommitment('definitive', $guarantee->value, $commitment->value);

                $this->assertFalse(CustomResource::isOpenExposure($custom), "guarantee={$guarantee->value}, commitment={$commitment->value}");
            }
        }
    }

    public function test_is_open_exposure_is_false_only_when_a_percentage_clearance_has_guarantee_returned_and_commitment_completed(): void
    {
        $cases = [
            [GuaranteeStatus::Returned, CommitmentStatus::Completed, false],
            [GuaranteeStatus::Returned, CommitmentStatus::NotCompleted, true],
            [GuaranteeStatus::NotReturned, CommitmentStatus::Completed, true],
            [GuaranteeStatus::NotReturned, CommitmentStatus::NotCompleted, true],
        ];

        foreach ($cases as [$guarantee, $commitment, $expected]) {
            $custom = $this->makeCustomWithGuaranteeAndCommitment('percentage', $guarantee->value, $commitment->value);

            $this->assertSame($expected, CustomResource::isOpenExposure($custom), "guarantee={$guarantee->value}, commitment={$commitment->value}");
        }
    }

    public function test_list_table_shows_the_exposure_flag_badge_only_when_exposure_is_open(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['custom.view']);

        $flagged = $this->makeCustomWithGuaranteeAndCommitment('percentage', GuaranteeStatus::NotReturned->value, CommitmentStatus::NotCompleted->value);
        $closed = $this->makeCustomWithGuaranteeAndCommitment('percentage', GuaranteeStatus::Returned->value, CommitmentStatus::Completed->value);

        Livewire::test(ListCustoms::class)
            ->assertTableColumnFormattedStateSet('exposure_flag', __('resources/custom/strings.table.exposure_flag'), $flagged)
            ->assertTableColumnFormattedStateSet('exposure_flag', '', $closed);
    }

    public function test_infolist_exposure_flag_entry_is_visible_only_when_exposure_is_open(): void
    {
        $flagged = $this->makeCustomWithGuaranteeAndCommitment('percentage', GuaranteeStatus::NotReturned->value, CommitmentStatus::NotCompleted->value);
        $closed = $this->makeCustomWithGuaranteeAndCommitment('percentage', GuaranteeStatus::Returned->value, CommitmentStatus::Completed->value);

        $this->assertTrue(CustomResource::viewExposureFlag()->model($flagged)->isVisible());
        $this->assertFalse(CustomResource::viewExposureFlag()->model($closed)->isVisible());
    }

    public function test_the_three_status_workflow_progress_columns_have_distinct_labels(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['custom.view']);

        $columns = Livewire::test(ListCustoms::class)->instance()->getTable()->getColumns();

        $clearance = collect($columns)->first(fn ($c) => $c->getName() === 'clearance_status_id_progress');
        $guarantee = collect($columns)->first(fn ($c) => $c->getName() === 'bank_guarantee_status_id_progress');
        $commitment = collect($columns)->first(fn ($c) => $c->getName() === 'commitment_status_id_progress');

        $this->assertNotNull($clearance);
        $this->assertNotNull($guarantee);
        $this->assertNotNull($commitment);

        $labels = [$clearance->getLabel(), $guarantee->getLabel(), $commitment->getLabel()];
        $this->assertSame(array_unique($labels), $labels);
        $this->assertSame('Clearance Status Progress', $clearance->getLabel());
        $this->assertSame('Bank Guarantee Status Progress', $guarantee->getLabel());
        $this->assertSame('Commitment Status Progress', $commitment->getLabel());
    }

    // Clearance-aging indicator (idea 2)

    public function test_clearance_aging_color_thresholds_map_to_success_warning_danger_and_gray(): void
    {
        $this->assertSame('gray', CustomResource::clearanceAgingColor(null));
        $this->assertSame('success', CustomResource::clearanceAgingColor(6));
        $this->assertSame('warning', CustomResource::clearanceAgingColor(7));
        $this->assertSame('warning', CustomResource::clearanceAgingColor(14));
        $this->assertSame('danger', CustomResource::clearanceAgingColor(15));
    }

    public function test_list_table_formats_the_clearance_aging_days_column_with_a_days_suffix_or_dash(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['custom.view']);

        $aged = Custom::factory()->create(['doc_submission_date' => '2026-05-01', 'clearance_date' => '2026-05-20']);
        $unsubmitted = Custom::factory()->create(['doc_submission_date' => null]);

        Livewire::test(ListCustoms::class)
            ->assertTableColumnFormattedStateSet('clearance_aging_days', '19 '.__('resources/custom/strings.table.days'), $aged)
            ->assertTableColumnFormattedStateSet('clearance_aging_days', '-', $unsubmitted);
    }

    // Percentage-only date fields — hidden on definitive clearances (idea 3)

    public function test_create_form_only_shows_percentage_only_date_fields_once_clearance_type_is_set_to_percentage(): void
    {
        $this->actingAsUserWithPermissions(['custom.create', 'custom.view']);

        Livewire::test(CreateCustom::class)
            ->assertDontSee(__('resources/custom/strings.form.ten_percent_exit_date'))
            ->assertDontSee(__('resources/custom/strings.form.rial_return_date'))
            ->fillForm(['clearance_type' => 'percentage'])
            ->assertSee(__('resources/custom/strings.form.ten_percent_exit_date'))
            ->assertSee(__('resources/custom/strings.form.rial_return_date'))
            ->fillForm(['clearance_type' => 'definitive'])
            ->assertDontSee(__('resources/custom/strings.form.ten_percent_exit_date'))
            ->assertDontSee(__('resources/custom/strings.form.rial_return_date'));
    }

    public function test_infolist_percentage_only_date_entries_are_visible_only_for_percentage_clearance_records(): void
    {
        $percentage = Custom::factory()->create(['clearance_type' => 'percentage']);
        $definitive = Custom::factory()->create(['clearance_type' => 'definitive']);

        $this->assertTrue(CustomResource::viewTenPercentExitDate()->model($percentage)->isVisible());
        $this->assertTrue(CustomResource::viewRialReturnDate()->model($percentage)->isVisible());
        $this->assertFalse(CustomResource::viewTenPercentExitDate()->model($definitive)->isVisible());
        $this->assertFalse(CustomResource::viewRialReturnDate()->model($definitive)->isVisible());
    }

    // Bulk / soft delete

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['custom.view', 'custom.delete', 'custom.restore']);
        $record = Custom::factory()->create();

        Livewire::test(ListCustoms::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Custom::find($record->id));
        $this->assertTrue(Custom::withTrashed()->find($record->id)->trashed());

        Livewire::test(ListCustoms::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(Custom::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['custom.view', 'custom.delete']);
        $one = Custom::factory()->create();
        $two = Custom::factory()->create();

        Livewire::test(ListCustoms::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(Custom::find($one->id));
        $this->assertNull(Custom::find($two->id));
    }

    public function test_bulk_actions_toolbar_orders_export_before_delete_and_restore(): void
    {
        $this->actingAsUserWithPermissions(['custom.view', 'custom.delete', 'custom.restore']);

        Livewire::test(ListCustoms::class)
            ->assertTableBulkActionsExistInOrder(['exportCustoms', 'delete', 'restore']);
    }

    // Global search contract

    public function test_global_search_title_prefers_declaration_no_then_falls_back_to_custom_no(): void
    {
        $withDeclaration = Custom::factory()->create(['declaration_no' => 'DEC-TITLE-1']);
        $this->assertSame('🛃 DEC-TITLE-1', CustomResource::getGlobalSearchResultTitle($withDeclaration));

        $withoutDeclaration = Custom::factory()->create(['declaration_no' => null]);
        $this->assertSame('🛃 '.$withoutDeclaration->custom_no, CustomResource::getGlobalSearchResultTitle($withoutDeclaration));
    }

    public function test_global_search_result_details_include_contract_no_shipment_and_clearance_status(): void
    {
        app()->setLocale('en');
        // shipment_no is CodeGenerator-mapped and force-regenerated on create — capture the real value.
        $shipment = Shipment::factory()->create();
        $status = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'english_name' => 'Declared']);
        $custom = Custom::factory()->create([
            'contract_no' => 'CT-DETAILS-1',
            'shipment_id' => $shipment->id,
            'clearance_status_id' => $status->id,
        ]);
        $custom->load(['shipment', 'clearanceStatus']);

        $details = CustomResource::getGlobalSearchResultDetails($custom);

        $this->assertSame('CT-DETAILS-1', $details[__('resources/custom/strings.form.contract_no')]);
        $this->assertSame($shipment->shipment_no, $details[__('resources/custom/strings.form.shipment')]);
        $this->assertSame('Declared', $details[__('resources/custom/strings.form.clearance_status')]);
    }

    // Import / Export — flat single-row bulk transfer

    private function importColumnMap(): array
    {
        $names = collect(CustomImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeImporter(array $data, array $options = []): CustomImporter
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $importer = new CustomImporter(new Import, $this->importColumnMap(), array_merge([
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
            'custom_no' => '',
            'shipment_id' => '',
            'declaration_no' => '',
            'clearance_type' => '',
            'commitment_balance' => '',
            'clearance_date' => '',
            'doc_submission_date' => '',
            'ten_percent_exit_date' => '',
            'rial_return_date' => '',
            'clearance_status_id' => '',
            'bank_guarantee_status_id' => '',
            'commitment_status_id' => '',
            'notes' => '',
        ], $overrides);
    }

    public function test_import_creates_a_new_record_and_derives_registered_order_and_contract_no_from_shipment(): void
    {
        $shipment = Shipment::factory()->create(['contract_no' => 'CT-IMPORT-1']);

        $importer = $this->invokeImporter($this->baseImportRow([
            'shipment_id' => $shipment->shipment_no,
        ]));

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertMatchesRegularExpression('/^CU-\d{6}(-\d+)?$/', $record->custom_no);
        $this->assertSame($shipment->id, $record->shipment_id);
        $this->assertSame($shipment->registered_order_id, $record->registered_order_id);
        $this->assertSame('CT-IMPORT-1', $record->contract_no);
    }

    public function test_import_reupload_of_existing_custom_no_updates_in_place(): void
    {
        $shipment = Shipment::factory()->create();

        $row = $this->baseImportRow([
            'shipment_id' => $shipment->shipment_no,
            'notes' => 'First upload',
        ]);

        $first = $this->invokeImporter($row);
        $id = $first->getRecord()->id;

        $row['custom_no'] = $first->getRecord()->custom_no;
        $row['notes'] = 'Second upload';

        $second = $this->invokeImporter($row);

        $this->assertSame($id, $second->getRecord()->id);
        $this->assertSame('Second upload', $second->getRecord()->notes);
    }

    public function test_import_rejects_an_unresolvable_shipment_value(): void
    {
        try {
            $this->invokeImporter($this->baseImportRow([
                'shipment_id' => 'SHP-DOES-NOT-EXIST',
            ]));

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('SHP-DOES-NOT-EXIST', collect($exception->errors())->flatten()->implode(' '));
        }
    }

    public function test_import_rejects_a_blank_shipment_on_a_new_record_cleanly(): void
    {
        app()->setLocale('en');
        $before = Custom::count();

        try {
            $this->invokeImporter($this->baseImportRow([]));

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertSame(__('resources/general/strings.import.required_for_new_record', [
                'label' => __('resources/custom/strings.form.shipment'),
            ]), $exception->getMessage());
        }

        $this->assertSame($before, Custom::count());
    }

    public function test_import_reupload_with_a_blank_shipment_preserves_the_existing_value(): void
    {
        $shipment = Shipment::factory()->create();

        $first = $this->invokeImporter($this->baseImportRow([
            'shipment_id' => $shipment->shipment_no,
        ]));
        $record = $first->getRecord();

        $row = $this->baseImportRow([
            'custom_no' => $record->custom_no,
            'notes' => 'Second upload, shipment left blank',
        ]);

        $second = $this->invokeImporter($row);

        $this->assertSame($record->id, $second->getRecord()->id);
        $this->assertSame($shipment->id, $second->getRecord()->shipment_id);
    }

    public function test_import_new_record_with_unresolvable_clearance_type_and_status_saves_via_null_fallback_and_logs_a_note(): void
    {
        $shipment = Shipment::factory()->create();

        $importer = $this->invokeImporter($this->baseImportRow([
            'shipment_id' => $shipment->shipment_no,
            'clearance_type' => 'Nonexistent Type XYZ',
            'clearance_status_id' => 'Nonexistent Status XYZ',
        ]));

        $record = $importer->getRecord();

        $this->assertNull($record->clearance_type);
        $this->assertNull($record->clearance_status_id);
        $this->assertStringContainsString('Nonexistent Type XYZ', $record->notes);
        $this->assertStringContainsString('Nonexistent Status XYZ', $record->notes);
    }

    public function test_filled_example_file_exists_and_matches_the_import_shape(): void
    {
        $path = CustomImporter::filledExamplePath();

        $this->assertNotNull($path);

        $fullPath = storage_path('app/'.$path);
        $this->assertFileExists($fullPath);

        $contents = file_get_contents($fullPath);

        $this->assertStringContainsString('shipment_id', $contents);
        $this->assertStringContainsString('custom_no', $contents);
    }

    public function test_auto_generated_empty_example_has_a_real_header_for_every_import_column(): void
    {
        foreach (CustomImporter::getColumns() as $column) {
            $this->assertNotSame('', trim($column->getExampleHeader()), "Column [{$column->getName()}] has a blank example header.");
        }
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Pinned counts — a silent column drop during a future refactor must fail this test, not slip through unnoticed.
        $this->assertCount(13, CustomImporter::getColumns());
        $this->assertCount(13, CustomImporter::columnLabels());
        $this->assertCount(23, CustomExporter::columnLabels());
    }

    public function test_filled_example_file_imports_cleanly_end_to_end(): void
    {
        Shipment::factory()->create(['shipment_no' => 'hdm1675wnhs4111b']);
        Shipment::factory()->create(['shipment_no' => 'APS1254NHS5945']);
        Shipment::factory()->create(['shipment_no' => '005122-26']);
        Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'english_name' => 'Documents Submitted']);
        Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'english_name' => 'Declared']);
        Status::factory()->create(['english_type' => Custom::TYPE_BANK_GUARANTEE_STATUS, 'english_name' => 'Not Returned']);
        Status::factory()->create(['english_type' => Custom::TYPE_COMMITMENT_STATUS, 'english_name' => 'Not Completed']);

        $fullPath = storage_path('app/'.CustomImporter::filledExamplePath());
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
        $this->assertSame('definitive', $imported[0]->getRecord()->clearance_type);
        $this->assertSame('Documents Submitted', $imported[0]->getRecord()->clearanceStatus->english_name);
        $this->assertNull($imported[2]->getRecord()->clearance_type);
        $this->assertStringContainsString('NONEXISTENT_TYPE_XYZ', $imported[2]->getRecord()->notes);
    }

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'custom_export_').'.csv';
        CustomExporter::write($query, $path);

        $csv = (string) file_get_contents($path);
        unlink($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", trim(ltrim($csv, "\xEF\xBB\xBF"))))));
        $header = str_getcsv($lines[0]);
        $rows = array_map(fn (string $line) => array_combine($header, str_getcsv($line)), array_slice($lines, 1));

        return ['header' => $header, 'rows' => $rows];
    }

    public function test_exporter_escapes_formula_injection_in_creator_and_updater_names(): void
    {
        app()->setLocale('en');
        $shipment = Shipment::factory()->create();
        $ro = RegisteredOrder::factory()->create();
        $status = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'english_name' => 'CreatorEscapeCheck']);

        $creator = User::factory()->create(['name' => '=1+1']);
        $this->actingAs($creator);
        $record = Custom::factory()->create([
            'shipment_id' => $shipment->id,
            'registered_order_id' => $ro->id,
            'clearance_type' => 'definitive',
            'clearance_status_id' => $status->id,
        ]);

        $updater = User::factory()->create(['name' => '+SUM(1,2)']);
        $this->actingAs($updater);
        $record->update(['notes' => 'triggers updated_by_id']);

        ['rows' => [$row]] = $this->exportToRows(Custom::query()->whereKey($record->id));
        $labels = CustomExporter::columnLabels();

        $this->assertSame("'=1+1", $row[$labels['creator']]);
        $this->assertSame("'+SUM(1,2)", $row[$labels['updater']]);
    }

    public function test_exporter_write_emits_one_parent_row_per_record_with_the_localized_clearance_type(): void
    {
        app()->setLocale('en');
        // shipment_no is CodeGenerator-mapped and force-regenerated on create — capture the real value.
        $shipment = Shipment::factory()->create();
        $ro = RegisteredOrder::factory()->create();
        $status = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'english_name' => 'ExportStatusCheck']);

        $record = Custom::factory()->create([
            'shipment_id' => $shipment->id,
            'registered_order_id' => $ro->id,
            'clearance_type' => 'definitive',
            'clearance_status_id' => $status->id,
            'notes' => null,
        ]);

        ['rows' => [$row]] = $this->exportToRows(Custom::query()->whereKey($record->id));

        $labels = CustomExporter::columnLabels();

        $this->assertSame($record->custom_no, $row[$labels['custom_no']]);
        $this->assertSame($shipment->shipment_no, $row[$labels['shipment_no']]);
        $this->assertSame($ro->ro_number, $row[$labels['registered_order']]);
        $this->assertSame('Definitive Clearance', $row[$labels['clearance_type']]);
        $this->assertSame('ExportStatusCheck', $row[$labels['clearance_status']]);
        $this->assertSame('ExportStatusCheck', $row[$labels['clearance_status_english']]);
    }
}
