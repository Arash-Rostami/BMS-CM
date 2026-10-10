<?php

namespace Tests\Feature\Models;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\CalendarColor;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility;
use App\Models\CalendarRule;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CalendarRuleModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        app()->setLocale('en');
        Queue::fake(); // observers are live: never let a dispatch run inline under QUEUE_CONNECTION=sync
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

    private function userWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin_junior');

        return $user;
    }

    private function rule(array $overrides = []): CalendarRule
    {
        return CalendarRule::factory()->create($overrides);
    }

    // Guards

    public function test_creating_without_a_user_id_outside_an_authenticated_context_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->rule(['user_id' => null]);
    }

    public function test_creating_with_an_explicit_user_id_is_allowed_without_authentication(): void
    {
        $record = $this->rule();

        $this->assertNotNull($record->fresh()->user_id);
    }

    // Casts

    public function test_enum_and_json_casts_roundtrip(): void
    {
        $record = $this->rule([
            'type' => RuleType::ACTION->value,
            'visibility' => Visibility::USERS->value,
            'color' => CalendarColor::TEAL->value,
            'lead_times' => [7, 3, 1],
            'shared_user_ids' => [12, 34],
            'on_day' => false,
        ]);

        $record = $record->fresh();

        $this->assertSame(RuleType::ACTION, $record->type);
        $this->assertSame(Visibility::USERS, $record->visibility);
        $this->assertSame(CalendarColor::TEAL, $record->color);
        $this->assertSame([7, 3, 1], $record->lead_times);
        $this->assertSame([12, 34], $record->shared_user_ids);
        $this->assertFalse($record->on_day);
        $this->assertSame('--cal-color-teal', $record->color->cssVar());
    }

    // Scope: active

    public function test_stamp_columns_carry_the_conventional_named_indexes(): void
    {
        $names = collect(Schema::getIndexes('calendar_rules'))->pluck('name');

        $this->assertTrue($names->contains('idx_calendar_rules_user_id'));
        $this->assertTrue($names->contains('idx_calendar_rules_updated_by_id'));
    }

    public function test_scope_active_filters_inactive_rules(): void
    {
        $active = $this->rule(['is_active' => true]);
        $inactive = $this->rule(['is_active' => false]);

        $this->assertTrue(CalendarRule::active()->whereKey($active->id)->exists());
        $this->assertFalse(CalendarRule::active()->whereKey($inactive->id)->exists());
    }

    // Visibility

    public function test_visible_to_sees_own_everyone_and_shared_rules(): void
    {
        $owner = $this->userWithPermissions([]);
        $other = $this->userWithPermissions([]);

        $own = $this->rule(['user_id' => $owner->id, 'visibility' => Visibility::ME->value]);
        $sharedInt = $this->rule(['user_id' => 999, 'visibility' => Visibility::USERS->value, 'shared_user_ids' => [$other->id]]);
        $sharedString = $this->rule(['user_id' => 999, 'visibility' => Visibility::USERS->value, 'shared_user_ids' => [(string) $other->id]]);
        $everyone = $this->rule(['user_id' => 999, 'visibility' => Visibility::EVERYONE->value]);
        $private = $this->rule(['user_id' => 999, 'visibility' => Visibility::ME->value]);

        $visible = CalendarRule::visibleTo($other)->pluck('id');

        $this->assertTrue($visible->contains($sharedInt->id));
        $this->assertTrue($visible->contains($sharedString->id));
        $this->assertTrue($visible->contains($everyone->id));
        $this->assertFalse($visible->contains($own->id));
        $this->assertFalse($visible->contains($private->id));
    }

    public function test_visible_to_shows_admin_every_rule(): void
    {
        $admin = $this->admin();
        $private = $this->rule(['user_id' => 999, 'visibility' => Visibility::ME->value]);

        $this->assertTrue(CalendarRule::visibleTo($admin)->whereKey($private->id)->exists());
    }

    public function test_shared_membership_stops_matching_once_visibility_leaves_users(): void
    {
        $sharedUser = $this->userWithPermissions([]);
        $otherOwner = $this->userWithPermissions([]);

        $record = $this->rule([
            'user_id' => 999,
            'visibility' => Visibility::USERS->value,
            'shared_user_ids' => [$sharedUser->id],
        ]);

        $this->assertTrue(CalendarRule::visibleTo($sharedUser)->whereKey($record->id)->exists());

        $record->update(['visibility' => Visibility::ME->value, 'user_id' => $otherOwner->id]);

        $this->assertFalse(CalendarRule::visibleTo($sharedUser)->whereKey($record->id)->exists());

        $record->update(['visibility' => Visibility::EVERYONE->value]);

        $this->assertTrue(CalendarRule::visibleTo($sharedUser)->whereKey($record->id)->exists());
    }

    public function test_shared_user_ids_are_stored_as_ints(): void
    {
        $user = $this->userWithPermissions([]);

        $record = $this->rule([
            'visibility' => Visibility::USERS->value,
            'shared_user_ids' => [(string) $user->id, '77', 5],
        ]);

        $raw = json_decode(
            DB::table('calendar_rules')->where('id', $record->id)->value('shared_user_ids'),
            true
        );

        $this->assertSame([$user->id, 77, 5], $raw);
        $this->assertSame([$user->id, 77, 5], $record->fresh()->shared_user_ids);
    }

    // Editability

    public function test_is_editable_by_allows_owner_and_admin_only(): void
    {
        $owner = $this->userWithPermissions([]);
        $stranger = $this->userWithPermissions([]);
        $record = $this->rule(['user_id' => $owner->id]);

        $this->assertTrue($record->isEditableBy($owner));
        $this->assertTrue($record->isEditableBy($this->admin()));
        $this->assertFalse($record->isEditableBy($stranger));
    }

    // Recipients

    public function test_recipients_filters_by_module_permission_and_excludes_inactive_users(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $permitted = $this->userWithPermissions(['purchase_request.view']);
        $unpermitted = $this->userWithPermissions(['shipment.view']);
        $inactive = $this->userWithPermissions(['purchase_request.view']);
        $inactive->forceFill(['status' => 'inactive'])->save();

        $record = $this->rule([
            'user_id' => $owner->id,
            'subject' => PurchaseRequest::class,
            'visibility' => Visibility::USERS->value,
            'shared_user_ids' => [$permitted->id, $unpermitted->id, $inactive->id],
        ]);

        $recipients = $record->recipients();

        $this->assertTrue($recipients->contains($owner));
        $this->assertTrue($recipients->contains($permitted));
        $this->assertFalse($recipients->contains($unpermitted));
        $this->assertFalse($recipients->contains($inactive));
    }

    public function test_recipients_everyone_reaches_all_permitted_users(): void
    {
        $permitted = $this->userWithPermissions(['purchase_request.view']);
        $unpermitted = $this->userWithPermissions([]);

        $record = $this->rule([
            'subject' => PurchaseRequest::class,
            'visibility' => Visibility::EVERYONE->value,
        ]);

        $recipients = $record->recipients();

        $this->assertTrue($recipients->contains($permitted));
        $this->assertFalse($recipients->contains($unpermitted));
    }

    // Fingerprints

    private function ruleWithFilters(array $rules): CalendarRule
    {
        return $this->rule([
            'filters' => ['rules' => $rules],
        ]);
    }

    public function test_fingerprints_ignore_item_uuids_and_rule_order(): void
    {
        $one = $this->ruleWithFilters([
            'aaaa1111-1111-4111-8111-111111111111' => ['type' => 'name', 'data' => ['operator' => 'contains', 'value' => 'x']],
            'bbbb2222-2222-4222-8222-222222222222' => ['type' => 'code', 'data' => ['operator' => 'equals', 'value' => 'y']],
        ]);

        $two = $this->ruleWithFilters([
            'zzzz9999-9999-4999-8999-999999999999' => ['type' => 'code', 'data' => ['operator' => 'equals', 'value' => 'y']],
            'yyyy8888-8888-4888-8888-888888888888' => ['type' => 'name', 'data' => ['operator' => 'contains', 'value' => 'x']],
        ]);

        $one->computeFingerprints();
        $two->computeFingerprints();

        $this->assertSame($one->fingerprint, $two->fingerprint);
        $this->assertSame($one->conditions_hash, $two->conditions_hash);
    }

    public function test_fingerprint_changes_with_timing_but_conditions_hash_does_not(): void
    {
        $base = $this->ruleWithFilters([
            'aaaa1111-1111-4111-8111-111111111111' => ['type' => 'name', 'data' => ['operator' => 'contains', 'value' => 'x']],
        ]);

        $shifted = $this->ruleWithFilters([
            'aaaa1111-1111-4111-8111-111111111111' => ['type' => 'name', 'data' => ['operator' => 'contains', 'value' => 'x']],
        ]);

        $base->computeFingerprints();
        $shifted->computeFingerprints();
        $conditionsHash = $base->conditions_hash;

        $shifted->day_shift = 2;
        $shifted->type = RuleType::ACTION;
        $shifted->computeFingerprints();

        $this->assertSame($conditionsHash, $shifted->conditions_hash);
        $this->assertNotSame($base->fingerprint, $shifted->fingerprint);
    }

    public function test_canonical_or_blocks_normalize_group_keys(): void
    {
        $tree = [
            'rules' => [
                '11111111-1111-4111-8111-111111111111' => [
                    'type' => 'or',
                    'data' => [
                        'groups' => [
                            '22222222-2222-4222-8222-222222222222' => ['rules' => ['a' => ['type' => 'name', 'data' => ['operator' => 'equals', 'value' => 'x']]]],
                            '33333333-3333-4333-8333-333333333333' => ['rules' => ['b' => ['type' => 'code', 'data' => ['operator' => 'equals', 'value' => 'y']]]],
                        ],
                    ],
                ],
            ],
        ];

        $canonical = CalendarRule::canonical($tree);

        $this->assertIsList($canonical['rules']);
        $groups = $canonical['rules'][0]['data']['groups'];
        $this->assertIsList($groups);
        $this->assertIsList($groups[0]['rules']);
    }
}
