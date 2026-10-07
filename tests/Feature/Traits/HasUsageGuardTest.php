<?php

namespace Tests\Feature\Traits;

use App\Filament\Resources\BankResource;
use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Tests\TestCase;

/**
 * App\Filament\Traits\HasUsageGuard's unit contract: usageCount() sums the
 * composing resource's declared usageRelations(), both guarded before
 * hooks (record + bulk) send the blocked-notification and halt on in-use
 * records — the bulk hook sums usage across the whole selection — and
 * getInUseCountColumn() builds the shared usage-count badge column.
 * The Livewire-level delete/deactivate behavior (halted notification, unused
 * delete succeeding) and the badge's per-row numeric state are already
 * covered per-resource in BankResourceTest, CompanyResourceTest and
 * CurrencyResourceTest — only the trait contract is pinned here, through
 * the real BankResource wiring.
 */
class HasUsageGuardTest extends TestCase
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

    public function test_usage_count_is_zero_for_a_record_with_no_usage(): void
    {
        $bank = Bank::factory()->create();

        $this->assertSame(0, BankResource::usageCount($bank), 'A record nobody references must report zero usage.');
    }

    public function test_usage_count_sums_across_every_declared_usage_relation(): void
    {
        $bank = Bank::factory()->create();
        BankProfile::factory()->count(2)->create(['bank_id' => $bank->id]);
        Payment::factory()->create(['bank_id' => $bank->id]);

        $this->assertSame(3, BankResource::usageCount($bank), 'usageCount must sum every declared relation, not just the first that has rows.');
    }

    public function test_in_use_count_column_is_a_warning_badge_gray_only_when_unused(): void
    {
        $column = BankResource::getInUseCountColumn();

        $this->assertTrue($column->isBadge());
        $this->assertSame('heroicon-o-link', $column->getIcon(null));
        $this->assertSame('gray', $column->getColor(0));
        $this->assertSame('warning', $column->getColor(2));
    }

    public function test_guarded_record_action_halts_with_the_blocked_notification_when_the_record_is_in_use(): void
    {
        app()->setLocale('en');
        $bank = Bank::factory()->create();
        BankProfile::factory()->create(['bank_id' => $bank->id]);
        $action = HasUsageGuardBankResource::exposeGuardRecordAction(DeleteAction::make())->record($bank);

        try {
            $action->callBefore();
            $this->fail('A guarded action on an in-use record must halt before running.');
        } catch (Halt) {
        }

        Notification::assertNotified(__('resources/general/strings.usage_guard.blocked', ['count' => 1]));
    }

    public function test_guarded_record_action_runs_without_halting_when_the_record_is_unused(): void
    {
        $bank = Bank::factory()->create();
        $action = HasUsageGuardBankResource::exposeGuardRecordAction(DeleteAction::make())->record($bank);

        $action->callBefore();

        Notification::assertNotNotified();
    }

    public function test_guarded_bulk_action_halts_when_any_record_in_the_selection_is_in_use(): void
    {
        app()->setLocale('en');
        $inUse = Bank::factory()->create();
        BankProfile::factory()->count(2)->create(['bank_id' => $inUse->id]);
        $unused = Bank::factory()->create();
        $action = HasUsageGuardBankResource::exposeGuardBulkAction(DeleteAction::make());

        $before = new ReflectionProperty($action, 'before');
        $before->setAccessible(true);

        try {
            ($before->getValue($action))($action, new Collection([$inUse, $unused]));
            $this->fail('A bulk action over a selection containing an in-use record must halt before running.');
        } catch (Halt) {
        }

        Notification::assertNotified(__('resources/general/strings.usage_guard.blocked', ['count' => 2]));
    }
}

class HasUsageGuardBankResource extends BankResource
{
    public static function exposeGuardRecordAction(Action $action): Action
    {
        return static::guardRecordAction($action);
    }

    public static function exposeGuardBulkAction(Action $action): Action
    {
        return static::guardBulkAction($action);
    }
}
