<?php

namespace Tests\Feature\Jobs;

use App\Filament\Resources\Operational\PurchaseRequestResource\Imports\PurchaseRequestImporter;
use App\Jobs\ImportCsv;
use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Tests\TestCase;

class ImportCsvTest extends TestCase
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

    private function mergedColumnMap(): array
    {
        $names = collect(PurchaseRequestImporter::getColumns())->map(fn ($column) => $column->getName())->all();

        return array_combine($names, $names);
    }

    public function test_middleware_wraps_the_import_with_without_overlapping(): void
    {
        $import = $this->makePersistedImport(1);
        $job = new ImportCsv($import, [], [], []);

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    public function test_handle_processes_flat_rows_and_resets_reserved_numbers_afterwards(): void
    {
        $requester = User::factory()->create();
        $department = Department::factory()->create();

        $import = $this->makePersistedImport(2);
        $columnMap = $this->mergedColumnMap();

        $rows = [
            [
                'pr_number' => '', 'requester_id' => $requester->email, 'department_id' => $department->name,
                'cost_center_id' => '', 'required_by_date' => '2026-12-31', 'urgency_level' => 'low',
                'total_estimated_cost' => '5', 'status_id' => '', 'notes' => '',
            ],
            [
                'pr_number' => '', 'requester_id' => $requester->email, 'department_id' => $department->name,
                'cost_center_id' => '', 'required_by_date' => '2026-12-31', 'urgency_level' => 'low',
                'total_estimated_cost' => '5', 'status_id' => '', 'notes' => '',
            ],
        ];

        $job = new ImportCsv($import, $rows, $columnMap, ['locale' => 'en', 'jalali' => false, 'date_format' => 'Y-m-d']);
        $job->handle();

        $import->refresh();

        $this->assertSame(2, $import->successful_rows);
        $records = PurchaseRequest::where('requester_id', $requester->id)->get();
        $this->assertCount(2, $records);
        $this->assertNotSame($records[0]->pr_number, $records[1]->pr_number);

        $queue = new ReflectionProperty(PurchaseRequestImporter::class, 'reservedNumberQueue');
        $queue->setAccessible(true);
        $this->assertSame([], $queue->getValue());
    }
}
