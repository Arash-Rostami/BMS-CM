<?php

namespace Tests\Feature\Filament\Reports;

use App\Filament\Pages\Reports\ReportPage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ReportTableExportParityTest extends TestCase
{
    private const BUILT_REPORTS = ['operations', 'fx_supply', 'customs', 'in_transit', 'requester', 'executive', 'fx_commitments'];

    private const TABLE_ONLY_BADGES = [
        'operations' => [],
        'fx_supply' => [],
        'customs' => [],
        'in_transit' => [],
        'requester' => [],
        'fx_commitments' => [],
        'executive' => [],
    ];

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
        app()->setLocale('en');
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

    private function actingAsEveryReportViewer(): void
    {
        $role = Role::create(['name' => 'parity_role_'.uniqid(), 'guard_name' => 'web']);

        foreach (ReportPage::registry() as $page) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => 'report.'.$page::permission(), 'guard_name' => 'web']));
        }

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
    }

    public function test_every_built_report_page_is_registered(): void
    {
        $this->assertEqualsCanonicalizing(self::BUILT_REPORTS, array_keys(ReportPage::registry()));
    }

    public function test_every_table_column_of_every_report_has_an_export_twin_or_a_named_exception(): void
    {
        $this->actingAsEveryReportViewer();

        $gaps = [];

        foreach (ReportPage::registry() as $key => $page) {
            $labels = array_values(app($page::service())->exportLabels());

            foreach (Livewire::test($page)->instance()->getTable()->getColumns() as $name => $column) {
                if (! in_array($name, self::TABLE_ONLY_BADGES[$key] ?? [], true) && ! in_array((string) $column->getLabel(), $labels, true)) {
                    $gaps[$key][] = $name.' ('.$column->getLabel().')';
                }
            }
        }

        $this->assertSame([], $gaps, 'table columns without an export twin: '.json_encode($gaps, JSON_UNESCAPED_UNICODE));
    }

    public function test_the_exceptions_only_name_real_columns_so_they_cannot_hide_a_gap_silently(): void
    {
        $this->actingAsEveryReportViewer();

        $this->assertEqualsCanonicalizing(self::BUILT_REPORTS, array_keys(self::TABLE_ONLY_BADGES));

        foreach (ReportPage::registry() as $key => $page) {
            $names = array_keys(Livewire::test($page)->instance()->getTable()->getColumns());

            $this->assertSame([], array_values(array_diff(self::TABLE_ONLY_BADGES[$key], $names)), "{$key}: an exception names a column that does not exist");
        }
    }

    public function test_every_export_label_is_translated_in_every_locale(): void
    {
        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);

            foreach (ReportPage::registry() as $key => $page) {
                foreach (app($page::service())->exportLabels() as $column => $label) {
                    $this->assertNotSame('', trim((string) $label), "{$locale} {$key} {$column}");
                    $this->assertDoesNotMatchRegularExpression('/^resources\/.+\.[a-z_.]+$/', (string) $label, "{$locale} {$key} {$column} is an untranslated key");
                }
            }
        }
    }
}
