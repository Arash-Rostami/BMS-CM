<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CorrespondenceResource;
use App\Filament\Resources\Operational\CorrespondenceResource\Exports\CorrespondenceExporter;
use App\Filament\Resources\Operational\CorrespondenceResource\Pages\CreateCorrespondence;
use App\Filament\Resources\Operational\CorrespondenceResource\Pages\EditCorrespondence;
use App\Filament\Resources\Operational\CorrespondenceResource\Pages\ListCorrespondences;
use App\Models\Correspondence;
use App\Models\Permission;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\SmartCacheManager;
use App\Services\StatusWorkflow;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class CorrespondenceResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        $this->resetReplyParentCache();
        SmartCacheManager::invalidate('Correspondence');
        SmartCacheManager::invalidate('Status');
    }

    protected function tearDown(): void
    {
        SmartCacheManager::invalidate('Correspondence');
        SmartCacheManager::invalidate('Status');
        $this->resetReplyParentCache();
        DB::rollBack();
        parent::tearDown();
    }

    private function resetReplyParentCache(): void
    {
        $reflection = new ReflectionProperty(CorrespondenceResource::class, 'replyParent');
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);
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

    private function correspondenceStatus(string $englishName): Status
    {
        return Status::factory()->create([
            'type' => Correspondence::TYPE_CORRESPONDENCE_STATUS,
            'english_type' => Correspondence::TYPE_CORRESPONDENCE_STATUS,
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

    public function test_infolist_status_history_tab_renders_and_badge_matches_history_count(): void
    {
        app()->setLocale('en');
        $correspondence = Correspondence::factory()->create();
        $correspondence->update(['status_id' => $this->correspondenceStatus('Sent')->id]);
        $correspondence->load('statusHistories');

        $tab = $this->statusHistoryTab(CorrespondenceResource::infolist(Schema::make()));

        $this->assertNotNull($tab);
        $this->assertSame($correspondence->statusHistories->count(), $this->statusHistoryTabBadge($tab, $correspondence));
    }

    // Permissions

    public function test_full_permissions_allow_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([
            'correspondence.view',
            'correspondence.create',
            'correspondence.edit',
            'correspondence.delete',
            'correspondence.restore',
        ]);

        $record = Correspondence::factory()->create();

        $this->assertTrue(CorrespondenceResource::canViewAny());
        $this->assertTrue(CorrespondenceResource::canCreate());
        $this->assertTrue(CorrespondenceResource::canEdit($record));
        $this->assertTrue(CorrespondenceResource::canDelete($record));
        $this->assertTrue(CorrespondenceResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = Correspondence::factory()->create();

        $this->assertFalse(CorrespondenceResource::canViewAny());
        $this->assertFalse(CorrespondenceResource::canCreate());
        $this->assertFalse(CorrespondenceResource::canEdit($record));
        $this->assertFalse(CorrespondenceResource::canDelete($record));
        $this->assertFalse(CorrespondenceResource::canRestore($record));
    }

    // List — search

    public function test_list_page_renders_and_search_finds_by_subject(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.view']);

        $target = Correspondence::factory()->create(['subject' => 'Unique Subject Alpha']);
        $other = Correspondence::factory()->create(['subject' => 'Different Topic Beta']);

        Livewire::test(ListCorrespondences::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable('Unique Subject Alpha')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Filters

    public function test_type_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.view']);

        $note = Correspondence::factory()->create(['type' => 'note']);
        $warning = Correspondence::factory()->create(['type' => 'warning']);

        Livewire::test(ListCorrespondences::class)
            ->filterTable('type', ['note'])
            ->assertCanSeeTableRecords([$note])
            ->assertCanNotSeeTableRecords([$warning]);
    }

    public function test_priority_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.view']);

        $low = Correspondence::factory()->create(['priority' => 'low']);
        $urgent = Correspondence::factory()->create(['priority' => 'urgent']);

        Livewire::test(ListCorrespondences::class)
            ->filterTable('priority', ['urgent'])
            ->assertCanSeeTableRecords([$urgent])
            ->assertCanNotSeeTableRecords([$low]);
    }

    public function test_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.view']);

        $statusA = $this->correspondenceStatus('FilterStatusA');
        $statusB = $this->correspondenceStatus('FilterStatusB');
        $withA = Correspondence::factory()->create(['status_id' => $statusA->id]);
        $withB = Correspondence::factory()->create(['status_id' => $statusB->id]);

        Livewire::test(ListCorrespondences::class)
            ->filterTable('status_id', $statusA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_creator_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['correspondence.view']);
        $withA = Correspondence::factory()->create(['user_id' => $userA->id]);

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $withB = Correspondence::factory()->create(['user_id' => $userB->id]);

        $this->actingAs($userA);

        Livewire::test(ListCorrespondences::class)
            ->filterTable('user_id', $userA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_creation_date_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.view']);

        $withinRange = Correspondence::factory()->create();
        $outsideRange = Correspondence::factory()->create();
        Correspondence::whereKey($withinRange->id)->update(['created_at' => '2026-01-10']);
        Correspondence::whereKey($outsideRange->id)->update(['created_at' => '2026-06-10']);

        Livewire::test(ListCorrespondences::class)
            ->filterTable('created_at', ['created_from' => '2026-01-01', 'created_until' => '2026-01-31'])
            ->assertCanSeeTableRecords([$withinRange])
            ->assertCanNotSeeTableRecords([$outsideRange]);
    }

    public function test_my_mentions_filter_narrows_the_table_to_records_where_the_user_is_a_recipient(): void
    {
        $user = $this->actingAsUserWithPermissions(['correspondence.view']);

        $mentioned = Correspondence::factory()->create();
        $mentioned->recipients()->attach($user->id, ['type' => 'to', 'read_at' => null]);
        $notMentioned = Correspondence::factory()->create();

        Livewire::test(ListCorrespondences::class)
            ->filterTable('my_mentions')
            ->assertCanSeeTableRecords([$mentioned])
            ->assertCanNotSeeTableRecords([$notMentioned]);
    }

    public function test_unread_filter_narrows_the_table_to_the_current_users_unread_recipient_records(): void
    {
        $user = $this->actingAsUserWithPermissions(['correspondence.view']);

        $unread = Correspondence::factory()->create();
        $unread->recipients()->attach($user->id, ['type' => 'to', 'read_at' => null]);
        $read = Correspondence::factory()->create();
        $read->recipients()->attach($user->id, ['type' => 'to', 'read_at' => now()]);

        Livewire::test(ListCorrespondences::class)
            ->filterTable('unread')
            ->assertCanSeeTableRecords([$unread])
            ->assertCanNotSeeTableRecords([$read]);
    }

    // Reply row action — always links to the true thread root

    public function test_reply_action_url_points_to_the_true_thread_root_even_for_a_reply_of_a_reply(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.view']);

        $root = Correspondence::factory()->create(['parent_id' => null]);
        $firstReply = Correspondence::factory()->create(['parent_id' => $root->id]);
        $secondReply = Correspondence::factory()->create(['parent_id' => $firstReply->id]);

        Livewire::test(ListCorrespondences::class)
            ->assertTableActionHasUrl('reply', CreateCorrespondence::getUrl(['parent_id' => $root->id]), $secondReply);
    }

    // View action — marks the current user's own recipient pivot as read on mount

    public function test_view_action_marks_the_current_users_recipient_pivot_as_read(): void
    {
        $user = $this->actingAsUserWithPermissions(['correspondence.view']);
        $record = Correspondence::factory()->create();
        $record->recipients()->attach($user->id, ['type' => 'to', 'read_at' => null]);

        Livewire::test(ListCorrespondences::class)
            ->mountTableAction('view', $record);

        $pivot = $record->recipients()->wherePivot('user_id', $user->id)->first()->pivot;
        $this->assertNotNull($pivot->read_at);
    }

    // Edit page — hydrates recipients_to / recipients_cc on mount (mutateFormDataBeforeFill, not fillForm())

    public function test_edit_page_hydrates_recipients_to_and_cc_from_the_pivot_type_column(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.edit', 'correspondence.view']);
        $toUser = User::factory()->create();
        $ccUser = User::factory()->create(['name' => 'CC Person']);
        $record = Correspondence::factory()->create();
        $record->recipients()->attach($toUser->id, ['type' => 'to']);
        $record->recipients()->attach($ccUser->id, ['type' => 'cc']);

        Livewire::test(EditCorrespondence::class, ['record' => $record->getRouteKey()])
            ->assertFormSet([
                'recipients_to' => [$toUser->id],
                'recipients_cc' => ['CC Person'],
            ]);
    }

    public function test_edit_page_loads_existing_values_and_persists_a_status_update(): void
    {
        $this->markTestSkipped('fillForm() harness quirk — see tests/testPattern.md §3d. The required recipients_to Select is a plain (non-relationship) field alongside the relationship-bound status_id Select, so fillForm() nulls it out and blocks save.');

        $this->actingAsUserWithPermissions(['correspondence.edit', 'correspondence.view']);
        $statusA = $this->correspondenceStatus('EditStatusA');
        $statusB = $this->correspondenceStatus('EditStatusB');
        $record = Correspondence::factory()->create(['status_id' => $statusA->id]);

        Livewire::test(EditCorrespondence::class, ['record' => $record->getRouteKey()])
            ->assertFormSet(['status_id' => $statusA->id])
            ->fillForm(['status_id' => $statusB->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($statusB->id, $record->fresh()->status_id);
    }

    // Create page — validation only; §3d plain-scalar fillForm() quirk doesn't affect assertHasFormErrors

    public function test_create_requires_subject_body_type_priority_status_and_recipients(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.create', 'correspondence.view']);

        Livewire::test(CreateCorrespondence::class)
            ->fillForm([
                'subject' => null,
                'body' => null,
                'type' => null,
                'priority' => null,
                'status_id' => null,
                'recipients_to' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'subject' => 'required',
                'body',
                'type' => 'required',
                'priority' => 'required',
                'status_id' => 'required',
                'recipients_to' => 'required',
            ]);
    }

    // Create page — reply defaults derived from the ?parent_id query param, read directly off the
    // built field objects (no live Livewire mount needed for a plain ->default() evaluation)

    public function test_create_page_defaults_subject_and_correspondable_fields_from_the_reply_parent(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $parent = Correspondence::factory()->forCorrespondable($ro)->create(['subject' => 'Original Subject']);

        request()->query->set('parent_id', $parent->id);

        $this->assertSame($parent->id, CorrespondenceResource::getHiddenParentIdField()->getDefaultState());
        $this->assertSame(RegisteredOrder::class, CorrespondenceResource::getHiddenCorrespondableTypeField()->getDefaultState());
        $this->assertSame($ro->id, CorrespondenceResource::getHiddenCorrespondableIdField()->getDefaultState());
        $this->assertStringContainsString('Original Subject', CorrespondenceResource::getSubjectField()->getDefaultState());
    }

    // Status workflow — HasStatusWorkflow wiring is a no-op today (no admin-configured stage_order/approval_permission
    // exists for Correspondence Status), and becomes a real gate once one is temporarily configured — see filamentPattern.md §1.12b

    public function test_list_and_edit_pages_expose_the_status_workflow_pipeline_header_action(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.create', 'correspondence.view', 'correspondence.edit']);
        $record = Correspondence::factory()->create();

        Livewire::test(ListCorrespondences::class)
            ->assertActionExists('statusWorkflowPipeline');

        Livewire::test(EditCorrespondence::class, ['record' => $record->getRouteKey()])
            ->assertActionExists('statusWorkflowPipeline');
    }

    public function test_status_workflow_is_a_no_op_while_correspondence_status_has_no_stage_order_configured(): void
    {
        $type = Correspondence::TYPE_CORRESPONDENCE_STATUS;

        $this->assertNull(StatusWorkflow::initialFor($type));

        $unchanged = ['subject' => 'x', 'body' => 'y'];
        $this->assertSame($unchanged, CorrespondenceResource::applyInitialStatusOnCreate($unchanged));

        $a = $this->correspondenceStatus('NoOpOptionA');
        $b = $this->correspondenceStatus('NoOpOptionB');

        $method = new ReflectionMethod(CorrespondenceResource::class, 'availableStatusWorkflowIds');
        $method->setAccessible(true);
        $ids = $method->invoke(null, 'status_id', null);

        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
        $this->assertCount(Status::where('english_type', $type)->count(), $ids);
    }

    public function test_status_field_default_still_falls_back_to_the_real_submitted_status_on_create(): void
    {
        $submitted = Status::findBy(Correspondence::TYPE_CORRESPONDENCE_STATUS, 'Submitted');

        $field = CorrespondenceResource::getStatusField();
        $property = new ReflectionProperty($field, 'defaultState');
        $property->setAccessible(true);
        $closure = $property->getValue($field);

        $this->assertSame($submitted?->id, $closure('create'));
        $this->assertNull($closure('edit'));
    }

    public function test_status_transition_is_rejected_once_stage_order_and_approval_permission_are_configured(): void
    {
        $stageOne = $this->correspondenceStatus('GatedStageOneReject');
        $stageOne->update(['stage_order' => 1]);
        $stageTwo = $this->correspondenceStatus('GatedStageTwoReject');
        $stageTwo->update(['stage_order' => 2, 'approval_permission' => 'correspondence.grant_gated_stage_two_reject']);

        $record = Correspondence::factory()->create(['status_id' => $stageOne->id]);
        $this->actingAsUserWithPermissions(['correspondence.edit', 'correspondence.view']);

        $this->expectException(ValidationException::class);

        CorrespondenceResource::assertStatusTransitionAllowed($record, 'status_id', $stageTwo->id);
    }

    public function test_status_transition_succeeds_once_stage_order_and_approval_permission_are_granted(): void
    {
        $stageOne = $this->correspondenceStatus('GatedStageOneAllow');
        $stageOne->update(['stage_order' => 1]);
        $stageTwo = $this->correspondenceStatus('GatedStageTwoAllow');
        $stageTwo->update(['stage_order' => 2, 'approval_permission' => 'correspondence.grant_gated_stage_two_allow']);

        $record = Correspondence::factory()->create(['status_id' => $stageOne->id]);
        $this->actingAsUserWithPermissions(['correspondence.edit', 'correspondence.view', 'correspondence.grant_gated_stage_two_allow']);

        CorrespondenceResource::assertStatusTransitionAllowed($record, 'status_id', $stageTwo->id);

        $record->update(['status_id' => $stageTwo->id]);
        $this->assertSame('GatedStageTwoAllow', $record->fresh()->status->english_name);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.view', 'correspondence.delete', 'correspondence.restore']);
        $record = Correspondence::factory()->create();

        Livewire::test(ListCorrespondences::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Correspondence::find($record->id));
        $this->assertTrue(Correspondence::withTrashed()->find($record->id)->trashed());

        Livewire::test(ListCorrespondences::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(Correspondence::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.view', 'correspondence.delete']);
        $one = Correspondence::factory()->create();
        $two = Correspondence::factory()->create();

        Livewire::test(ListCorrespondences::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(Correspondence::find($one->id));
        $this->assertNull(Correspondence::find($two->id));
    }

    // Global search contract

    public function test_global_search_title_uses_the_speech_emoji_subject_and_date(): void
    {
        $record = Correspondence::factory()->create(['subject' => 'Global Search Subject']);

        $expected = '💬 Global Search Subject (📆 '.toYmdDate($record).')';

        $this->assertSame($expected, CorrespondenceResource::getGlobalSearchResultTitle($record));
    }

    public function test_globally_searchable_attributes_are_subject_and_body(): void
    {
        $this->assertSame(['subject', 'body'], CorrespondenceResource::getGloballySearchableAttributes());
    }

    // HandlesRecipients — precedence + extraction, unit-style (no Livewire/fillForm involved)

    public function test_sync_recipients_gives_to_precedence_when_a_user_is_in_both_to_and_cc_lists(): void
    {
        $shared = User::factory()->create(['name' => 'Shared Person']);
        $ccOnly = User::factory()->create(['name' => 'Cc Only Person']);
        $record = Correspondence::factory()->create();

        CreateCorrespondence::syncRecipients($record, [$shared->id], [$shared->name, $ccOnly->name]);

        $sharedType = $record->recipients()->wherePivot('user_id', $shared->id)->first()->pivot->type;
        $ccOnlyType = $record->recipients()->wherePivot('user_id', $ccOnly->id)->first()->pivot->type;

        $this->assertSame('to', $sharedType);
        $this->assertSame('cc', $ccOnlyType);
    }

    public function test_sync_recipients_resolves_cc_names_to_user_ids(): void
    {
        $ccUser = User::factory()->create(['name' => 'Named Cc User']);
        $record = Correspondence::factory()->create();

        CreateCorrespondence::syncRecipients($record, [], [$ccUser->name]);

        $this->assertTrue($record->recipients()->wherePivot('user_id', $ccUser->id)->exists());
    }

    public function test_extract_recipients_splits_recipients_to_and_cc_out_of_the_form_data(): void
    {
        [$cleanData, $to, $cc] = CreateCorrespondence::extractRecipients([
            'subject' => 'Test',
            'recipients_to' => [1, 2],
            'recipients_cc' => ['Someone'],
        ]);

        $this->assertSame(['subject' => 'Test'], $cleanData);
        $this->assertSame([1, 2], $to);
        $this->assertSame(['Someone'], $cc);
    }

    // Navigation badge — unread recipient count, scoped per user

    public function test_navigation_badge_counts_the_current_users_unread_recipient_rows_and_is_scoped_per_user(): void
    {
        $userA = User::factory()->create();
        $this->actingAs($userA);

        $unread = Correspondence::factory()->create();
        $unread->recipients()->attach($userA->id, ['type' => 'to', 'read_at' => null]);
        $read = Correspondence::factory()->create();
        $read->recipients()->attach($userA->id, ['type' => 'to', 'read_at' => now()]);

        $this->assertSame('1', CorrespondenceResource::getNavigationBadge());

        $userB = User::factory()->create();
        $this->actingAs($userB);

        $this->assertNull(CorrespondenceResource::getNavigationBadge());
    }

    // Reply row action — recipient defaults derived from the thread root, minus the replier

    public function test_create_page_defaults_recipients_to_and_cc_from_the_reply_parent_minus_the_replier(): void
    {
        $replier = User::factory()->create();
        $this->actingAs($replier);

        $otherTo = User::factory()->create(['name' => 'Other To Person']);
        $ccUser = User::factory()->create(['name' => 'Cc Person']);

        $parent = Correspondence::factory()->create();
        $parent->recipients()->attach($otherTo->id, ['type' => 'to']);
        $parent->recipients()->attach($replier->id, ['type' => 'to']);
        $parent->recipients()->attach($ccUser->id, ['type' => 'cc']);

        request()->query->set('parent_id', $parent->id);

        $this->assertSame([$otherTo->id], CorrespondenceResource::getRecipientsToField()->getDefaultState());
        $this->assertSame(['Cc Person'], CorrespondenceResource::getRecipientsCcField()->getDefaultState());
    }

    // Bulk mark as read

    public function test_bulk_mark_as_read_flips_read_at_for_every_selected_record(): void
    {
        $user = $this->actingAsUserWithPermissions(['correspondence.view']);

        $one = Correspondence::factory()->create();
        $one->recipients()->attach($user->id, ['type' => 'to', 'read_at' => null]);
        $two = Correspondence::factory()->create();
        $two->recipients()->attach($user->id, ['type' => 'to', 'read_at' => null]);

        Livewire::test(ListCorrespondences::class)
            ->callTableBulkAction('markAsRead', [$one, $two]);

        $this->assertNotNull($one->recipients()->wherePivot('user_id', $user->id)->first()->pivot->read_at);
        $this->assertNotNull($two->recipients()->wherePivot('user_id', $user->id)->first()->pivot->read_at);
    }

    // Unresolved CC name warning

    public function test_sync_recipients_returns_names_that_did_not_resolve_to_a_user(): void
    {
        $known = User::factory()->create(['name' => 'Known Person']);
        $record = Correspondence::factory()->create();

        $unresolved = CreateCorrespondence::syncRecipients($record, [], [$known->name, 'Nonexistent Person']);

        $this->assertSame(['Nonexistent Person'], $unresolved);
    }

    public function test_sync_recipients_returns_no_unresolved_names_when_every_cc_name_matches(): void
    {
        $known = User::factory()->create(['name' => 'Known Person']);
        $record = Correspondence::factory()->create();

        $unresolved = CreateCorrespondence::syncRecipients($record, [], [$known->name]);

        $this->assertSame([], $unresolved);
    }

    // Edit page save — warning notification fires only when a CC name fails to resolve
    // (uses ->set('data.x', ...) rather than fillForm() to avoid the §3d harness bug)

    public function test_edit_page_save_warns_about_unresolved_cc_names(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.edit', 'correspondence.view']);
        $status = $this->correspondenceStatus('WarningTestStatus');
        $known = User::factory()->create(['name' => 'Known Person']);
        $record = Correspondence::factory()->create(['status_id' => $status->id]);

        Livewire::test(EditCorrespondence::class, ['record' => $record->getRouteKey()])
            ->set('data.recipients_to', [$known->id])
            ->set('data.recipients_cc', ['Ghost Person'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified(__('resources/correspondence/strings.general.unresolved_cc_warning', ['names' => 'Ghost Person']));
    }

    public function test_edit_page_save_does_not_warn_when_every_cc_name_resolves(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.edit', 'correspondence.view']);
        $status = $this->correspondenceStatus('NoWarningTestStatus');
        $known = User::factory()->create(['name' => 'Known Person']);
        $record = Correspondence::factory()->create(['status_id' => $status->id]);

        Livewire::test(EditCorrespondence::class, ['record' => $record->getRouteKey()])
            ->set('data.recipients_to', [$known->id])
            ->set('data.recipients_cc', [$known->name])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($record->recipients()->wherePivot('user_id', $known->id)->exists());
    }

    // Exporter — plain write()-based CSV, comprehensive column coverage

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'correspondence_export_').'.csv';
        CorrespondenceExporter::write($query, $path);

        $csv = (string) file_get_contents($path);
        unlink($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", trim(ltrim($csv, "\xEF\xBB\xBF"))))));
        $header = str_getcsv($lines[0]);
        $rows = array_map(fn (string $line) => array_combine($header, str_getcsv($line)), array_slice($lines, 1));

        return ['header' => $header, 'rows' => $rows];
    }

    public function test_exporter_column_count_is_pinned(): void
    {
        // Pinned per app/Services/Imports/importsPattern.md — export column count must fail loudly on an
        // accidental shrink/grow, not silently drift (BankProfile's 38→12 regression was the reason this exists).
        $this->assertCount(17, CorrespondenceExporter::columnLabels());
    }

    public function test_exporter_write_emits_every_documented_column_with_resolved_values(): void
    {
        app()->setLocale('en');
        $status = $this->correspondenceStatus('ExportStatusCheck');
        $ro = RegisteredOrder::factory()->create();
        $creator = User::factory()->create(['name' => 'Export Creator']);
        $this->actingAs($creator);

        $root = Correspondence::factory()->forCorrespondable($ro)->create([
            'subject' => 'Root Export Subject',
            'body' => '<p>Root <strong>body</strong></p>',
            'type' => 'note',
            'priority' => 'high',
            'status_id' => $status->id,
            'is_internal' => true,
            'is_private' => false,
        ]);
        $toUser = User::factory()->create(['name' => 'Export To Person']);
        $ccUser = User::factory()->create(['name' => 'Export Cc Person']);
        $root->recipients()->attach($toUser->id, ['type' => 'to']);
        $root->recipients()->attach($ccUser->id, ['type' => 'cc']);

        $reply = Correspondence::factory()->create(['parent_id' => $root->id, 'subject' => 'Reply Export Subject']);

        $export = $this->exportToRows(Correspondence::whereIn('id', [$root->id, $reply->id]));

        $this->assertSame(array_values(CorrespondenceExporter::columnLabels()), $export['header']);

        $rootRow = collect($export['rows'])->first(fn ($row) => $row[__('resources/correspondence/strings.export.subject')] === 'Root Export Subject');
        $replyRow = collect($export['rows'])->first(fn ($row) => $row[__('resources/correspondence/strings.export.subject')] === 'Reply Export Subject');

        $this->assertNotNull($rootRow);
        $this->assertNotNull($replyRow);

        $this->assertSame('Note', $rootRow[__('resources/correspondence/strings.export.type')]);
        $this->assertSame('High', $rootRow[__('resources/correspondence/strings.export.priority')]);
        $this->assertSame('ExportStatusCheck', $rootRow[__('resources/correspondence/strings.export.status')]);
        $this->assertSame('Yes', $rootRow[__('resources/correspondence/strings.export.is_internal')]);
        $this->assertSame('No', $rootRow[__('resources/correspondence/strings.export.is_private')]);
        $this->assertSame('Root body', $rootRow[__('resources/correspondence/strings.export.body')]);
        $this->assertSame('Registered Order', $rootRow[__('resources/correspondence/strings.export.related_module')]);
        $this->assertSame(__('resources/correspondence/strings.export.thread_role_root'), $rootRow[__('resources/correspondence/strings.export.thread_role')]);
        $this->assertSame('', $rootRow[__('resources/correspondence/strings.export.parent_subject')]);
        $this->assertSame('Export Creator', $rootRow[__('resources/correspondence/strings.export.creator')]);
        $this->assertStringContainsString('Export To Person (To)', $rootRow[__('resources/correspondence/strings.export.recipients')]);
        $this->assertStringContainsString('Export Cc Person (CC)', $rootRow[__('resources/correspondence/strings.export.recipients')]);

        $this->assertSame(__('resources/correspondence/strings.export.thread_role_reply'), $replyRow[__('resources/correspondence/strings.export.thread_role')]);
        $this->assertSame('Root Export Subject', $replyRow[__('resources/correspondence/strings.export.parent_subject')]);
    }
}
