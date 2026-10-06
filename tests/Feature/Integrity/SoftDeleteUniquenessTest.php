<?php

namespace Tests\Feature\Integrity;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * One mechanism, two halves: soft-deletable tables must NOT carry a DB-level
 * unique index (it collides with soft-deleted rows — modelsPattern.md §9),
 * which means uniqueness for those columns is enforced application-side only.
 * The schema half below verifies no index snuck back in on the REAL dev
 * MySQL schema (the project has no active migrations — the live schema is
 * the only source of truth); the validation half sweeps every Form trait for
 * a ->unique() call on a soft-deletable model and requires the rule to
 * exclude trashed rows. Without the useMysql wiring the schema check reads
 * the empty test-default sqlite connection and passes vacuously — the
 * skeleton is load-bearing here, not conventional.
 */
class SoftDeleteUniquenessTest extends TestCase
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

    public function test_soft_deletable_tables_carry_no_db_level_unique_index(): void
    {
        $columnsByTable = [
            'users' => ['email'],
            'products' => ['code'],
            'purchase_requests' => ['pr_number'],
            'shipments' => ['shipment_no'],
            'purchase_orders' => ['po_number'],
            'proforma_invoices' => ['invoice_no'],
            'registered_orders' => ['ro_number', 'official_registration_no'],
            'payments' => ['payment_no'],
            'customs' => ['custom_no'],
            'bank_profiles' => ['bp_number'],
            'statuses' => ['type', 'name'],
        ];

        foreach ($columnsByTable as $table => $columns) {
            $this->assertNotEmpty(
                Schema::getIndexes($table),
                "{$table} not found on the dev MySQL schema — the index check can't run."
            );

            foreach (Schema::getIndexes($table) as $index) {
                if ($index['primary'] || ! $index['unique']) {
                    continue;
                }

                $overlap = array_intersect($columns, $index['columns']);

                if ($overlap !== []) {
                    $this->fail(
                        "{$table}.".implode(',', $overlap).
                        " carries a DB-level unique index ({$index['name']}) again — this collides with soft-deleted rows ".
                        '(see modelsPattern.md §9). Uniqueness for these columns must stay application-level only.'
                    );
                }
            }
        }

        $this->assertTrue(true);
    }

    public function test_unique_form_fields_on_soft_deletable_models_exclude_trashed_rows(): void
    {
        $files = File::allFiles(app_path('Filament/Resources'));
        $checked = 0;

        foreach ($files as $file) {
            if (! str_ends_with($file->getPathname(), 'Traits'.DIRECTORY_SEPARATOR.'Form.php')) {
                continue;
            }

            $path = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

            if (! preg_match('#([A-Za-z]+)Resource[/\\\\]Traits[/\\\\]Form\.php$#', $path, $m)) {
                continue;
            }

            $model = 'App\\Models\\'.$m[1];

            if (! class_exists($model) || ! in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                continue;
            }

            $contents = $file->getContents();
            $offset = 0;
            $primitiveReturnTypes = ['array', 'string', 'int', 'float', 'bool', 'void', 'mixed', 'iterable', 'self', 'static', 'object', 'callable'];

            while (($pos = strpos($contents, '->unique(', $offset)) !== false) {
                $call = $this->extractBalancedCall($contents, $pos + strlen('->unique('));
                $offset = $pos + strlen('->unique(') + strlen($call);

                $enclosingReturnType = null;
                if (preg_match_all('#function\s+\w+\([^)]*\)\s*:\s*\??([A-Za-z_\\\\]+)#', substr($contents, 0, $pos), $matches)) {
                    $enclosingReturnType = strtolower(end($matches[1]));
                }

                if (in_array($enclosingReturnType, $primitiveReturnTypes, true)) {
                    continue;
                }

                $checked++;

                if (! str_contains($call, 'withoutTrashed()') && ! str_contains($call, "whereNull('deleted_at')")) {
                    $this->fail("{$path}: a ->unique() call on soft-deletable model {$m[1]} is missing modifyRuleUsing: fn (\$rule) => \$rule->withoutTrashed() — soft-deleted rows will block new records reusing the same value.");
                }
            }
        }

        $this->assertGreaterThan(0, $checked, 'Expected to find at least one ->unique() call on a soft-deletable model to verify.');
    }

    private function extractBalancedCall(string $contents, int $start): string
    {
        $depth = 1;
        $i = $start;
        $len = strlen($contents);

        while ($i < $len && $depth > 0) {
            if ($contents[$i] === '(') {
                $depth++;
            } elseif ($contents[$i] === ')') {
                $depth--;
            }
            $i++;
        }

        return substr($contents, $start, $i - $start);
    }
}