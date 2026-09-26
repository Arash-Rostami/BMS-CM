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
}
