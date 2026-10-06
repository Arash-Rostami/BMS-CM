<?php

namespace Tests\Feature\Traits;

use App\Filament\Traits\HandleActivation;
use App\Models\Bank;
use App\Models\Currency;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Filament\Traits\HandleActivation provides getActivateBulkAction()/
 * getDeactivateBulkAction() — bulk-set is_active to 1/0 for the given records via a
 * single whereIn()->update(). Composed on 4 unrelated Master resources (Bank, Company,
 * Currency, Department) — cross-cutting, only exercised end-to-end through
 * DepartmentResourceTest.php, nothing asserting the trait's own action closure
 * directly against more than one model.
 */
class HandleActivationTest extends TestCase
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

    private function runBulkAction(BulkAction $action, Collection $records): void
    {
        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('action');
        $property->setAccessible(true);
        $closure = $property->getValue($action);

        $closure($records);
    }

    public function test_activate_bulk_action_sets_is_active_true_for_every_selected_bank_in_one_query(): void
    {
        $banks = Bank::factory()->count(3)->inactive()->create();

        $this->runBulkAction(HandleActivationBankProbe::activateAction(), $banks);

        $this->assertSame(3, Bank::whereKey($banks->pluck('id'))->where('is_active', true)->count());
    }

    public function test_deactivate_bulk_action_sets_is_active_false_for_every_selected_currency(): void
    {
        $currencies = Currency::factory()->count(2)->create(['is_active' => true]);

        $this->runBulkAction(HandleActivationCurrencyProbe::deactivateAction(), $currencies);

        $this->assertSame(2, Currency::whereKey($currencies->pluck('id'))->where('is_active', false)->count());
    }

    public function test_bulk_action_never_touches_a_record_outside_the_given_selection(): void
    {
        $selected = Bank::factory()->inactive()->create();
        $untouched = Bank::factory()->inactive()->create();

        $this->runBulkAction(
            HandleActivationBankProbe::activateAction(),
            new Collection([$selected])
        );

        $this->assertTrue($selected->refresh()->is_active);
        $this->assertFalse($untouched->refresh()->is_active);
    }
}

class HandleActivationBankProbe
{
    use HandleActivation;

    public static function getModel(): string
    {
        return Bank::class;
    }

    public static function activateAction(): BulkAction
    {
        return self::getActivateBulkAction();
    }
}

class HandleActivationCurrencyProbe
{
    use HandleActivation;

    public static function getModel(): string
    {
        return Currency::class;
    }

    public static function deactivateAction(): BulkAction
    {
        return self::getDeactivateBulkAction();
    }
}
