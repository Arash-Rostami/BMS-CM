<?php

namespace Tests\Feature\Services;

use App\Models\Permission;
use App\Models\Status;
use App\Models\User;
use App\Services\SmartCacheManager;
use App\Services\StatusWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StatusWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        SmartCacheManager::invalidate('Status');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        SmartCacheManager::invalidate('Status');
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

    private function makeStatus(string $type, string $name, ?int $stageOrder = null, ?string $permission = null): Status
    {
        return Status::factory()->create([
            'type' => $type,
            'english_type' => $type,
            'name' => $name,
            'english_name' => $name,
            'stage_order' => $stageOrder,
            'approval_permission' => $permission,
        ]);
    }

    public function test_initial_for_returns_the_status_with_the_lowest_stage_order(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $this->makeStatus($type, 'Second', 2);
        $first = $this->makeStatus($type, 'First', 1);
        $this->makeStatus($type, 'Unordered');

        $initial = StatusWorkflow::initialFor($type);

        $this->assertTrue($initial->is($first));
    }

    public function test_next_for_returns_the_following_stage(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $current = $this->makeStatus($type, 'Stage 1', 1);
        $next = $this->makeStatus($type, 'Stage 2', 2);

        $this->assertTrue(StatusWorkflow::nextFor($current)->is($next));
    }

    public function test_next_for_returns_null_when_current_is_unordered_or_the_last_stage(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $unordered = $this->makeStatus($type, 'Conditional');
        $last = $this->makeStatus($type, 'Stage 1', 1);

        $this->assertNull(StatusWorkflow::nextFor($unordered));
        $this->assertNull(StatusWorkflow::nextFor($last));
    }

    public function test_can_set_is_always_true_when_no_order_and_no_permission_required(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $target = $this->makeStatus($type, 'Conditional');

        $this->assertTrue(StatusWorkflow::canSet(null, $target, null));
    }

    public function test_can_set_allows_one_step_forward(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $current = $this->makeStatus($type, 'Stage 1', 1);
        $target = $this->makeStatus($type, 'Stage 2', 2);

        $this->assertTrue(StatusWorkflow::canSet(null, $target, $current));
    }

    public function test_can_set_rejects_skipping_a_stage(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $current = $this->makeStatus($type, 'Stage 1', 1);
        $this->makeStatus($type, 'Stage 2', 2);
        $target = $this->makeStatus($type, 'Stage 3', 3);

        $this->assertFalse(StatusWorkflow::canSet(null, $target, $current));
    }

    public function test_can_set_allows_ordered_transition_when_required_permission_is_granted(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $permission = Permission::factory()->create();
        $current = $this->makeStatus($type, 'Stage 1', 1);
        $target = $this->makeStatus($type, 'Stage 2', 2, $permission->name);

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $this->assertTrue(StatusWorkflow::canSet($user, $target, $current));
    }

    public function test_can_set_rejects_ordered_transition_when_required_permission_is_missing(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $permission = Permission::factory()->create();
        $current = $this->makeStatus($type, 'Stage 1', 1);
        $target = $this->makeStatus($type, 'Stage 2', 2, $permission->name);

        $user = User::factory()->create();

        $this->assertFalse(StatusWorkflow::canSet($user, $target, $current));
        $this->assertFalse(StatusWorkflow::canSet(null, $target, $current));
    }

    public function test_can_set_allows_null_current_transitioning_to_the_initial_status(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $initial = $this->makeStatus($type, 'Stage 1', 1);
        $this->makeStatus($type, 'Stage 2', 2);

        $this->assertTrue(StatusWorkflow::canSet(null, $initial, null));
    }

    public function test_can_set_rejects_null_current_transitioning_to_a_non_initial_status(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $this->makeStatus($type, 'Stage 1', 1);
        $second = $this->makeStatus($type, 'Stage 2', 2);

        $this->assertFalse(StatusWorkflow::canSet(null, $second, null));
    }

    public function test_assert_allowed_throws_validation_exception_when_transition_is_not_allowed(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $this->makeStatus($type, 'Stage 1', 1);
        $target = $this->makeStatus($type, 'Stage 3', 3);

        $this->expectException(ValidationException::class);

        StatusWorkflow::assertAllowed(null, $target, null);
    }

    public function test_assert_allowed_is_silent_when_transition_is_allowed(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $current = $this->makeStatus($type, 'Stage 1', 1);
        $target = $this->makeStatus($type, 'Stage 2', 2);

        StatusWorkflow::assertAllowed(null, $target, $current);

        $this->assertTrue(true);
    }

    public function test_is_terminal_is_true_for_the_highest_stage_order(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $this->makeStatus($type, 'Stage 1', 1);
        $last = $this->makeStatus($type, 'Stage 2', 2);

        $this->assertTrue(StatusWorkflow::isTerminal($last));
    }

    public function test_is_terminal_is_false_for_a_non_final_ordered_stage(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $first = $this->makeStatus($type, 'Stage 1', 1);
        $this->makeStatus($type, 'Stage 2', 2);

        $this->assertFalse(StatusWorkflow::isTerminal($first));
    }

    public function test_is_terminal_is_false_for_an_unordered_status(): void
    {
        $type = 'Workflow Test Type '.uniqid();
        $unordered = $this->makeStatus($type, 'Conditional');

        $this->assertFalse(StatusWorkflow::isTerminal($unordered));
    }
}
