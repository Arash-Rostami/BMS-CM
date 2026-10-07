<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\EntityAttributeResource;
use App\Filament\Resources\Master\EntityAttributeResource\Pages\ManageEntityAttributes;
use App\Models\Department;
use App\Models\EntityAttribute;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class EntityAttributeResourceTest extends TestCase
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

        $role = Role::create(['name' => 'agent_test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function createEntityAttribute(array $overrides = []): EntityAttribute
    {
        return EntityAttribute::factory()->forEntity(Department::factory()->create())->create($overrides);
    }

    // Permissions

    public function test_full_permissions_allow_viewing_and_deleting(): void
    {
        $this->actingAsUserWithPermissions(['entity_attribute.view', 'entity_attribute.delete', 'entity_attribute.restore']);

        $record = $this->createEntityAttribute();

        $this->assertTrue(EntityAttributeResource::canViewAny());
        $this->assertTrue(EntityAttributeResource::canDelete($record));
        $this->assertTrue(EntityAttributeResource::canRestore($record));
    }

    public function test_resource_does_not_use_has_resource_permissions_and_is_therefore_ungated(): void
    {
        // Known gap, not asserted-as-desired behavior: unlike every other Filament resource in
        // this codebase, EntityAttributeResource does not `use HasResourcePermissions`, so its
        // can*() checks fall through to Filament's allow-everything vendor default (no Policies
        // exist in this project — see filamentPattern.md §1.5). This pins the CURRENT behavior
        // so a future silent change is visible; closing the gap is a resource-wiring decision
        // for the Lead/user, not something to fix inline while adding test coverage.
        $this->actingAsUserWithPermissions([]);

        $record = $this->createEntityAttribute();

        $this->assertTrue(EntityAttributeResource::canViewAny());
        $this->assertTrue(EntityAttributeResource::canDelete($record));
        $this->assertTrue(EntityAttributeResource::canRestore($record));
    }

    // View-only — no create/edit action exists at all

    public function test_resource_is_view_only_with_no_create_or_edit_action(): void
    {
        $this->actingAsUserWithPermissions(['entity_attribute.view', 'entity_attribute.delete']);

        $livewire = Livewire::test(ManageEntityAttributes::class);

        $livewire->assertActionDoesNotExist('create');
        $livewire->assertTableActionExists('view');
        $livewire->assertTableActionDoesNotExist('edit');
    }

    // Ungated resource, so Create/Edit must be denied explicitly or the footer buttons leak through

    public function test_create_and_edit_authorization_responses_are_denied(): void
    {
        $record = EntityAttribute::factory()->forEntity(User::factory()->create())->create();

        $this->assertTrue(EntityAttributeResource::getCreateAuthorizationResponse()->denied());
        $this->assertTrue(EntityAttributeResource::getEditAuthorizationResponse($record)->denied());
        $this->assertTrue(EntityAttributeResource::getUpdateAuthorizationResponse($record)->denied());
        $this->assertFalse(EntityAttributeResource::canCreate());
        $this->assertFalse(EntityAttributeResource::canEdit($record));
    }

    // List — search

    public function test_manage_page_renders_and_search_finds_by_key(): void
    {
        $this->actingAsUserWithPermissions(['entity_attribute.view']);

        $target = $this->createEntityAttribute(['key' => 'SEARCH-TARGET-KEY']);
        $other = $this->createEntityAttribute(['key' => 'SEARCH-OTHER-KEY']);

        Livewire::test(ManageEntityAttributes::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable('SEARCH-TARGET-KEY')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Filters

    public function test_key_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['entity_attribute.view']);

        $target = $this->createEntityAttribute(['key' => 'FILTER-KEY-ONE']);
        $other = $this->createEntityAttribute(['key' => 'FILTER-KEY-TWO']);

        Livewire::test(ManageEntityAttributes::class)
            ->filterTable('key', 'FILTER-KEY-ONE')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_entity_type_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['entity_attribute.view']);

        $department = $this->createEntityAttribute();
        $otherEntity = EntityAttribute::factory()->forEntity(User::factory()->create())->create();

        Livewire::test(ManageEntityAttributes::class)
            ->filterTable('entity_type', Department::class)
            ->assertCanSeeTableRecords([$department])
            ->assertCanNotSeeTableRecords([$otherEntity]);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['entity_attribute.view', 'entity_attribute.delete', 'entity_attribute.restore']);
        $record = $this->createEntityAttribute();

        Livewire::test(ManageEntityAttributes::class)
            ->callTableAction('delete', $record);

        $this->assertNull(EntityAttribute::find($record->id));
        $this->assertTrue(EntityAttribute::withTrashed()->find($record->id)->trashed());

        Livewire::test(ManageEntityAttributes::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(EntityAttribute::find($record->id));
    }

    // 2026-10-07 QA follow-up

    public function test_table_shows_a_badge_styled_id_column(): void
    {
        $this->actingAsUserWithPermissions(['entity_attribute.view']);

        $column = EntityAttributeResource::showId();

        $this->assertTrue($column->isBadge());
    }

    public function test_owner_link_resolves_for_mapped_live_records_and_hides_otherwise(): void
    {
        $this->actingAsUserWithPermissions(['shipment.edit']);

        $shipment = Shipment::factory()->create();
        $mapped = EntityAttribute::factory()->forEntity($shipment)->create();
        $unmapped = $this->createEntityAttribute();

        $this->assertSame(
            route('filament.dashboard.resources.shipments.edit', ['record' => $shipment->id]),
            EntityAttributeResource::ownerUrl($mapped)
        );
        $this->assertStringEndsWith('#'.$shipment->id, EntityAttributeResource::ownerLabel($mapped));
        $this->assertNull(EntityAttributeResource::ownerUrl($unmapped));

        $gone = Shipment::factory()->create();
        $orphan = EntityAttribute::factory()->forEntity($gone)->create();
        $gone->delete();

        $this->assertNull(EntityAttributeResource::ownerUrl($orphan));
    }

    public function test_owner_link_is_hidden_when_the_viewer_cannot_edit_the_owner_resource(): void
    {
        $this->actingAsUserWithPermissions(['entity_attribute.view']);

        $mapped = EntityAttribute::factory()->forEntity(Shipment::factory()->create())->create();

        $this->assertNull(EntityAttributeResource::ownerUrl($mapped));
    }

    public function test_owner_url_batches_existence_checks_per_type(): void
    {
        $user = $this->actingAsUserWithPermissions(['shipment.edit']);
        $user->can('shipment.edit');

        $a = EntityAttribute::factory()->forEntity(Shipment::factory()->create())->create();
        $b = EntityAttribute::factory()->forEntity(Shipment::factory()->create())->create();
        $page = collect([$a, $b]);

        DB::enableQueryLog();
        EntityAttributeResource::ownerUrl($a, $page);
        EntityAttributeResource::ownerUrl($b, $page);

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_key_column_is_an_info_badge(): void
    {
        $column = EntityAttributeResource::showKey();

        $this->assertTrue($column->isBadge());
        $this->assertSame('info', $column->getColor(null));
    }

    public function test_filter_options_are_cached_and_invalidated_on_save(): void
    {
        $this->actingAsUserWithPermissions(['entity_attribute.view']);
        $this->createEntityAttribute(['key' => 'CACHE-KEY-ONE']);

        $first = EntityAttributeResource::getKeyFilter()->getOptions();
        $this->assertArrayHasKey('CACHE-KEY-ONE', $first);

        DB::table('entity_attributes')->insert([
            'entity_type' => Department::class, 'entity_id' => 1, 'key' => 'CACHE-KEY-RAW',
            'value' => '"x"', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertArrayNotHasKey('CACHE-KEY-RAW', EntityAttributeResource::getKeyFilter()->getOptions());

        $this->createEntityAttribute(['key' => 'CACHE-KEY-TWO']);
        $fresh = EntityAttributeResource::getKeyFilter()->getOptions();
        $this->assertArrayHasKey('CACHE-KEY-RAW', $fresh);
        $this->assertArrayHasKey('CACHE-KEY-TWO', $fresh);
    }

    public function test_infolist_renders_a_complex_value_as_labelled_key_value_rows(): void
    {
        $record = $this->createEntityAttribute([
            'value' => [
                'currency' => 'Euro',
                'seller_name' => 'Solsun',
                'eta' => null,
                'items' => [
                    ['unit' => 'kg', 'description' => null],
                ],
            ],
        ]);

        $rendered = (string) EntityAttributeResource::viewValue()->model(EntityAttribute::class)->formatState($record->value);

        // Humanized labels, LTR-forced (keys are always technical English)
        $this->assertStringContainsString('dir="ltr"', $rendered);
        $this->assertStringContainsString('Currency', $rendered);
        $this->assertStringContainsString('Euro', $rendered);
        $this->assertStringContainsString('Seller Name', $rendered);
        $this->assertStringContainsString('Solsun', $rendered);

        // Null entries are dropped entirely, at any depth
        $this->assertStringNotContainsString('Eta', $rendered);
        $this->assertStringNotContainsString('Description', $rendered);

        // List items never get a numeric index label
        $this->assertStringNotContainsString('>0<', $rendered);
        $this->assertStringContainsString('Unit', $rendered);
    }
}
