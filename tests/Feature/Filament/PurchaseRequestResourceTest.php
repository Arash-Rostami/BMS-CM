<?php

namespace Tests\Feature\Filament;

use App\Filament\Actions\GroupedImportAction;
use App\Filament\Actions\RevertAttachmentAction;
use App\Filament\Actions\SupersedeAttachmentAction;
use App\Filament\Resources\General\FormComponents;
use App\Filament\Resources\Operational\PurchaseRequestResource\Exports\PurchaseRequestExporter;
use App\Filament\Resources\Operational\PurchaseRequestResource\Imports\PurchaseRequestImporter;
use App\Filament\Resources\Operational\PurchaseRequestResource\Imports\PurchaseRequestItemImporter;
use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\CreatePurchaseRequest;
use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\EditPurchaseRequest;
use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\ListPurchaseRequests;
use App\Filament\Resources\Operational\PurchaseRequestResource\RelationManagers\RegisteredOrderRelationManager as PurchaseRequestRegisteredOrderRelationManager;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\HandleStatusMutation;
use App\Filament\Resources\Operational\RegisteredOrderResource\Pages\EditRegisteredOrder;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\PurchaseRequestsRelationManager;
use App\Filament\Resources\PurchaseRequestResource;
use App\Jobs\ImportGroupedCsv;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\Imports\GroupRowFailedException;
use App\Services\SmartCacheManager;
use App\Services\StatusWorkflow;
use Filament\Actions\Imports\Models\Import;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class PurchaseRequestResourceTest extends TestCase
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

    private function prStatus(string $englishName): Status
    {
        return Status::factory()->create([
            'type' => PurchaseRequest::TYPE_PURCHASE_REQUEST,
            'english_type' => PurchaseRequest::TYPE_PURCHASE_REQUEST,
            'name' => $englishName,
            'english_name' => $englishName,
        ]);
    }

    private function realPrStatus(string $englishName): Status
    {
        return Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)
            ->where('english_name', $englishName)
            ->firstOrFail();
    }

    private function prItemStatus(string $englishName): Status
    {
        return Status::factory()->create([
            'type' => PurchaseRequestItem::TYPE_PURCHASE_REQUEST,
            'english_type' => PurchaseRequestItem::TYPE_PURCHASE_REQUEST,
            'name' => $englishName,
            'english_name' => $englishName,
        ]);
    }

    private function repeatableItemComponents(RepeatableEntry $entry): array
    {
        $reflection = new ReflectionProperty($entry, 'childComponents');
        $reflection->setAccessible(true);

        return $reflection->getValue($entry)['default'];
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
            'purchase_request.view',
            'purchase_request.create',
            'purchase_request.edit',
            'purchase_request.delete',
            'purchase_request.restore',
        ]);

        $record = PurchaseRequest::factory()->create();

        $this->assertTrue(PurchaseRequestResource::canViewAny());
        $this->assertTrue(PurchaseRequestResource::canCreate());
        $this->assertTrue(PurchaseRequestResource::canEdit($record));
        $this->assertTrue(PurchaseRequestResource::canDelete($record));
        $this->assertTrue(PurchaseRequestResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = PurchaseRequest::factory()->create();

        $this->assertFalse(PurchaseRequestResource::canViewAny());
        $this->assertFalse(PurchaseRequestResource::canCreate());
        $this->assertFalse(PurchaseRequestResource::canEdit($record));
        $this->assertFalse(PurchaseRequestResource::canDelete($record));
        $this->assertFalse(PurchaseRequestResource::canRestore($record));
    }

    // List — search, filters

    public function test_list_page_renders_and_search_finds_by_pr_number(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $target = PurchaseRequest::factory()->create();
        $other = PurchaseRequest::factory()->create();

        // CodeGeneratingObserver forces same-day pr_numbers to share a date prefix and
        // sequential suffix (other's value can literally contain target's as a substring),
        // so the search term must be assigned directly, bypassing that regeneration.
        PurchaseRequest::whereKey($target->id)->update(['pr_number' => 'PR-SEARCH-TARGET-'.$target->id]);
        PurchaseRequest::whereKey($other->id)->update(['pr_number' => 'PR-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();
        $term = 'TARGET-'.$target->id;

        Livewire::test(ListPurchaseRequests::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_finds_records_by_extra_attribute_key_and_value(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $target = PurchaseRequest::factory()->create();
        $other = PurchaseRequest::factory()->create();
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(ListPurchaseRequests::class)
            ->searchTable('contract_reference')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_with_extra_attributes_search_appends_the_shared_eav_columns(): void
    {
        $this->assertSame(
            ['pr_number', 'rejection_reason', 'extraAttributes.key', 'extraAttributes.value'],
            PurchaseRequestResource::withExtraAttributesSearch(['pr_number', 'rejection_reason'])
        );
    }

    public function test_or_where_extra_attributes_match_filters_by_key_and_value(): void
    {
        $target = PurchaseRequest::factory()->create();
        $other = PurchaseRequest::factory()->create();
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        $byKey = PurchaseRequestResource::orWhereExtraAttributesMatch(
            PurchaseRequest::query()->whereRaw('1 = 0'),
            'contract_reference'
        )->pluck('id')->all();
        $byValue = PurchaseRequestResource::orWhereExtraAttributesMatch(
            PurchaseRequest::query()->whereRaw('1 = 0'),
            'ACME-9981'
        )->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$target->id], $byKey);
        $this->assertEqualsCanonicalizing([$target->id], $byValue);
        $this->assertNotContains($other->id, $byKey);
    }

    public function test_purchase_requests_relation_manager_search_finds_records_by_extra_attribute(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'registered_order.view']);

        $ro = RegisteredOrder::factory()->create();
        $target = PurchaseRequest::factory()->create();
        $other = PurchaseRequest::factory()->create();
        $ro->purchaseRequests()->attach([$target->id, $other->id]);
        $target->syncCustomAttributes(['contract_reference' => 'ACME-9981']);

        Livewire::test(PurchaseRequestsRelationManager::class, [
            'ownerRecord' => $ro,
            'pageClass' => EditRegisteredOrder::class,
        ])
            ->assertSuccessful()
            ->searchTable('ACME-9981')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_department_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $inDeptA = PurchaseRequest::factory()->create(['department_id' => $deptA->id]);
        $inDeptB = PurchaseRequest::factory()->create(['department_id' => $deptB->id]);

        Livewire::test(ListPurchaseRequests::class)
            ->filterTable('department_id', $deptA->id)
            ->assertCanSeeTableRecords([$inDeptA])
            ->assertCanNotSeeTableRecords([$inDeptB]);
    }

    public function test_urgency_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $high = PurchaseRequest::factory()->create(['urgency_level' => 'high']);
        $low = PurchaseRequest::factory()->create(['urgency_level' => 'low']);

        Livewire::test(ListPurchaseRequests::class)
            ->filterTable('urgency_level', 'high')
            ->assertCanSeeTableRecords([$high])
            ->assertCanNotSeeTableRecords([$low]);
    }

    public function test_search_with_no_matches_shows_the_filtered_empty_state_message(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        PurchaseRequest::factory()->create();

        Livewire::test(ListPurchaseRequests::class)
            ->searchTable('zzz-no-such-purchase-request-term-999')
            ->assertSee(__('resources/general/strings.empty_state.filtered_heading'))
            ->assertDontSee(__('resources/general/strings.empty_state.heading'));
    }

    public function test_registered_order_relation_manager_shows_the_gated_empty_state_when_owner_has_none(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'registered_order.view']);

        $pr = PurchaseRequest::factory()->create();

        Livewire::test(PurchaseRequestRegisteredOrderRelationManager::class, [
            'ownerRecord' => $pr,
            'pageClass' => EditPurchaseRequest::class,
        ])
            ->assertSee(__('resources/general/strings.empty_state.gated_heading'));
    }

    public function test_table_uses_the_panel_wide_default_pagination_page_options(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $instance = Livewire::test(ListPurchaseRequests::class);

        $this->assertSame([25, 50, 100], $instance->instance()->getTable()->getPaginationPageOptions());
    }

    // Required-by-date badge

    public function test_required_by_date_shows_overdue_badge_for_a_past_due_open_request(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $status = $this->prStatus('Under Review');
        $record = PurchaseRequest::factory()->create([
            'status_id' => $status->id,
            'required_by_date' => now()->subDay(),
        ]);

        Livewire::test(ListPurchaseRequests::class)
            ->assertTableColumnFormattedStateSet('required_by_date', __('resources/purchaseRequest/strings.table.overdue'), $record);
    }

    public function test_required_by_date_shows_due_soon_badge_within_three_days(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $status = $this->prStatus('Under Review');
        $record = PurchaseRequest::factory()->create([
            'status_id' => $status->id,
            'required_by_date' => now()->addDays(2),
        ]);

        Livewire::test(ListPurchaseRequests::class)
            ->assertTableColumnFormattedStateSet('required_by_date', __('resources/purchaseRequest/strings.table.due_soon'), $record);
    }

    public function test_required_by_date_shows_plain_date_when_status_is_authorized(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $status = $this->prStatus('Authorized');
        $record = PurchaseRequest::factory()->create([
            'status_id' => $status->id,
            'required_by_date' => now()->subDay(),
        ]);

        Livewire::test(ListPurchaseRequests::class)
            ->assertTableColumnFormattedStateSet('required_by_date', adaptiveDate($record->required_by_date), $record);
    }

    public function test_required_by_date_shows_plain_date_when_far_in_the_future(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $status = $this->prStatus('Under Review');
        $record = PurchaseRequest::factory()->create([
            'status_id' => $status->id,
            'required_by_date' => now()->addDays(10),
        ]);

        Livewire::test(ListPurchaseRequests::class)
            ->assertTableColumnFormattedStateSet('required_by_date', adaptiveDate($record->required_by_date), $record);
    }

    // Create

    public function test_create_happy_path_saves_with_server_forced_requester_and_department(): void
    {
        $this->markTestSkipped('fillForm() harness quirk — see tests/testPattern.md §3d. Verified working in real browser QA.');

        $dept = Department::factory()->create();
        $user = $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view']);
        $user->forceFill(['department_id' => $dept->id])->save();

        $status = $this->prStatus('Under Review');
        $itemStatus = $this->prItemStatus('Under Review');
        $costCenter = Department::factory()->create();
        $product = Product::factory()->create();

        Livewire::test(CreatePurchaseRequest::class)
            ->fillForm([
                'status_id' => $status->id,
                'cost_center_id' => $costCenter->id,
                'required_by_date' => now()->addDays(10)->format('Y-m-d'),
                'urgency_level' => 'medium',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'status_id' => $itemStatus->id,
                        'quantity' => 5,
                        'unit' => 'pcs',
                        'estimated_cost' => 20,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $record = PurchaseRequest::where('cost_center_id', $costCenter->id)->firstOrFail();
        $this->assertSame($user->id, $record->requester_id);
        $this->assertSame($dept->id, $record->department_id);
        $this->assertSame(1, $record->items()->count());
    }

    public function test_create_requires_cost_center_and_a_future_required_by_date(): void
    {
        $this->markTestSkipped('fillForm() harness quirk — see tests/testPattern.md §3d. Verified working in real browser QA.');

        $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view']);
        $status = $this->prStatus('Under Review');

        Livewire::test(CreatePurchaseRequest::class)
            ->fillForm([
                'status_id' => $status->id,
                'cost_center_id' => null,
                'required_by_date' => now()->subDay()->format('Y-m-d'),
                'urgency_level' => 'low',
            ])
            ->call('create')
            ->assertHasFormErrors(['cost_center_id' => 'required', 'required_by_date' => 'after']);
    }

    public function test_create_requires_at_least_one_item(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view']);
        $status = $this->prStatus('Under Review');
        $costCenter = Department::factory()->create();

        Livewire::test(CreatePurchaseRequest::class)
            ->fillForm([
                'status_id' => $status->id,
                'cost_center_id' => $costCenter->id,
                'required_by_date' => now()->addDays(5)->format('Y-m-d'),
                'urgency_level' => 'low',
                'items' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['items' => 'min']);
    }

    public function test_create_requires_a_unit_of_measurement_on_every_item(): void
    {
        $dept = Department::factory()->create();
        $user = $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view']);
        $user->forceFill(['department_id' => $dept->id])->save();

        $status = $this->prStatus('Under Review');
        $itemStatus = $this->prItemStatus('Under Review');
        $costCenter = Department::factory()->create();
        $product = Product::factory()->create();

        Livewire::test(CreatePurchaseRequest::class)
            ->fillForm([
                'status_id' => $status->id,
                'cost_center_id' => $costCenter->id,
                'required_by_date' => now()->addDays(10)->format('Y-m-d'),
                'urgency_level' => 'medium',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'status_id' => $itemStatus->id,
                        'quantity' => 5,
                        'unit' => null,
                        'estimated_cost' => 20,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['items.0.unit' => 'required']);
    }

    public function test_declined_status_requires_a_rejection_reason(): void
    {
        $this->markTestSkipped('fillForm() harness quirk — see tests/testPattern.md §3d. Verified working in real browser QA.');

        $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view']);
        $declined = $this->prStatus('Declined');
        $costCenter = Department::factory()->create();

        Livewire::test(CreatePurchaseRequest::class)
            ->fillForm([
                'status_id' => $declined->id,
                'cost_center_id' => $costCenter->id,
                'required_by_date' => now()->addDays(5)->format('Y-m-d'),
                'urgency_level' => 'low',
                'rejection_reason' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['rejection_reason' => 'required']);
    }

    public function test_declined_status_with_a_rejection_reason_saves_successfully(): void
    {
        $this->markTestSkipped('fillForm() harness quirk — see tests/testPattern.md §3d. Verified working in real browser QA.');

        $dept = Department::factory()->create();
        $user = $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view']);
        $user->forceFill(['department_id' => $dept->id])->save();

        $declined = $this->prStatus('Declined');
        $itemStatus = $this->prItemStatus('Under Review');
        $costCenter = Department::factory()->create();
        $product = Product::factory()->create();

        Livewire::test(CreatePurchaseRequest::class)
            ->fillForm([
                'status_id' => $declined->id,
                'cost_center_id' => $costCenter->id,
                'required_by_date' => now()->addDays(5)->format('Y-m-d'),
                'urgency_level' => 'low',
                'rejection_reason' => 'Budget exceeded',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'status_id' => $itemStatus->id,
                        'quantity' => 1,
                        'unit' => 'pcs',
                        'estimated_cost' => 10,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $record = PurchaseRequest::where('cost_center_id', $costCenter->id)->firstOrFail();
        $this->assertSame('Budget exceeded', $record->rejection_reason);
    }

    public function test_creating_a_second_recent_request_for_the_same_cost_center_warns_but_does_not_block(): void
    {
        $this->markTestSkipped('fillForm() harness quirk — see tests/testPattern.md §3d. Verified working in real browser QA.');

        $dept = Department::factory()->create();
        $user = $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view']);
        $user->forceFill(['department_id' => $dept->id])->save();

        $status = $this->prStatus('Under Review');
        $itemStatus = $this->prItemStatus('Under Review');
        $costCenter = Department::factory()->create();
        $product = Product::factory()->create();

        $formData = fn () => [
            'status_id' => $status->id,
            'cost_center_id' => $costCenter->id,
            'required_by_date' => now()->addDays(5)->format('Y-m-d'),
            'urgency_level' => 'low',
            'items' => [
                [
                    'product_id' => $product->id,
                    'status_id' => $itemStatus->id,
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'estimated_cost' => 10,
                ],
            ],
        ];

        Livewire::test(CreatePurchaseRequest::class)
            ->fillForm($formData())
            ->call('create')
            ->assertHasNoErrors();

        Livewire::test(CreatePurchaseRequest::class)
            ->fillForm($formData())
            ->call('create')
            ->assertHasNoErrors()
            ->assertNotified(__('resources/purchaseRequest/strings.notifications.duplicate_title'));

        $this->assertSame(2, PurchaseRequest::where('cost_center_id', $costCenter->id)->count());
    }

    // Edit

    public function test_edit_page_loads_existing_values_and_persists_an_update(): void
    {
        $this->markTestSkipped('fillForm() harness quirk — see tests/testPattern.md §3d. Verified working in real browser QA.');

        $this->actingAsUserWithPermissions(['purchase_request.edit', 'purchase_request.view']);
        $status = $this->prStatus('Under Review');
        $record = PurchaseRequest::factory()->create([
            'status_id' => $status->id,
            'cost_center_id' => Department::factory()->create()->id,
            'urgency_level' => 'low',
        ]);
        PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $record->id,
            'status_id' => $this->prItemStatus('Under Review')->id,
            'unit' => 'pcs',
        ]);

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->assertFormSet(['urgency_level' => 'low'])
            ->fillForm(['urgency_level' => 'high'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('high', $record->fresh()->urgency_level);
    }

    public function test_editing_unrelated_field_does_not_block_saving_a_pre_existing_item_with_a_null_unit(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.edit', 'purchase_request.view']);
        $status = $this->prStatus('Under Review');
        $record = PurchaseRequest::factory()->create([
            'status_id' => $status->id,
            'cost_center_id' => Department::factory()->create()->id,
            'urgency_level' => 'low',
        ]);
        PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $record->id,
            'status_id' => $this->prItemStatus('Under Review')->id,
            'unit' => null,
        ]);

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->set('data.urgency_level', 'high')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('high', $record->fresh()->urgency_level);
    }

    // Status workflow — auto-set on create, gated forward transitions, return-for-revision, resubmit

    public function test_create_page_auto_sets_the_workflow_initial_status(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view']);
        $initial = StatusWorkflow::initialFor(PurchaseRequest::TYPE_PURCHASE_REQUEST);

        $page = new CreatePurchaseRequest;
        $method = new ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $data = $method->invoke($page, [
            'cost_center_id' => Department::factory()->create()->id,
            'urgency_level' => 'low',
            'required_by_date' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $this->assertSame($initial->id, $data['status_id']);
    }

    private function editableRecordWithStatus(Status $status): PurchaseRequest
    {
        $record = PurchaseRequest::factory()->create([
            'status_id' => $status->id,
            'cost_center_id' => Department::factory()->create()->id,
        ]);
        PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $record->id,
            'status_id' => $this->prItemStatus('Under Review')->id,
            'unit' => 'pcs',
        ]);

        return $record;
    }

    public function test_status_field_locked_helper_text_names_the_actual_granted_approvers(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.edit', 'purchase_request.view']);
        $underReview = $this->realPrStatus('Under Review');
        $salesManagerApproval = $this->realPrStatus('Sales Manager Approval');
        $record = $this->editableRecordWithStatus($underReview);

        $permission = Permission::firstOrCreate(['name' => $salesManagerApproval->approval_permission, 'guard_name' => 'web']);
        $permission->users()->sync([]);
        $approver = User::factory()->create(['name' => 'Jane Approver']);
        $approver->givePermissionTo($permission);

        $method = new ReflectionMethod(PurchaseRequestResource::class, 'statusWorkflowLockedHelperText');
        $method->setAccessible(true);

        $this->assertSame(
            __('resources/general/strings.status_workflow.locked_helper_with_names', [
                'status' => $salesManagerApproval->getLocalizedNameAttribute(),
                'names' => 'Jane Approver',
            ]),
            $method->invoke(null, 'status_id', $record->fresh())
        );
    }

    public function test_status_field_locked_helper_text_falls_back_to_generic_wording_without_named_approvers(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.edit', 'purchase_request.view']);
        $underReview = $this->realPrStatus('Under Review');
        $salesManagerApproval = $this->realPrStatus('Sales Manager Approval');
        $record = $this->editableRecordWithStatus($underReview);

        Permission::firstOrCreate(['name' => $salesManagerApproval->approval_permission, 'guard_name' => 'web'])->users()->sync([]);

        $method = new ReflectionMethod(PurchaseRequestResource::class, 'statusWorkflowLockedHelperText');
        $method->setAccessible(true);

        $this->assertSame(
            __('resources/general/strings.status_workflow.locked_helper', ['status' => $salesManagerApproval->getLocalizedNameAttribute()]),
            $method->invoke(null, 'status_id', $record->fresh())
        );
    }

    public function test_status_field_locked_helper_text_falls_back_to_generic_wording_beyond_three_named_approvers(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.edit', 'purchase_request.view']);
        $underReview = $this->realPrStatus('Under Review');
        $salesManagerApproval = $this->realPrStatus('Sales Manager Approval');
        $record = $this->editableRecordWithStatus($underReview);

        $permission = Permission::firstOrCreate(['name' => $salesManagerApproval->approval_permission, 'guard_name' => 'web']);
        $permission->users()->sync([]);
        foreach (range(1, 4) as $i) {
            User::factory()->create(['name' => "Approver {$i}"])->givePermissionTo($permission);
        }

        $method = new ReflectionMethod(PurchaseRequestResource::class, 'statusWorkflowLockedHelperText');
        $method->setAccessible(true);

        $this->assertSame(
            __('resources/general/strings.status_workflow.locked_helper', ['status' => $salesManagerApproval->getLocalizedNameAttribute()]),
            $method->invoke(null, 'status_id', $record->fresh())
        );
    }

    public function test_status_workflow_pipeline_data_lists_the_ordered_stages_with_icons_and_responsible_parties(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['purchase_request.edit', 'purchase_request.view']);
        $submitted = $this->realPrStatus('Submitted');
        $underReview = $this->realPrStatus('Under Review');
        $salesManagerApproval = $this->realPrStatus('Sales Manager Approval');
        $authorized = $this->realPrStatus('Authorized');

        Permission::firstOrCreate(['name' => $salesManagerApproval->approval_permission, 'guard_name' => 'web'])
            ->users()->sync([]);
        $approver = User::factory()->create(['name' => 'Jane Approver']);
        $approver->givePermissionTo(Permission::firstOrCreate(['name' => $salesManagerApproval->approval_permission, 'guard_name' => 'web']));

        Permission::firstOrCreate(['name' => $authorized->approval_permission, 'guard_name' => 'web'])
            ->users()->sync([]);

        $record = $this->editableRecordWithStatus($underReview);

        $method = new ReflectionMethod(PurchaseRequestResource::class, 'statusWorkflowPipelineData');
        $method->setAccessible(true);

        $data = $method->invoke(null, $record->fresh());

        $this->assertSame([
            ['icon' => '✅', 'order' => 1, 'name' => $submitted->getLocalizedNameAttribute(), 'responsible' => __('resources/general/strings.status_workflow.pipeline_automatic')],
            ['icon' => '✅', 'order' => 2, 'name' => $underReview->getLocalizedNameAttribute(), 'responsible' => __('resources/general/strings.status_workflow.pipeline_anyone')],
            ['icon' => '🔒', 'order' => 3, 'name' => $salesManagerApproval->getLocalizedNameAttribute(), 'responsible' => __('resources/general/strings.status_workflow.pipeline_requires', ['names' => 'Jane Approver'])],
            ['icon' => '🔒', 'order' => 4, 'name' => $authorized->getLocalizedNameAttribute(), 'responsible' => __('resources/general/strings.status_workflow.pipeline_requires_generic')],
        ], $data['ordered']);

        $this->assertSame([
            ['icon' => '🔓', 'name' => $this->realPrStatus('Conditional')->getLocalizedNameAttribute(), 'available_text' => __('resources/general/strings.status_workflow.pipeline_available_anytime')],
            ['icon' => '🔓', 'name' => $this->realPrStatus('Declined')->getLocalizedNameAttribute(), 'available_text' => __('resources/general/strings.status_workflow.pipeline_available_anytime')],
        ], $data['unordered']);
    }

    public function test_status_workflow_pipeline_data_carries_localized_names_and_responsible_text_for_fa(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['purchase_request.edit', 'purchase_request.view']);
        $underReview = $this->realPrStatus('Under Review');
        $salesManagerApproval = $this->realPrStatus('Sales Manager Approval');

        Permission::firstOrCreate(['name' => $salesManagerApproval->approval_permission, 'guard_name' => 'web'])
            ->users()->sync([]);
        $approver = User::factory()->create(['name' => 'Jane Approver']);
        $approver->givePermissionTo(Permission::firstOrCreate(['name' => $salesManagerApproval->approval_permission, 'guard_name' => 'web']));

        $record = $this->editableRecordWithStatus($underReview);

        $method = new ReflectionMethod(PurchaseRequestResource::class, 'statusWorkflowPipelineData');
        $method->setAccessible(true);

        $data = $method->invoke(null, $record->fresh());
        $salesManagerLine = $data['ordered'][2];

        $this->assertSame($salesManagerApproval->getLocalizedNameAttribute(), $salesManagerLine['name']);
        $this->assertSame(__('resources/general/strings.status_workflow.pipeline_requires', ['names' => 'Jane Approver']), $salesManagerLine['responsible']);
        $this->assertStringContainsString('Jane Approver', $salesManagerLine['responsible']);
    }

    public function test_status_workflow_pipeline_data_treats_a_new_record_as_stage_one_and_still_lists_unordered_statuses(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view']);
        $submitted = $this->realPrStatus('Submitted');
        $underReview = $this->realPrStatus('Under Review');

        $method = new ReflectionMethod(PurchaseRequestResource::class, 'statusWorkflowPipelineData');
        $method->setAccessible(true);

        $data = $method->invoke(null, null);

        $this->assertSame('', $data['ordered'][0]['icon']);
        $this->assertSame($submitted->getLocalizedNameAttribute(), $data['ordered'][0]['name']);
        $this->assertSame('🔒', $data['ordered'][1]['icon']);
        $this->assertSame($underReview->getLocalizedNameAttribute(), $data['ordered'][1]['name']);
        $this->assertContains(
            ['icon' => '🔓', 'name' => $this->realPrStatus('Conditional')->getLocalizedNameAttribute(), 'available_text' => __('resources/general/strings.status_workflow.pipeline_available_anytime')],
            $data['unordered']
        );
        $this->assertContains(
            ['icon' => '🔓', 'name' => $this->realPrStatus('Declined')->getLocalizedNameAttribute(), 'available_text' => __('resources/general/strings.status_workflow.pipeline_available_anytime')],
            $data['unordered']
        );
    }

    public function test_list_and_edit_pages_expose_the_status_workflow_pipeline_header_action(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view', 'purchase_request.edit']);
        $record = PurchaseRequest::factory()->create();

        Livewire::test(ListPurchaseRequests::class)
            ->assertActionExists('statusWorkflowPipeline');

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->assertActionExists('statusWorkflowPipeline');
    }

    public function test_edit_form_shows_the_attachment_status_repeater_with_supersede_and_revert_actions(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.edit', 'purchase_request.view']);
        $record = PurchaseRequest::factory()->create();
        $uploaded = Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_UPLOADED);
        Attachment::factory()->forAttachable($record)->create(['status_id' => $uploaded->id]);

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->assertSee(__('resources/general/strings.attachments.mark_superseded'));
    }

    public function test_create_form_does_not_show_the_attachment_status_repeater_before_a_record_exists(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.create']);

        Livewire::test(CreatePurchaseRequest::class)
            ->assertDontSee(__('resources/general/strings.attachments.mark_superseded'));
    }

    public function test_editing_status_forward_without_the_gating_permission_is_rejected_server_side(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.edit', 'purchase_request.view']);
        $underReview = $this->realPrStatus('Under Review');
        $salesManagerApproval = $this->realPrStatus('Sales Manager Approval');
        $record = $this->editableRecordWithStatus($underReview);

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->set('data.status_id', $salesManagerApproval->id)
            ->call('save')
            ->assertHasFormErrors(['status_id']);

        $this->assertSame('Under Review', $record->fresh()->status->english_name);
    }

    public function test_editing_status_forward_with_the_gating_permission_succeeds(): void
    {
        $this->actingAsUserWithPermissions([
            'purchase_request.edit', 'purchase_request.view',
            'status.grant_purchase_request_sales_manager_approval',
        ]);
        $underReview = $this->realPrStatus('Under Review');
        $salesManagerApproval = $this->realPrStatus('Sales Manager Approval');
        $record = $this->editableRecordWithStatus($underReview);

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->set('data.status_id', $salesManagerApproval->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Sales Manager Approval', $record->fresh()->status->english_name);
    }

    public function test_editing_status_skipping_a_stage_is_rejected_even_with_permission(): void
    {
        $this->actingAsUserWithPermissions([
            'purchase_request.edit', 'purchase_request.view',
            'status.grant_purchase_request_sales_manager_approval',
            'status.grant_purchase_request_commercial_manager_approval',
        ]);
        $underReview = $this->realPrStatus('Under Review');
        $authorized = $this->realPrStatus('Authorized');
        $record = $this->editableRecordWithStatus($underReview);

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->set('data.status_id', $authorized->id)
            ->call('save')
            ->assertHasFormErrors(['status_id']);

        $this->assertSame('Under Review', $record->fresh()->status->english_name);
    }

    public function test_server_side_transition_guard_rejects_a_skipped_stage_independently_of_the_select_options(): void
    {
        $this->actingAsUserWithPermissions([
            'status.grant_purchase_request_sales_manager_approval',
            'status.grant_purchase_request_commercial_manager_approval',
        ]);
        $underReview = $this->realPrStatus('Under Review');
        $authorized = $this->realPrStatus('Authorized');
        $record = $this->editableRecordWithStatus($underReview);

        $mutator = new class
        {
            use HandleStatusMutation;

            public function run(array $data, ?PurchaseRequest $record): array
            {
                return $this->mutateStatusData($data, $record);
            }
        };

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $mutator->run(['status_id' => $authorized->id], $record);
    }

    // Attachment lifecycle — Uploaded / Superseded / Archived

    public function test_transitioning_to_the_terminal_status_archives_attachments_in_a_single_query_and_hides_delete(): void
    {
        $authorized = $this->realPrStatus('Authorized');
        $this->actingAsUserWithPermissions([
            'purchase_request.edit', 'purchase_request.view',
            $authorized->approval_permission,
        ]);
        $salesManagerApproval = $this->realPrStatus('Sales Manager Approval');
        $record = $this->editableRecordWithStatus($salesManagerApproval);

        $uploaded = Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_UPLOADED);
        $attachment = Attachment::factory()->forAttachable($record)->create(['status_id' => $uploaded->id]);

        DB::enableQueryLog();

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->set('data.status_id', $authorized->id)
            ->call('save')
            ->assertHasNoErrors();

        $archiveQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'update `attachments`') && str_contains($q['query'], 'status_id'));
        DB::disableQueryLog();

        $this->assertCount(1, $archiveQueries);
        $this->assertSame('Authorized', $record->fresh()->status->english_name);
        $this->assertSame('Archived', $attachment->fresh()->status->english_name);

        $field = FormComponents::getAttachmentsField()->model($record->fresh());
        $this->assertFalse($field->isDeletable());
    }

    public function test_delete_stays_visible_for_a_non_terminal_record(): void
    {
        $underReview = $this->realPrStatus('Under Review');
        $record = $this->editableRecordWithStatus($underReview);

        $field = FormComponents::getAttachmentsField()->model($record);

        $this->assertTrue($field->isDeletable());
    }

    public function test_mark_as_superseded_and_revert_actions_transition_the_attachment_status(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'purchase_request.edit']);

        $uploaded = Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_UPLOADED);
        $archived = Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_ARCHIVED);
        $record = PurchaseRequest::factory()->create();
        $attachment = Attachment::factory()->forAttachable($record)->create(['status_id' => $uploaded->id]);

        $supersede = SupersedeAttachmentAction::make();
        $revert = RevertAttachmentAction::make();

        $this->assertTrue($supersede->record($attachment)->isVisible());
        $this->assertFalse($revert->record($attachment)->isVisible());

        $supersede->record($attachment)->call();
        $attachment->refresh();
        $this->assertSame('Superseded', $attachment->status->english_name);

        $attachment->update(['status_id' => $archived->id]);
        $attachment->refresh();

        $this->assertFalse($supersede->record($attachment)->isVisible());
        $this->assertTrue($revert->record($attachment)->isVisible());

        $revert->record($attachment)->call();
        $this->assertSame('Uploaded', $attachment->fresh()->status->english_name);
    }

    public function test_supersede_and_revert_actions_require_edit_permission_on_the_attachable_resource(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $uploaded = Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_UPLOADED);
        $archived = Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_ARCHIVED);
        $record = PurchaseRequest::factory()->create();
        $uploadedAttachment = Attachment::factory()->forAttachable($record)->create(['status_id' => $uploaded->id]);
        $archivedAttachment = Attachment::factory()->forAttachable($record)->create(['status_id' => $archived->id]);

        $this->assertFalse(SupersedeAttachmentAction::make()->record($uploadedAttachment)->isVisible());
        $this->assertFalse(RevertAttachmentAction::make()->record($archivedAttachment)->isVisible());

        $this->actingAsUserWithPermissions(['purchase_request.view', 'purchase_request.edit']);

        $this->assertTrue(SupersedeAttachmentAction::make()->record($uploadedAttachment)->isVisible());
        $this->assertTrue(RevertAttachmentAction::make()->record($archivedAttachment)->isVisible());
    }

    public function test_attachments_infolist_entry_wires_the_supersede_and_revert_actions(): void
    {
        $entry = collect($this->repeatableItemComponents(PurchaseRequestResource::viewAttachments()))
            ->first(fn ($component) => $component->getName() === 'status.name');

        $reflection = new ReflectionProperty($entry, 'suffixActions');
        $reflection->setAccessible(true);

        $names = collect($reflection->getValue($entry))->map(fn ($action) => $action->getName())->all();

        $this->assertSame(['supersedeAttachment', 'revertAttachment'], $names);
    }

    public function test_return_for_revision_requires_a_reason(): void
    {
        $this->actingAsUserWithPermissions([
            'purchase_request.edit', 'purchase_request.view',
            'status.grant_purchase_request_sales_manager_approval',
        ]);
        $salesManagerApproval = $this->realPrStatus('Sales Manager Approval');
        $record = PurchaseRequest::factory()->create(['status_id' => $salesManagerApproval->id]);

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->callAction('returnForRevision', data: ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        $this->assertSame('Sales Manager Approval', $record->fresh()->status->english_name);
    }

    public function test_return_for_revision_resets_status_to_stage_one_and_persists_the_reason(): void
    {
        $this->actingAsUserWithPermissions([
            'purchase_request.edit', 'purchase_request.view',
            'status.grant_purchase_request_sales_manager_approval',
        ]);
        $initial = StatusWorkflow::initialFor(PurchaseRequest::TYPE_PURCHASE_REQUEST);
        $salesManagerApproval = $this->realPrStatus('Sales Manager Approval');
        $record = PurchaseRequest::factory()->create(['status_id' => $salesManagerApproval->id]);

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->callAction('returnForRevision', data: ['reason' => 'Missing budget approval documents'])
            ->assertHasNoActionErrors();

        $record->refresh();
        $this->assertSame($initial->id, $record->status_id);

        $history = $record->statusHistories()->latest('id')->first();
        $this->assertSame('Missing budget approval documents', $history->reason);
    }

    public function test_return_for_revision_is_hidden_without_the_current_status_approval_permission(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.edit', 'purchase_request.view']);
        $salesManagerApproval = $this->realPrStatus('Sales Manager Approval');
        $record = PurchaseRequest::factory()->create(['status_id' => $salesManagerApproval->id]);

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->assertActionHidden('returnForRevision');
    }

    public function test_resubmit_resets_status_to_stage_one_for_the_original_creator(): void
    {
        $creator = $this->actingAsUserWithPermissions(['purchase_request.edit', 'purchase_request.view']);
        $initial = StatusWorkflow::initialFor(PurchaseRequest::TYPE_PURCHASE_REQUEST);
        $declined = $this->realPrStatus('Declined');
        $record = PurchaseRequest::factory()->create([
            'status_id' => $declined->id,
            'user_id' => $creator->id,
            'rejection_reason' => 'Budget exceeded',
        ]);

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->callAction('resubmit');

        $this->assertSame($initial->id, $record->fresh()->status_id);
    }

    public function test_resubmit_is_hidden_for_a_different_user(): void
    {
        $creator = User::factory()->create();
        $this->actingAsUserWithPermissions(['purchase_request.edit', 'purchase_request.view']);
        $declined = $this->realPrStatus('Declined');
        $record = PurchaseRequest::factory()->create(['status_id' => $declined->id]);
        // UserStamps::bootUserStamps() forces user_id to the acting user on create,
        // so the intended creator must be assigned via a raw update afterwards.
        PurchaseRequest::whereKey($record->id)->update(['user_id' => $creator->id]);
        $record->refresh();

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->assertActionHidden('resubmit');
    }

    public function test_resubmit_is_hidden_when_status_is_not_conditional_or_declined(): void
    {
        $creator = $this->actingAsUserWithPermissions(['purchase_request.edit', 'purchase_request.view']);
        $underReview = $this->realPrStatus('Under Review');
        $record = PurchaseRequest::factory()->create(['status_id' => $underReview->id, 'user_id' => $creator->id]);

        Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])
            ->assertActionHidden('resubmit');
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'purchase_request.delete', 'purchase_request.restore']);
        $record = PurchaseRequest::factory()->create();

        Livewire::test(ListPurchaseRequests::class)
            ->callTableAction('delete', $record);

        $this->assertNull(PurchaseRequest::find($record->id));
        $this->assertTrue(PurchaseRequest::withTrashed()->find($record->id)->trashed());

        Livewire::test(ListPurchaseRequests::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(PurchaseRequest::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'purchase_request.delete']);
        $one = PurchaseRequest::factory()->create();
        $two = PurchaseRequest::factory()->create();

        Livewire::test(ListPurchaseRequests::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(PurchaseRequest::find($one->id));
        $this->assertNull(PurchaseRequest::find($two->id));
    }

    // Import / Export — merged single-file grouped bulk transfer

    private function mergedColumnMap(): array
    {
        $names = collect(PurchaseRequestImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    private function invokeMergedImporter(array $parentData, array $itemRows = [], array $options = []): PurchaseRequestImporter
    {
        $importer = new PurchaseRequestImporter(new Import, $this->mergedColumnMap(), array_merge([
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
        $import->importer = PurchaseRequestImporter::class;
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
            ->itemOnlyColumns(['quantity', 'unit', 'estimated_cost', 'item_status_id', 'item_notes']);
    }

    public function test_import_end_to_end_creates_parent_with_items_via_after_save_hook(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();
        $costCenter = Department::factory()->create();
        $productOne = Product::factory()->create();
        $productTwo = Product::factory()->create();
        $status = $this->prStatus('ImportGroupUnderReview');
        $itemStatus = $this->prItemStatus('ImportGroupItemPending');

        $importer = $this->invokeMergedImporter([
            'pr_number' => '',
            'requester_id' => $requester->email,
            'department_id' => $department->name,
            'cost_center_id' => $costCenter->name,
            'required_by_date' => '2026-12-31',
            'urgency_level' => 'High',
            'total_estimated_cost' => '1000.00',
            'status_id' => $status->english_name,
            'notes' => 'Imported parent row',
        ], [
            ['product_id' => $productOne->code, 'quantity' => '5', 'unit' => 'kg', 'estimated_cost' => '100', 'item_status_id' => $itemStatus->english_name, 'item_notes' => 'First item'],
            ['product_id' => $productTwo->code, 'quantity' => '2', 'unit' => 'pcs', 'estimated_cost' => '20', 'item_status_id' => $itemStatus->english_name, 'item_notes' => 'Second item'],
        ]);

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertNotEmpty($record->pr_number);
        $this->assertMatchesRegularExpression('/^PR-\d{6}(-\d+)?$/', $record->pr_number);
        $this->assertSame(2, $record->items()->count());
        $this->assertSame(1, $record->items()->where('product_id', $productOne->id)->count());
        $this->assertSame(1, $record->items()->where('product_id', $productTwo->id)->count());
    }

    public function test_import_parent_with_zero_items_saves_cleanly(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();
        $status = $this->prStatus('ImportZeroItemStatus');

        $importer = $this->invokeMergedImporter([
            'pr_number' => '',
            'requester_id' => $requester->email,
            'department_id' => $department->name,
            'cost_center_id' => $department->name,
            'required_by_date' => '2026-12-31',
            'urgency_level' => 'low',
            'total_estimated_cost' => '0',
            'status_id' => $status->english_name,
            'notes' => '',
        ]);

        $record = $importer->getRecord();

        $this->assertNotNull($record);
        $this->assertSame(0, $record->items()->count());
    }

    public function test_import_duplicate_product_within_same_group_is_rejected(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();
        $status = $this->prStatus('ImportDupProductStatus');
        $product = Product::factory()->create();

        $countBefore = PurchaseRequest::withTrashed()->count();

        try {
            $this->invokeMergedImporter([
                'pr_number' => '',
                'requester_id' => $requester->email,
                'department_id' => $department->name,
                'cost_center_id' => $department->name,
                'required_by_date' => '2026-12-31',
                'urgency_level' => 'low',
                'total_estimated_cost' => '10',
                'status_id' => $status->english_name,
                'notes' => '',
            ], [
                ['product_id' => $product->code, 'quantity' => '1', 'unit' => 'kg', 'estimated_cost' => '1', 'item_status_id' => '', 'item_notes' => ''],
                ['product_id' => $product->code, 'quantity' => '2', 'unit' => 'kg', 'estimated_cost' => '2', 'item_status_id' => '', 'item_notes' => ''],
            ]);

            $this->fail('Expected GroupRowFailedException was not thrown.');
        } catch (GroupRowFailedException) {
            $this->assertSame($countBefore, PurchaseRequest::withTrashed()->count(), 'A failed item row must roll back the parent record too — no orphan purchase request should be persisted.');
        }
    }

    public function test_import_reupload_of_existing_pr_number_updates_in_place(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();
        $costCenter = Department::factory()->create();
        $product = Product::factory()->create();
        $status = $this->prStatus('ImportReuploadStatus');
        $itemStatus = $this->prItemStatus('ImportReuploadItemStatus');

        $importer = $this->invokeMergedImporter([
            'pr_number' => '',
            'requester_id' => $requester->email,
            'department_id' => $department->name,
            'cost_center_id' => $costCenter->name,
            'required_by_date' => '2026-12-31',
            'urgency_level' => 'low',
            'total_estimated_cost' => '10',
            'status_id' => $status->english_name,
            'notes' => 'Original',
        ], [
            ['product_id' => $product->code, 'quantity' => '1', 'unit' => 'kg', 'estimated_cost' => '1', 'item_status_id' => $itemStatus->english_name, 'item_notes' => ''],
        ]);

        $record = $importer->getRecord();
        $newDepartment = Department::factory()->create();

        $this->invokeMergedImporter([
            'pr_number' => $record->pr_number,
            'requester_id' => $requester->email,
            'department_id' => $newDepartment->name,
            'cost_center_id' => $costCenter->name,
            'required_by_date' => '2026-12-31',
            'urgency_level' => 'high',
            'total_estimated_cost' => '20',
            'status_id' => $status->english_name,
            'notes' => 'Updated via re-upload',
        ], [
            ['product_id' => $product->code, 'quantity' => '9', 'unit' => 'kg', 'estimated_cost' => '9', 'item_status_id' => $itemStatus->english_name, 'item_notes' => 'Updated item'],
        ]);

        $this->assertSame(1, PurchaseRequest::withTrashed()->where('pr_number', $record->pr_number)->count());

        $record->refresh();
        $this->assertSame($newDepartment->id, $record->department_id);
        $this->assertSame('high', $record->urgency_level);
        $this->assertSame('Updated via re-upload', $record->notes);
        $this->assertSame(1, $record->items()->count());
        $this->assertSame('9.00000', (string) $record->items()->first()->quantity);
    }

    public function test_import_trashed_parent_match_refuses_cleanly(): void
    {
        $record = PurchaseRequest::factory()->create();
        $record->delete();

        $requester = User::factory()->create();
        $department = Department::factory()->create();

        try {
            $this->invokeMergedImporter([
                'pr_number' => $record->pr_number,
                'requester_id' => $requester->email,
                'department_id' => $department->name,
                'cost_center_id' => $department->name,
                'required_by_date' => '2026-12-31',
                'urgency_level' => 'low',
                'total_estimated_cost' => '10',
                'status_id' => null,
                'notes' => null,
            ]);

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertStringContainsString($record->pr_number, $exception->getMessage());
        }
    }

    public function test_import_columns_are_labeled_with_the_db_column_name_in_parentheses(): void
    {
        $labels = collect(PurchaseRequestImporter::getColumns())->mapWithKeys(fn ($column) => [$column->getName() => $column->getLabel()]);

        $this->assertStringEndsWith('(product_id)', $labels['product_id']);
        $this->assertStringEndsWith('(status_id)', $labels['status_id']);
        $this->assertStringEndsWith('(department_id)', $labels['department_id']);
        $this->assertStringEndsWith('(urgency_level)', $labels['urgency_level']);
    }

    public function test_import_example_headers_match_labels_and_exclude_system_only_columns(): void
    {
        $columns = collect(PurchaseRequestImporter::getColumns())->keyBy(fn ($column) => $column->getName());

        $this->assertSame($columns['product_id']->getLabel(), $columns['product_id']->getExampleHeader());
        $this->assertSame($columns['status_id']->getLabel(), $columns['status_id']->getExampleHeader());

        $excluded = PurchaseRequestImporter::autoFilledColumnNames();
        $this->assertSame(['requester_id', 'department_id'], $excluded);
        $this->assertNotContains('pr_number', $excluded);
        $this->assertNotContains('cost_center_id', $excluded);
        $this->assertNotContains('status_id', $excluded);
    }

    public function test_import_modal_description_renders_the_accordion_guide_without_crashing(): void
    {
        $this->actingAsUserWithPermissions([
            'purchase_request.view', 'purchase_request.create', 'purchase_request.edit',
        ]);

        $instance = Livewire::test(ListPurchaseRequests::class)
            ->mountAction('importPurchaseRequests');

        $description = (string) $instance->instance()->getMountedAction()->getModalDescription();

        $this->assertStringContainsString('downloadExample', $description);
        $this->assertStringContainsString(__('resources/general/strings.import.guide.title'), $description);

        foreach (PurchaseRequestImporter::autoFilledColumnNames() as $column) {
            $this->assertStringContainsString('columnMap.'.$column.'"', $description);
        }

        $this->assertStringContainsString('downloadFilledExample', $description);
    }

    public function test_import_parses_a_valid_jalali_date_with_persian_digits_to_the_correct_gregorian_value(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();

        $importer = $this->invokeMergedImporter([
            'pr_number' => '',
            'requester_id' => $requester->email,
            'department_id' => $department->name,
            'cost_center_id' => '',
            'required_by_date' => '۱۴۰۴-۰۱-۰۱',
            'urgency_level' => 'low',
            'total_estimated_cost' => '',
            'status_id' => '',
            'notes' => '',
        ], [], ['jalali' => true, 'date_format' => 'Y-m-d']);

        $this->assertSame('2025-03-21', $importer->getRecord()->required_by_date->format('Y-m-d'));
    }

    public function test_import_parses_a_valid_gregorian_date_with_mixed_digit_scripts_to_the_correct_value(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();

        $importer = $this->invokeMergedImporter([
            'pr_number' => '',
            'requester_id' => $requester->email,
            'department_id' => $department->name,
            'cost_center_id' => '',
            'required_by_date' => '٢٠٢۶-١٢-٣١',
            'urgency_level' => 'low',
            'total_estimated_cost' => '',
            'status_id' => '',
            'notes' => '',
        ], [], ['jalali' => false, 'date_format' => 'Y-m-d']);

        $this->assertSame('2026-12-31', $importer->getRecord()->required_by_date->format('Y-m-d'));
    }

    public function test_import_rejects_an_invalid_jalali_date_cleanly_instead_of_crashing(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();

        try {
            $this->invokeMergedImporter([
                'pr_number' => '',
                'requester_id' => $requester->email,
                'department_id' => $department->name,
                'cost_center_id' => '',
                'required_by_date' => '1404-13-01',
                'urgency_level' => 'low',
                'total_estimated_cost' => '',
                'status_id' => '',
                'notes' => '',
            ], [], ['jalali' => true, 'date_format' => 'Y-m-d']);

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertStringContainsString('1404-13-01', $exception->getMessage());
        }
    }

    public function test_import_rejects_an_invalid_gregorian_date_cleanly_instead_of_crashing(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();

        try {
            $this->invokeMergedImporter([
                'pr_number' => '',
                'requester_id' => $requester->email,
                'department_id' => $department->name,
                'cost_center_id' => '',
                'required_by_date' => '2026-02-30',
                'urgency_level' => 'low',
                'total_estimated_cost' => '',
                'status_id' => '',
                'notes' => '',
            ], [], ['jalali' => false, 'date_format' => 'Y-m-d']);

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertStringContainsString('2026-02-30', $exception->getMessage());
        }
    }

    public function test_import_rejects_a_malformed_date_string_cleanly_instead_of_crashing(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();

        try {
            $this->invokeMergedImporter([
                'pr_number' => '',
                'requester_id' => $requester->email,
                'department_id' => $department->name,
                'cost_center_id' => '',
                'required_by_date' => 'not-a-date',
                'urgency_level' => 'low',
                'total_estimated_cost' => '',
                'status_id' => '',
                'notes' => '',
            ], [], ['jalali' => false, 'date_format' => 'Y-m-d']);

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertStringContainsString('not-a-date', $exception->getMessage());
        }
    }

    public function test_filled_example_file_exists_and_matches_the_grouped_import_shape(): void
    {
        $path = PurchaseRequestImporter::filledExamplePath();

        $this->assertNotNull($path);

        $fullPath = storage_path('app/'.$path);
        $this->assertFileExists($fullPath);

        $contents = file_get_contents($fullPath);

        $this->assertStringContainsString('product_id', $contents);
        $this->assertStringContainsString('extra_key_1', $contents);
    }

    public function test_import_accepts_localized_urgency_and_unit_values(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();
        $product = Product::factory()->create();

        $importer = $this->invokeMergedImporter([
            'pr_number' => '',
            'requester_id' => $requester->email,
            'department_id' => $department->name,
            'cost_center_id' => '',
            'required_by_date' => '2026-12-31',
            'urgency_level' => trans('resources/purchaseRequest/strings.general.urgency', [], 'fa')['high'],
            'total_estimated_cost' => '10',
            'status_id' => '',
            'notes' => '',
        ], [
            ['product_id' => $product->code, 'quantity' => '1', 'unit' => trans('resources/general/strings.metrics', [], 'fa')['kg'], 'estimated_cost' => '1', 'item_status_id' => '', 'item_notes' => ''],
        ]);

        $record = $importer->getRecord();

        $this->assertSame('high', $record->urgency_level);
        $this->assertSame('kg', $record->items()->first()->unit);
    }

    public function test_import_syncs_custom_attributes_from_filled_extra_pairs(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();

        $importer = $this->invokeMergedImporter([
            'pr_number' => '',
            'requester_id' => $requester->email,
            'department_id' => $department->name,
            'cost_center_id' => '',
            'required_by_date' => '2026-12-31',
            'urgency_level' => 'low',
            'total_estimated_cost' => '10',
            'status_id' => '',
            'notes' => '',
            'extra_key_1' => 'Incoterm',
            'extra_value_1' => 'FOB',
            'extra_key_2' => 'Contract Ref',
            'extra_value_2' => 'CR-2026-04',
            'extra_key_3' => '',
            'extra_value_3' => '',
        ]);

        $record = $importer->getRecord()->fresh();

        $this->assertSame(2, $record->customAttributes()->count());
        $this->assertSame('FOB', $record->customAttributes()->where('key', 'Incoterm')->value('value'));
        $this->assertSame('CR-2026-04', $record->customAttributes()->where('key', 'Contract Ref')->value('value'));
    }

    public function test_import_does_not_wipe_existing_custom_attributes_when_extra_pairs_are_blank(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();
        $costCenter = Department::factory()->create();
        $status = $this->prStatus('ImportEavPreserve');

        $record = PurchaseRequest::factory()->create([
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'cost_center_id' => $costCenter->id,
            'status_id' => $status->id,
        ]);
        $record->syncCustomAttributes(['Existing Key' => 'Existing Value']);

        $this->invokeMergedImporter([
            'pr_number' => $record->pr_number,
            'requester_id' => $requester->email,
            'department_id' => $department->name,
            'cost_center_id' => $costCenter->name,
            'required_by_date' => '2026-12-31',
            'urgency_level' => 'low',
            'total_estimated_cost' => '10',
            'status_id' => $status->english_name,
            'notes' => 'Updated via re-upload without touching EAV',
        ]);

        $record->refresh();
        $this->assertSame(1, $record->customAttributes()->count());
        $this->assertSame('Existing Value', $record->customAttributes()->where('key', 'Existing Key')->value('value'));
    }

    public function test_import_rejects_a_nonsense_unit_value(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();
        $product = Product::factory()->create();

        $this->expectException(GroupRowFailedException::class);

        $this->invokeMergedImporter([
            'pr_number' => '',
            'requester_id' => $requester->email,
            'department_id' => $department->name,
            'cost_center_id' => '',
            'required_by_date' => '2026-12-31',
            'urgency_level' => 'low',
            'total_estimated_cost' => '10',
            'status_id' => '',
            'notes' => '',
        ], [
            ['product_id' => $product->code, 'quantity' => '1', 'unit' => 'purple monkey dishwasher', 'estimated_cost' => '1', 'item_status_id' => '', 'item_notes' => ''],
        ]);
    }

    public function test_import_defaults_requester_and_department_and_status_from_the_importing_user_when_blank(): void
    {
        $department = Department::factory()->create();
        $importingUser = $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view']);
        $importingUser->forceFill(['department_id' => $department->id])->save();
        $expectedStatusId = \App\Services\StatusWorkflow::initialFor(PurchaseRequest::TYPE_PURCHASE_REQUEST)?->id;

        $importer = $this->invokeMergedImporter([
            'pr_number' => '',
            'requester_id' => null,
            'department_id' => null,
            'cost_center_id' => null,
            'required_by_date' => '2026-12-31',
            'urgency_level' => 'low',
            'total_estimated_cost' => '10',
            'status_id' => null,
            'notes' => null,
        ]);

        $record = $importer->getRecord();

        $this->assertSame($importingUser->id, $record->requester_id);
        $this->assertSame($department->id, $record->department_id);
        $this->assertSame($expectedStatusId, $record->status_id);
    }

    public function test_import_defaults_cost_center_to_department_and_required_by_date_to_one_month_when_blank(): void
    {
        $department = Department::factory()->create();
        $importingUser = $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view']);
        $importingUser->forceFill(['department_id' => $department->id])->save();

        $importer = $this->invokeMergedImporter([
            'pr_number' => '',
            'requester_id' => null,
            'department_id' => null,
            'cost_center_id' => null,
            'required_by_date' => null,
            'urgency_level' => 'low',
            'total_estimated_cost' => '10',
            'status_id' => null,
            'notes' => null,
        ]);

        $record = $importer->getRecord();

        $this->assertSame($department->id, $record->cost_center_id);
        $this->assertSame(now()->addMonth()->format('Y-m-d'), $record->required_by_date->format('Y-m-d'));
    }

    public function test_import_recalculates_total_estimated_cost_from_priced_item_rows(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();
        $productOne = Product::factory()->create();
        $productTwo = Product::factory()->create();

        $importer = $this->invokeMergedImporter([
            'pr_number' => '',
            'requester_id' => $requester->email,
            'department_id' => $department->name,
            'cost_center_id' => $department->name,
            'required_by_date' => '2026-12-31',
            'urgency_level' => 'low',
            'total_estimated_cost' => '999999',
            'status_id' => '',
            'notes' => '',
        ], [
            ['product_id' => $productOne->code, 'quantity' => '3', 'unit' => 'kg', 'estimated_cost' => '10', 'item_status_id' => '', 'item_notes' => ''],
            ['product_id' => $productTwo->code, 'quantity' => '2', 'unit' => 'kg', 'estimated_cost' => '5', 'item_status_id' => '', 'item_notes' => ''],
        ]);

        $record = $importer->getRecord()->fresh();

        $this->assertSame('40.00000', $record->total_estimated_cost);
    }

    public function test_import_rejects_a_new_record_when_department_cannot_be_determined_instead_of_a_raw_sql_error(): void
    {
        $importingUser = $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view']);
        $importingUser->forceFill(['department_id' => null])->save();

        try {
            $this->invokeMergedImporter([
                'pr_number' => '',
                'requester_id' => null,
                'department_id' => null,
                'cost_center_id' => null,
                'required_by_date' => '2026-12-31',
                'urgency_level' => 'low',
                'total_estimated_cost' => '10',
                'status_id' => null,
                'notes' => null,
            ]);

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertStringContainsString(trans('resources/purchaseRequest/strings.form.department', [], 'en'), $exception->getMessage());
            $this->assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    public function test_import_still_rejects_an_unresolvable_requester_value(): void
    {
        $department = Department::factory()->create();

        try {
            $this->invokeMergedImporter([
                'pr_number' => '',
                'requester_id' => 'no-such-person@example.com',
                'department_id' => $department->name,
                'cost_center_id' => $department->name,
                'required_by_date' => '2026-12-31',
                'urgency_level' => 'low',
                'total_estimated_cost' => '10',
                'status_id' => '',
                'notes' => '',
            ]);

            $this->fail('Expected ValidationException was not thrown.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertStringContainsString('no-such-person@example.com', collect($exception->errors())->flatten()->implode(' '));
        }
    }

    public function test_import_still_rejects_an_unresolvable_department_value(): void
    {
        $requester = User::factory()->create();

        try {
            $this->invokeMergedImporter([
                'pr_number' => '',
                'requester_id' => $requester->email,
                'department_id' => 'NoSuchDepartmentAtAll',
                'cost_center_id' => '',
                'required_by_date' => '2026-12-31',
                'urgency_level' => 'low',
                'total_estimated_cost' => '10',
                'status_id' => '',
                'notes' => '',
            ]);

            $this->fail('Expected ValidationException was not thrown.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertStringContainsString('NoSuchDepartmentAtAll', collect($exception->errors())->flatten()->implode(' '));
        }
    }

    public function test_import_new_record_with_unresolvable_cost_center_saves_via_the_declared_fallback_and_logs_a_note(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();

        $importer = $this->invokeMergedImporter([
            'pr_number' => '',
            'requester_id' => $requester->email,
            'department_id' => $department->name,
            'cost_center_id' => 'NoSuchCostCenter',
            'required_by_date' => '2026-12-31',
            'urgency_level' => 'low',
            'total_estimated_cost' => '10',
            'status_id' => '',
            'notes' => '',
        ]);

        $record = $importer->getRecord();

        $this->assertSame($department->id, $record->cost_center_id);
        $this->assertSame(\App\Services\StatusWorkflow::initialFor(PurchaseRequest::TYPE_PURCHASE_REQUEST)?->id, $record->status_id);
        $this->assertStringContainsString('NoSuchCostCenter', $record->notes);
    }

    public function test_import_reupload_with_unresolvable_cost_center_preserves_the_existing_value_and_logs_a_note(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();
        $costCenter = Department::factory()->create();
        $status = $this->prStatus('ImportNullMismatchStatus');

        $importer = $this->invokeMergedImporter([
            'pr_number' => '',
            'requester_id' => $requester->email,
            'department_id' => $department->name,
            'cost_center_id' => $costCenter->name,
            'required_by_date' => '2026-12-31',
            'urgency_level' => 'low',
            'total_estimated_cost' => '10',
            'status_id' => $status->english_name,
            'notes' => 'Original',
        ]);

        $record = $importer->getRecord();

        $this->invokeMergedImporter([
            'pr_number' => $record->pr_number,
            'requester_id' => $requester->email,
            'department_id' => $department->name,
            'cost_center_id' => 'NoSuchCostCenter',
            'required_by_date' => '2026-12-31',
            'urgency_level' => 'low',
            'total_estimated_cost' => '10',
            'status_id' => $status->english_name,
            'notes' => 'Original',
        ]);

        $record->refresh();

        $this->assertSame($costCenter->id, $record->cost_center_id);
        $this->assertSame($status->id, $record->status_id);
        $this->assertStringContainsString('NoSuchCostCenter', $record->notes);
    }

    public function test_import_still_rejects_an_unresolvable_status_value(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();

        try {
            $this->invokeMergedImporter([
                'pr_number' => '',
                'requester_id' => $requester->email,
                'department_id' => $department->name,
                'cost_center_id' => '',
                'required_by_date' => '2026-12-31',
                'urgency_level' => 'low',
                'total_estimated_cost' => '10',
                'status_id' => 'NoSuchStatus',
                'notes' => '',
            ]);

            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertStringContainsString('NoSuchStatus', $exception->getMessage());
        }
    }

    public function test_grouped_import_action_classifies_item_without_product_as_a_failure(): void
    {
        $action = $this->groupedImportAction();
        $method = new ReflectionMethod($action, 'groupRows');
        $method->setAccessible(true);

        $columnMap = ['product_id' => 'Product', 'quantity' => 'Qty'];
        $rows = [
            ['physicalRow' => 2, 'data' => ['Product' => '', 'Qty' => '3']],
        ];

        [$groups, $failures] = $method->invoke($action, $rows, $columnMap);

        $this->assertSame([], $groups);
        $this->assertCount(1, $failures);
        $this->assertSame(
            __('resources/general/strings.import.item_without_product', ['row' => 2]),
            $failures[0]['validation_error']
        );
    }

    public function test_grouped_import_action_classifies_orphan_item_row_as_a_failure(): void
    {
        $action = $this->groupedImportAction();
        $method = new ReflectionMethod($action, 'groupRows');
        $method->setAccessible(true);

        $columnMap = ['product_id' => 'Product', 'quantity' => 'Qty'];
        $rows = [
            ['physicalRow' => 2, 'data' => ['Product' => 'Widget', 'Qty' => '1']],
        ];

        [$groups, $failures] = $method->invoke($action, $rows, $columnMap);

        $this->assertSame([], $groups);
        $this->assertCount(1, $failures);
        $this->assertSame(
            __('resources/general/strings.import.orphan_item_row', ['row' => 2]),
            $failures[0]['validation_error']
        );
    }

    public function test_grouped_import_action_groups_rows_by_file_order_around_parent_rows(): void
    {
        $action = $this->groupedImportAction();
        $method = new ReflectionMethod($action, 'groupRows');
        $method->setAccessible(true);

        $columnMap = ['pr_number' => 'PR No', 'product_id' => 'Product', 'quantity' => 'Qty'];
        $rows = [
            ['physicalRow' => 2, 'data' => ['PR No' => 'PR-1', 'Product' => '', 'Qty' => '']],
            ['physicalRow' => 3, 'data' => ['PR No' => '', 'Product' => 'Widget', 'Qty' => '5']],
            ['physicalRow' => 4, 'data' => ['PR No' => 'PR-2', 'Product' => '', 'Qty' => '']],
        ];

        [$groups, $failures] = $method->invoke($action, $rows, $columnMap);

        $this->assertSame([], $failures);
        $this->assertCount(2, $groups);
        $this->assertCount(1, $groups[0]['items']);
        $this->assertCount(0, $groups[1]['items']);
    }

    public function test_grouped_import_action_never_splits_a_group_across_chunks(): void
    {
        $action = $this->groupedImportAction();
        $method = new ReflectionMethod($action, 'chunkGroups');
        $method->setAccessible(true);

        $bigGroup = ['parent' => [], 'parentPhysicalRow' => 2, 'items' => array_fill(0, 150, ['row' => [], 'physicalRow' => 3])];
        $smallGroup = ['parent' => [], 'parentPhysicalRow' => 200, 'items' => []];

        $chunks = $method->invoke($action, [$bigGroup, $smallGroup], 100);

        $this->assertCount(2, $chunks);
        $this->assertCount(1, $chunks[0]);
        $this->assertCount(1, $chunks[1]);
    }

    // Export

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'pr_export_').'.csv';
        PurchaseRequestExporter::write($query, $path);

        $csv = (string) file_get_contents($path);
        unlink($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", trim(ltrim($csv, "\xEF\xBB\xBF"))))));
        $header = str_getcsv($lines[0]);
        $rows = array_map(fn (string $line) => array_combine($header, str_getcsv($line)), array_slice($lines, 1));

        return ['header' => $header, 'rows' => $rows];
    }

    public function test_exporter_stream_emits_a_parent_row_then_item_rows(): void
    {
        app()->setLocale('en');
        $requester = User::factory()->create();
        $department = Department::factory()->create(['english_name' => 'Export Department EN']);
        $costCenter = Department::factory()->create(['english_name' => 'Export Cost Center EN']);
        $status = $this->prStatus('ExportStatusCheck');
        $itemStatus = $this->prItemStatus('ExportItemStatusCheck');
        $product = Product::factory()->create();

        $record = PurchaseRequest::factory()->create([
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'cost_center_id' => $costCenter->id,
            'status_id' => $status->id,
            'required_by_date' => '2026-01-15',
            'total_estimated_cost' => 1234.5,
            'notes' => null,
        ]);
        PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $record->id,
            'product_id' => $product->id,
            'status_id' => $itemStatus->id,
            'quantity' => 3.5,
            'unit' => 'kg',
            'estimated_cost' => 12.25,
            'notes' => null,
        ]);

        ['rows' => [$parentRow, $itemRow]] = $this->exportToRows(PurchaseRequest::query()->whereKey($record->id));

        $labels = PurchaseRequestImporter::columnLabels();

        $this->assertSame($record->pr_number, $parentRow[$labels['pr_number']]);
        $this->assertSame($requester->email, $parentRow[$labels['requester_id']]);
        $this->assertSame('Export Department EN', $parentRow[$labels['department_id']]);
        $this->assertSame(jdate($record->required_by_date)->format('Y-m-d'), $parentRow[$labels['required_by_date']]);
        $this->assertSame((string) $record->total_estimated_cost, $parentRow[$labels['total_estimated_cost']]);
        $this->assertSame($status->english_name, $parentRow[$labels['status_id']]);
        $this->assertSame('', $parentRow[$labels['product_id']]);

        $this->assertSame('', $itemRow[$labels['pr_number']]);
        $this->assertSame($product->english_name, $itemRow[$labels['product_id']]);
        $this->assertSame((string) $record->items->first()->quantity, $itemRow[$labels['quantity']]);
        $this->assertSame($itemStatus->english_name, $itemRow[$labels['item_status_id']]);
    }

    public function test_exporter_stream_localizes_department_and_status_for_the_active_locale(): void
    {
        app()->setLocale('fa');
        $department = Department::factory()->create(['name' => 'دپارتمان تست', 'english_name' => 'Test Department EN']);
        $status = Status::factory()->create([
            'type' => PurchaseRequest::TYPE_PURCHASE_REQUEST,
            'english_type' => PurchaseRequest::TYPE_PURCHASE_REQUEST,
            'name' => 'تایید شده',
            'english_name' => 'Authorized',
        ]);
        $record = PurchaseRequest::factory()->create([
            'department_id' => $department->id,
            'status_id' => $status->id,
        ]);

        ['rows' => [$parentRow]] = $this->exportToRows(PurchaseRequest::query()->whereKey($record->id));
        $labels = PurchaseRequestImporter::columnLabels();

        $this->assertSame('دپارتمان تست', $parentRow[$labels['department_id']]);
        $this->assertSame('تایید شده', $parentRow[$labels['status_id']]);
    }

    public function test_exporter_stream_strips_html_tags_from_notes(): void
    {
        $record = PurchaseRequest::factory()->create(['notes' => '<p>Budget approved <strong>urgently</strong></p>']);
        PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $record->id,
            'notes' => '<p></p>',
        ]);

        ['rows' => [$parentRow, $itemRow]] = $this->exportToRows(PurchaseRequest::query()->whereKey($record->id));
        $labels = PurchaseRequestImporter::columnLabels();

        $this->assertSame('Budget approved urgently', $parentRow[$labels['notes']]);
        $this->assertSame('', $itemRow[$labels['item_notes']]);
    }

    public function test_export_then_reimport_round_trip_produces_no_changes_and_no_failures(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create(['english_name' => 'RoundTrip Department EN']);
        $costCenter = Department::factory()->create(['english_name' => 'RoundTrip Cost Center EN']);
        $status = $this->prStatus('RoundTripStatus');
        $itemStatus = $this->prItemStatus('RoundTripItemStatus');
        $product = Product::factory()->create();

        $record = PurchaseRequest::factory()->create([
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'cost_center_id' => $costCenter->id,
            'status_id' => $status->id,
            'required_by_date' => '2026-02-10',
            'urgency_level' => 'medium',
            'total_estimated_cost' => 500,
            'notes' => 'Round trip note',
        ]);
        PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $record->id,
            'product_id' => $product->id,
            'status_id' => $itemStatus->id,
            'quantity' => 4,
            'unit' => 'kg',
            'estimated_cost' => 8,
            'notes' => 'Round trip item note',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'pr_export_').'.csv';
        PurchaseRequestExporter::write(PurchaseRequest::query()->whereKey($record->id), $path);
        $csv = (string) file_get_contents($path);
        unlink($path);

        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", trim(ltrim($csv, "\xEF\xBB\xBF"))))));
        $header = str_getcsv($lines[0]);
        $dataLines = array_slice($lines, 1);

        $labels = PurchaseRequestImporter::columnLabels();
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

        $this->assertSame(1, PurchaseRequest::where('pr_number', $record->pr_number)->count());

        $record->refresh();
        $this->assertSame($department->id, $record->department_id);
        $this->assertSame($costCenter->id, $record->cost_center_id);
        $this->assertSame('medium', $record->urgency_level);
        $this->assertSame(1, $record->items()->count());
        $this->assertSame($product->id, $record->items()->first()->product_id);
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        // Pinned count — a silent column drop during a future refactor (exactly what happened to BankProfile's exporter this session) must fail this test, not slip through unnoticed.
        $this->assertCount(25, PurchaseRequestImporter::getColumns());
        $this->assertCount(6, PurchaseRequestItemImporter::getColumns());
        $this->assertCount(15, PurchaseRequestImporter::columnLabels());
    }

    // Infolist — compact items row (product/quantity/unit/estimated_cost/status, no notes)

    public function test_purchase_items_infolist_entry_keeps_only_the_compact_field_set(): void
    {
        $names = collect($this->repeatableItemComponents(PurchaseRequestResource::viewPurchaseItems()))
            ->map(fn ($component) => $component->getName())
            ->all();

        $this->assertSame(['product.name', 'quantity', 'unit', 'estimated_cost', 'status.name'], $names);
    }

    public function test_purchase_items_infolist_entry_renders_the_kept_fields(): void
    {
        app()->setLocale('en');
        $product = Product::factory()->create(['name' => 'محصول الف', 'english_name' => 'Alpha Product']);
        $status = $this->prItemStatus('Pending');
        $item = PurchaseRequestItem::factory()->make([
            'quantity' => 5,
            'unit' => 'kg',
            'estimated_cost' => 42.5,
        ]);
        $item->setRelation('product', $product);
        $item->setRelation('status', $status);

        $components = collect($this->repeatableItemComponents(PurchaseRequestResource::viewPurchaseItems()))->keyBy(fn ($c) => $c->getName());

        $this->assertSame('Alpha Product', $components['product.name']->model($item)->formatState($item->product?->getLocalizedNameAttribute()));
        $this->assertSame(preciseNumber(5), $components['quantity']->model($item)->formatState(5));
        $this->assertSame(__('resources/general/strings.metrics.kg'), $components['unit']->model($item)->formatState('kg'));
        $this->assertSame(preciseNumber(42.5), $components['estimated_cost']->model($item)->formatState(42.5));
        $this->assertSame('Pending', $components['status.name']->model($item)->formatState(null));
    }

    // Item estimated cost — renamed label

    public function test_item_estimated_cost_field_and_infolist_entry_use_the_renamed_label(): void
    {
        app()->setLocale('en');
        $expected = 'Approximate Selling Price';
        $this->assertSame($expected, __('resources/purchaseRequest/strings.form.estimated_cost'));

        $field = PurchaseRequestResource::getItemEstimatedCostField();
        $this->assertSame($expected, $field->getLabel());

        $entry = collect($this->repeatableItemComponents(PurchaseRequestResource::viewPurchaseItems()))
            ->first(fn ($component) => $component->getName() === 'estimated_cost');

        $this->assertSame($expected, $entry->getLabel());
    }

    // Infolist — status history tab

    public function test_infolist_status_history_tab_renders_and_badge_matches_history_count(): void
    {
        app()->setLocale('en');
        $pr = PurchaseRequest::factory()->create();
        $pr->update(['status_id' => $this->prStatus('Authorized')->id]);
        $pr->load('statusHistories');

        $tab = $this->statusHistoryTab(PurchaseRequestResource::infolist(Schema::make()));

        $this->assertNotNull($tab);
        $this->assertSame($pr->statusHistories->count(), $this->statusHistoryTabBadge($tab, $pr));
    }

    // Global search contract

    public function test_global_search_title_uses_cart_emoji_prefix_and_pr_number(): void
    {
        $record = PurchaseRequest::factory()->create();

        $this->assertSame('🛒 '.$record->pr_number, PurchaseRequestResource::getGlobalSearchResultTitle($record));
    }

    public function test_globally_searchable_attributes_include_pr_number(): void
    {
        $this->assertContains('pr_number', PurchaseRequestResource::getGloballySearchableAttributes());
    }
}
