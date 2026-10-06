<?php

namespace Tests\Feature\Jobs;

use App\Filament\Resources\Operational\PurchaseRequestResource\Imports\PurchaseRequestImporter;
use App\Jobs\ImportGroupedCsv;
use App\Models\Department;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Status;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportGroupedCsvTest extends TestCase
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

    private function prStatus(string $englishName): Status
    {
        return Status::factory()->create([
            'type' => PurchaseRequest::TYPE_PURCHASE_REQUEST,
            'english_type' => PurchaseRequest::TYPE_PURCHASE_REQUEST,
            'name' => $englishName,
            'english_name' => $englishName,
        ]);
    }

    private function mergedColumnMap(): array
    {
        $names = collect(PurchaseRequestImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
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

    public function test_middleware_wraps_the_import_with_without_overlapping(): void
    {
        $import = $this->makePersistedImport(1);
        $job = new ImportGroupedCsv($import, [], [], []);

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    public function test_handle_rolls_back_and_logs_every_row_in_a_failing_group(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();
        $costCenter = Department::factory()->create();
        $product = Product::factory()->create();
        $status = $this->prStatus('ImportRollbackStatus');

        $import = $this->makePersistedImport(3);
        $columnMap = $this->mergedColumnMap();

        $group = [
            'parent' => [
                'pr_number' => '',
                'requester_id' => $requester->email,
                'department_id' => $department->name,
                'cost_center_id' => $costCenter->name,
                'required_by_date' => '2026-12-31',
                'urgency_level' => 'low',
                'total_estimated_cost' => '10',
                'status_id' => $status->english_name,
                'notes' => '',
            ],
            'parentPhysicalRow' => 2,
            'items' => [
                ['row' => ['product_id' => $product->code, 'quantity' => '1', 'unit' => 'kg', 'estimated_cost' => '1', 'item_status_id' => '', 'item_notes' => ''], 'physicalRow' => 3],
                ['row' => ['product_id' => 'DOES-NOT-EXIST-ANYWHERE', 'quantity' => '1', 'unit' => 'kg', 'estimated_cost' => '1', 'item_status_id' => '', 'item_notes' => ''], 'physicalRow' => 4],
            ],
        ];

        $job = new ImportGroupedCsv($import, [$group], $columnMap, ['locale' => 'en', 'jalali' => false, 'date_format' => 'Y-m-d']);
        $job->handle();

        $import->refresh();

        $this->assertSame(0, $import->successful_rows);
        $this->assertSame(3, $import->processed_rows);
        $this->assertSame(3, $import->failedRows()->count());
        $this->assertSame(0, PurchaseRequest::where('cost_center_id', $costCenter->id)->count());

        $errors = $import->failedRows()->pluck('validation_error')->all();
        $this->assertTrue(collect($errors)->contains(fn ($message) => str_contains($message, 'DOES-NOT-EXIST-ANYWHERE')));
        $this->assertSame(2, collect($errors)->filter(fn ($message) => str_contains($message, (string) __('resources/general/strings.import.sibling_row_failed', ['row' => 4])))->count());
    }

    public function test_handle_accounts_successful_group_rows_including_zero_item_parent(): void
    {
        $requesterOne = User::factory()->create();
        $requesterTwo = User::factory()->create();
        $department = Department::factory()->create();
        $costCenter = Department::factory()->create();
        $product = Product::factory()->create();
        $status = $this->prStatus('ImportAccountingStatus');

        $import = $this->makePersistedImport(3);
        $columnMap = $this->mergedColumnMap();

        $groups = [
            [
                'parent' => [
                    'pr_number' => '', 'requester_id' => $requesterOne->email, 'department_id' => $department->name,
                    'cost_center_id' => $costCenter->name, 'required_by_date' => '2026-12-31', 'urgency_level' => 'low',
                    'total_estimated_cost' => '5', 'status_id' => $status->english_name, 'notes' => '',
                ],
                'parentPhysicalRow' => 2,
                'items' => [
                    ['row' => ['product_id' => $product->code, 'quantity' => '1', 'unit' => 'kg', 'estimated_cost' => '1', 'item_status_id' => '', 'item_notes' => ''], 'physicalRow' => 3],
                ],
            ],
            [
                'parent' => [
                    'pr_number' => '', 'requester_id' => $requesterTwo->email, 'department_id' => $department->name,
                    'cost_center_id' => $costCenter->name, 'required_by_date' => '2026-12-31', 'urgency_level' => 'low',
                    'total_estimated_cost' => '0', 'status_id' => $status->english_name, 'notes' => '',
                ],
                'parentPhysicalRow' => 4,
                'items' => [],
            ],
        ];

        $job = new ImportGroupedCsv($import, $groups, $columnMap, ['locale' => 'en', 'jalali' => false, 'date_format' => 'Y-m-d']);
        $job->handle();

        $import->refresh();

        $this->assertSame(3, $import->successful_rows);
        $this->assertSame(3, $import->processed_rows);
        $this->assertSame(0, $import->failedRows()->count());
        $this->assertSame(0, $import->getFailedRowsCount());

        $recordOne = PurchaseRequest::where('requester_id', $requesterOne->id)->firstOrFail();
        $recordTwo = PurchaseRequest::where('requester_id', $requesterTwo->id)->firstOrFail();
        $this->assertNotSame($recordOne->pr_number, $recordTwo->pr_number);
        $this->assertSame(1, $recordOne->items()->count());
        $this->assertSame(0, $recordTwo->items()->count());
    }

    public function test_handle_propagates_a_deadlock_instead_of_silently_logging_it_as_a_normal_row_failure(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();
        $costCenter = Department::factory()->create();
        $status = $this->prStatus('ImportDeadlockStatus');

        $import = $this->makePersistedImport(1);
        $columnMap = $this->mergedColumnMap();

        $group = [
            'parent' => [
                'pr_number' => '', 'requester_id' => $requester->email, 'department_id' => $department->name,
                'cost_center_id' => $costCenter->name, 'required_by_date' => '2026-12-31', 'urgency_level' => 'low',
                'total_estimated_cost' => '5', 'status_id' => $status->english_name, 'notes' => '',
            ],
            'parentPhysicalRow' => 2,
            'items' => [],
        ];

        $job = new ImportGroupedCsv($import, [$group], $columnMap, ['locale' => 'en', 'jalali' => false, 'date_format' => 'Y-m-d']);

        $property = new \ReflectionProperty($job, 'importer');
        $realImporter = $property->getValue($job);
        $stub = new DeadlockOnceImporterDecorator($realImporter);
        $property->setValue($job, $stub);

        try {
            $job->handle();
            $this->fail('A deadlock must propagate, never be absorbed as a normal row failure.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('Deadlock found when trying to get lock', $exception->getMessage());
        }

        $import->refresh();

        $this->assertSame(0, $import->successful_rows, 'nothing from the doomed attempt may be reported as successful');
        $this->assertSame(0, $import->failedRows()->count(), 'a deadlock is not a row-level failure to log, it must propagate instead');
        $this->assertSame(0, PurchaseRequest::where('requester_id', $requester->id)->count());
    }

    public function test_handle_still_logs_a_non_deadlock_query_exception_as_a_normal_row_failure(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();
        $costCenter = Department::factory()->create();
        $status = $this->prStatus('ImportNonDeadlockStatus');

        $import = $this->makePersistedImport(1);
        $columnMap = $this->mergedColumnMap();

        $group = [
            'parent' => [
                'pr_number' => '', 'requester_id' => $requester->email, 'department_id' => $department->name,
                'cost_center_id' => $costCenter->name, 'required_by_date' => '2026-12-31', 'urgency_level' => 'low',
                'total_estimated_cost' => '5', 'status_id' => $status->english_name, 'notes' => '',
            ],
            'parentPhysicalRow' => 2,
            'items' => [],
        ];

        $job = new ImportGroupedCsv($import, [$group], $columnMap, ['locale' => 'en', 'jalali' => false, 'date_format' => 'Y-m-d']);

        $property = new \ReflectionProperty($job, 'importer');
        $realImporter = $property->getValue($job);
        $stub = new NonDeadlockFailureImporterDecorator($realImporter);
        $property->setValue($job, $stub);

        $job->handle();

        $import->refresh();

        $this->assertSame(1, $stub->calls, 'a non-deadlock failure must not trigger a retry');
        $this->assertSame(0, $import->successful_rows);
        $this->assertSame(1, $import->processed_rows);
        $this->assertSame(1, $import->failedRows()->count());
        $this->assertSame(0, PurchaseRequest::where('requester_id', $requester->id)->count());
    }
}

class DeadlockOnceImporterDecorator extends \Filament\Actions\Imports\Importer
{
    public int $calls = 0;

    public function __construct(private $real) {}

    public function __invoke(array $data): void
    {
        $this->calls++;

        if ($this->calls === 1) {
            $previous = new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction');
            $previous->errorInfo = ['40001', 1213, 'Deadlock found'];

            throw new \Illuminate\Database\QueryException('mysql', 'insert', [], $previous);
        }

        ($this->real)($data);
    }

    public static function isDeadlock(\Illuminate\Database\QueryException $exception): bool
    {
        return \App\Filament\Resources\Operational\PurchaseRequestResource\Imports\PurchaseRequestImporter::isDeadlock($exception);
    }

    public static function getColumns(): array
    {
        return [];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return '';
    }
}

class NonDeadlockFailureImporterDecorator extends \Filament\Actions\Imports\Importer
{
    public int $calls = 0;

    public function __construct(private $real) {}

    public function __invoke(array $data): void
    {
        $this->calls++;

        $previous = new \PDOException('duplicate');
        $previous->errorInfo = ['23000', 1062, 'Duplicate entry'];

        throw new \Illuminate\Database\QueryException('mysql', 'insert', [], $previous);
    }

    public static function isDeadlock(\Illuminate\Database\QueryException $exception): bool
    {
        return \App\Filament\Resources\Operational\PurchaseRequestResource\Imports\PurchaseRequestImporter::isDeadlock($exception);
    }

    public static function getColumns(): array
    {
        return [];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return '';
    }
}
