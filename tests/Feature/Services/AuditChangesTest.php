<?php

namespace Tests\Feature\Services;

use App\Models\Department;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditChanges;
use App\Services\NameSearch;
use App\Services\PermissionLabeler;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditChangesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        app()->setLocale('en');
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

    private function service(): AuditChanges
    {
        return app(AuditChanges::class);
    }

    private function activity(array $new, array $old = [], string $class = PurchaseRequest::class, int $id = 1): Activity
    {
        return (new Activity)->forceFill([
            'id' => random_int(1_000_000, 9_999_999),
            'subject_type' => $class,
            'subject_id' => $id,
            'event' => 'updated',
            'properties' => ['attributes' => $new, 'old' => $old],
        ]);
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

    public function test_the_audited_model_registry_lists_eight_models_with_proforma_invoice_and_without_correspondence(): void
    {
        $this->assertCount(8, AuditChanges::MODELS);
        $this->assertContains(\App\Models\ProformaInvoice::class, AuditChanges::MODELS);
        $this->assertNotContains(\App\Models\Correspondence::class, AuditChanges::MODELS);
    }

    public function test_foreign_keys_render_as_names_and_fall_back_to_the_id(): void
    {
        $old = Department::factory()->create();
        $new = Department::factory()->create();
        $activity = $this->activity(['department_id' => $new->id], ['department_id' => $old->id]);

        $row = $this->service()->fields(collect([$activity]))[$activity->id][0];

        $this->assertSame(NameSearch::label($old), $row['old']);
        $this->assertSame(NameSearch::label($new), $row['new']);

        $missing = $this->activity(['department_id' => 987654321]);

        $this->assertSame('987654321', $this->service()->fields(collect([$missing]))[$missing->id][0]['new']);
    }

    public function test_foreign_key_labels_are_batched_into_one_query_per_column(): void
    {
        $a = Department::factory()->create();
        $b = Department::factory()->create();
        $activities = collect([
            $this->activity(['department_id' => $a->id]),
            $this->activity(['department_id' => $b->id], ['department_id' => $a->id]),
            $this->activity(['department_id' => $a->id]),
        ]);
        $this->service()->fields(collect([$this->activity(['notes' => 'warm'])]));

        DB::enableQueryLog();
        $this->service()->fields($activities);
        $departmentQueries = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'from `departments`'));

        $this->assertCount(1, $departmentQueries);
    }

    public function test_values_render_null_bool_array_and_long_text_safely(): void
    {
        $activity = $this->activity(['notes' => str_repeat('x', 500), 'rejection_reason' => ['a' => 1]], ['notes' => null]);

        $rows = collect($this->service()->fields(collect([$activity]))[$activity->id])->keyBy('label');

        $notes = $rows->first(fn (array $row): bool => $row['old'] === '-');
        $this->assertNotNull($notes);
        $this->assertLessThanOrEqual(203, mb_strlen($notes['new']));
        $this->assertSame('{"a":1}', $rows->last()['new']);
    }

    public function test_decimals_lose_padding_and_model_class_strings_render_as_module_labels(): void
    {
        $activity = $this->activity(['total_estimated_cost' => '12.50000', 'urgency_level' => '1.50', 'rejection_reason' => PurchaseRequest::class]);

        $rows = collect($this->service()->fields(collect([$activity]))[$activity->id])->pluck('new')->all();

        $this->assertSame(['12.5', '1.50', PermissionLabeler::getEntityLabel(PurchaseRequest::class)], $rows);
    }

    public function test_summary_is_memoised_for_saved_activities_only(): void
    {
        $record = PurchaseRequest::factory()->create();
        $saved = Activity::query()->inLog('audit')->where('subject_id', $record->id)->where('subject_type', PurchaseRequest::class)->first();
        $first = $this->service()->summary($saved);
        $saved->properties = collect(['attributes' => ['urgency_level' => 'high']]);

        $this->assertSame($first, $this->service()->summary($saved));

        $unsaved = $this->activity(['notes' => 'x']);
        $before = $this->service()->summary($unsaved);
        $unsaved->properties = collect(['attributes' => ['urgency_level' => 'high']]);

        $this->assertNotSame($before, $this->service()->summary($unsaved));
    }

    public function test_utc_iso_dates_render_in_the_application_timezone_without_a_shifted_day(): void
    {
        config(['app.timezone' => 'Asia/Tehran']);
        session(['calendar_type' => 'gregorian']);
        $activity = $this->activity(['required_by_date' => '2026-10-17T20:30:00.000000Z']);

        $row = $this->service()->fields(collect([$activity]))[$activity->id][0];

        $this->assertStringContainsString('18', $row['new']);
        $this->assertStringNotContainsString(':', $row['new']);
    }

    public function test_deleted_at_is_hidden_and_the_summary_caps_at_three_labels(): void
    {
        $hidden = $this->activity(['deleted_at' => '2026-10-18 10:00:00']);
        $many = $this->activity(['notes' => 'a', 'urgency_level' => 'high', 'rejection_reason' => 'c', 'department_id' => 1, 'cost_center_id' => 2]);

        $this->assertSame([], $this->service()->fields(collect([$hidden]))[$hidden->id]);
        $this->assertSame('', $this->service()->summary($hidden));
        $this->assertStringEndsWith(' +2', $this->service()->summary($many));
        $this->assertCount(3, explode(', ', $this->service()->summary($many)));
    }

    public function test_record_label_uses_the_identifier_and_falls_back_to_the_id(): void
    {
        $record = PurchaseRequest::factory()->create();
        $activity = Activity::query()->inLog('audit')->where('subject_id', $record->id)->where('subject_type', PurchaseRequest::class)->with('subject')->first();

        $this->assertSame($record->pr_number, $this->service()->record($activity));
        $this->assertSame('#987654321', $this->service()->record($this->activity(['notes' => 'x'], [], PurchaseRequest::class, 987654321)));
    }

    public function test_record_url_needs_a_live_subject_and_edit_permission(): void
    {
        $record = PurchaseRequest::factory()->create();
        $load = fn (): Activity => Activity::query()->inLog('audit')->where('subject_id', $record->id)->where('subject_type', PurchaseRequest::class)->with(['subject' => fn ($q) => $q->withTrashed()])->first();

        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $this->assertNull($this->service()->url($load()));

        $this->actingAsUserWithPermissions(['purchase_request.view', 'purchase_request.edit']);
        $this->assertSame(route('filament.dashboard.resources.purchase-requests.edit', ['record' => $record->id]), $this->service()->url($load()));

        $record->delete();
        $this->assertNull($this->service()->url($load()));
    }
}
