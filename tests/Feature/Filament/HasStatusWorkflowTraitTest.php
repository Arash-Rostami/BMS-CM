<?php

namespace Tests\Feature\Filament;

use App\Filament\Traits\HasStatusWorkflow;
use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\Status;
use App\Services\SmartCacheManager;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HasStatusWorkflowTraitTest extends TestCase
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

    private function makeStatus(string $type, string $name, ?int $stageOrder = null): Status
    {
        return Status::factory()->create([
            'type' => $type,
            'english_type' => $type,
            'name' => $name,
            'english_name' => $name,
            'stage_order' => $stageOrder,
        ]);
    }

    public function test_status_workflow_columns_defaults_to_status_id(): void
    {
        $resource = new class
        {
            use HasStatusWorkflow;
        };

        $this->assertSame(['status_id'], $resource::statusWorkflowColumns());
    }

    public function test_status_workflow_columns_is_overridable_for_multi_column_models(): void
    {
        $resource = new class
        {
            use HasStatusWorkflow;

            public static function statusWorkflowColumns(): array
            {
                return ['status_id', 'container_status_id', 'operation_status_id'];
            }
        };

        $this->assertSame(['status_id', 'container_status_id', 'operation_status_id'], $resource::statusWorkflowColumns());
    }

    public function test_apply_initial_status_on_create_sets_the_column_when_an_initial_status_exists(): void
    {
        $type = 'Trait Test Type '.uniqid();
        $initial = $this->makeStatus($type, 'Stage 1', 1);
        $this->makeStatus($type, 'Stage 2', 2);

        $resource = new class
        {
            use HasStatusWorkflow;

            public static string $type = '';

            public static function statusWorkflowType(): string
            {
                return self::$type;
            }
        };
        $resource::$type = $type;

        $data = $resource::applyInitialStatusOnCreate([]);

        $this->assertSame($initial->id, $data['status_id']);
    }

    public function test_apply_initial_status_on_create_is_a_no_op_for_an_ungated_type(): void
    {
        $type = 'Trait Test Type '.uniqid();
        $this->makeStatus($type, 'Conditional');

        $resource = new class
        {
            use HasStatusWorkflow;

            public static string $type = '';

            public static function statusWorkflowType(): string
            {
                return self::$type;
            }
        };
        $resource::$type = $type;

        $data = $resource::applyInitialStatusOnCreate(['status_id' => 'unchanged']);

        $this->assertSame(['status_id' => 'unchanged'], $data);
    }

    public function test_apply_initial_status_on_create_accepts_an_explicit_type_override_for_multi_column_resources(): void
    {
        $defaultType = 'Trait Test Default Type '.uniqid();
        $overrideType = 'Trait Test Override Type '.uniqid();
        $this->makeStatus($defaultType, 'Default Stage 1', 1);
        $overrideInitial = $this->makeStatus($overrideType, 'Override Stage 1', 1);

        $resource = new class
        {
            use HasStatusWorkflow;

            public static string $type = '';

            public static function statusWorkflowType(): string
            {
                return self::$type;
            }
        };
        $resource::$type = $defaultType;

        $data = $resource::applyInitialStatusOnCreate([], 'container_status_id', $overrideType);

        $this->assertSame($overrideInitial->id, $data['container_status_id']);
    }

    private function customStatusRecord(int $statusId): PurchaseRequest
    {
        $base = PurchaseRequest::factory()->create([
            'status_id' => $statusId,
            'cost_center_id' => Department::factory()->create()->id,
        ]);

        $record = new class extends PurchaseRequest
        {
            public function customStatus(): BelongsTo
            {
                return $this->belongsTo(Status::class, 'status_id');
            }
        };
        $record->forceFill($base->toArray());
        $record->exists = true;

        return $record;
    }

    public function test_assert_status_transition_allowed_reports_the_configured_column_for_a_non_default_column(): void
    {
        $type = 'Trait Test Type '.uniqid();
        $current = $this->makeStatus($type, 'Stage 1', 1);
        $target = $this->makeStatus($type, 'Stage 3', 3);
        $this->makeStatus($type, 'Stage 2', 2);

        $record = $this->customStatusRecord($current->id);

        $resource = new class
        {
            use HasStatusWorkflow;
        };

        try {
            $resource::assertStatusTransitionAllowed($record, 'custom_status_id', $target->id);
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('custom_status_id', $e->errors());
        }
    }

    public function test_assert_status_transition_allowed_is_silent_for_a_valid_transition_on_a_non_default_column(): void
    {
        $type = 'Trait Test Type '.uniqid();
        $current = $this->makeStatus($type, 'Stage 1', 1);
        $next = $this->makeStatus($type, 'Stage 2', 2);

        $record = $this->customStatusRecord($current->id);

        $resource = new class
        {
            use HasStatusWorkflow;
        };

        $resource::assertStatusTransitionAllowed($record, 'custom_status_id', $next->id);

        $this->assertTrue(true);
    }

    public function test_status_workflow_progress_computes_percent_and_fraction_for_a_mid_pipeline_record(): void
    {
        $type = 'Trait Test Type '.uniqid();
        $this->makeStatus($type, 'Stage 1', 1);
        $current = $this->makeStatus($type, 'Stage 2', 2);
        $this->makeStatus($type, 'Stage 3', 3);
        $this->makeStatus($type, 'Stage 4', 4);

        $record = $this->customStatusRecord($current->id);

        $resource = new class
        {
            use HasStatusWorkflow;
        };

        $method = new \ReflectionMethod($resource, 'statusWorkflowProgress');
        $method->setAccessible(true);

        $result = $method->invoke(null, $record, 'customStatus', $type);

        $this->assertSame(50, $result['percent']);
        $this->assertSame('2/4', $result['fraction']);
    }

    public function test_status_workflow_progress_is_null_when_the_type_has_no_ordered_stages(): void
    {
        $type = 'Trait Test Type '.uniqid();
        $current = $this->makeStatus($type, 'Only Status');

        $record = $this->customStatusRecord($current->id);

        $resource = new class
        {
            use HasStatusWorkflow;
        };

        $method = new \ReflectionMethod($resource, 'statusWorkflowProgress');
        $method->setAccessible(true);

        $result = $method->invoke(null, $record, 'customStatus', $type);

        $this->assertNull($result['percent']);
        $this->assertNull($result['fraction']);
    }

    public function test_status_workflow_progress_query_is_cached_not_reissued_per_row(): void
    {
        $type = 'Trait Test Type '.uniqid();
        $first = $this->makeStatus($type, 'Stage 1', 1);
        $this->makeStatus($type, 'Stage 2', 2);
        SmartCacheManager::invalidate('Status');

        $recordOne = $this->customStatusRecord($first->id);
        $recordTwo = $this->customStatusRecord($first->id);
        $recordTwo->customStatus;

        $resource = new class
        {
            use HasStatusWorkflow;
        };

        $method = new \ReflectionMethod($resource, 'statusWorkflowProgress');
        $method->setAccessible(true);
        $method->invoke(null, $recordOne, 'customStatus', $type);

        DB::enableQueryLog();
        $method->invoke(null, $recordTwo, 'customStatus', $type);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queryCount);
    }

    public function test_pipeline_data_treats_the_actual_lowest_stage_order_as_the_initial_stage_not_a_hardcoded_one(): void
    {
        app()->setLocale('en');
        $type = 'Trait Test Type '.uniqid();
        $this->makeStatus($type, 'Kickoff', 2);
        $this->makeStatus($type, 'Review', 3);

        $resource = new class
        {
            use HasStatusWorkflow;

            public static string $type = '';

            public static function statusWorkflowType(): string
            {
                return self::$type;
            }
        };
        $resource::$type = $type;

        $method = new \ReflectionMethod($resource, 'statusWorkflowPipelineData');
        $method->setAccessible(true);

        $data = $method->invoke(null, null);

        $this->assertSame('', $data['ordered'][0]['icon']);
        $this->assertSame(2, $data['ordered'][0]['order']);
        $this->assertSame(__('resources/general/strings.status_workflow.pipeline_automatic'), $data['ordered'][0]['responsible']);
        $this->assertSame('🔒', $data['ordered'][1]['icon']);
        $this->assertSame(3, $data['ordered'][1]['order']);
    }

    public function test_pipeline_data_carries_localized_names_and_responsible_text_for_fa(): void
    {
        app()->setLocale('fa');
        $type = 'Trait Test Type '.uniqid();
        $this->makeStatus($type, 'Kickoff', 2);
        $this->makeStatus($type, 'Review', 3);

        $resource = new class
        {
            use HasStatusWorkflow;

            public static string $type = '';

            public static function statusWorkflowType(): string
            {
                return self::$type;
            }
        };
        $resource::$type = $type;

        $method = new \ReflectionMethod($resource, 'statusWorkflowPipelineData');
        $method->setAccessible(true);

        $data = $method->invoke(null, null);

        $this->assertSame('Kickoff', $data['ordered'][0]['name']);
        $this->assertSame(__('resources/general/strings.status_workflow.pipeline_automatic'), $data['ordered'][0]['responsible']);
        $this->assertSame('Review', $data['ordered'][1]['name']);
    }
}
