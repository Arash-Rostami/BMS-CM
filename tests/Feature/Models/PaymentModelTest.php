<?php

namespace Tests\Feature\Models;

use App\Models\Bank;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentModelTest extends TestCase
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

    // TracksStatusHistory

    public function test_updating_status_id_writes_a_status_history_row_with_from_to_and_actor(): void
    {
        $from = Status::factory()->create();
        $to = Status::factory()->create();
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $payment = Payment::factory()->create(['status_id' => $from->id]);
        $payment->update(['status_id' => $to->id]);

        $history = $payment->statusHistories()->latest('id')->first();

        $this->assertSame('status_id', $history->field);
        $this->assertSame($from->id, $history->from_status_id);
        $this->assertSame($to->id, $history->to_status_id);
        $this->assertSame($actor->id, $history->user_id);
    }

    // Scoped relations — bank / payor / payee / currency / status

    public function test_bank_relation_excludes_an_inactive_bank(): void
    {
        $bank = Bank::factory()->create(['is_active' => false]);
        $payment = Payment::factory()->create(['bank_id' => $bank->id]);

        $this->assertNull($payment->fresh()->bank);
    }

    public function test_payor_relation_excludes_an_inactive_company(): void
    {
        $company = Company::factory()->create(['is_active' => false]);
        $payment = Payment::factory()->create(['payor_id' => $company->id]);

        $this->assertNull($payment->fresh()->payor);
    }

    public function test_payee_relation_excludes_an_inactive_company(): void
    {
        $company = Company::factory()->create(['is_active' => false]);
        $payment = Payment::factory()->create(['payee_id' => $company->id]);

        $this->assertNull($payment->fresh()->payee);
    }

    public function test_currency_relation_excludes_an_inactive_currency(): void
    {
        $currency = Currency::factory()->inactive()->create();
        $payment = Payment::factory()->create(['currency_id' => $currency->id]);

        $this->assertNull($payment->fresh()->currency);
    }

    public function test_status_relation_is_scoped_to_the_payment_status_type(): void
    {
        $wrongType = Status::factory()->create(['english_type' => 'Registered Order Status']);
        $payment = Payment::factory()->create(['status_id' => $wrongType->id]);

        $this->assertNull($payment->fresh()->status);
    }

    // scopeSearchAll

    public function test_search_all_scope_matches_id(): void
    {
        $target = Payment::factory()->create();

        $results = Payment::searchAll((string) $target->id)->get();

        $this->assertTrue($results->contains('id', $target->id));
    }

    public function test_search_all_scope_matches_payment_no(): void
    {
        $target = Payment::factory()->create();
        $term = substr($target->payment_no, -4);

        $results = Payment::searchAll($term)->get();

        $this->assertTrue($results->contains('id', $target->id));
    }

    public function test_search_all_scope_with_blank_term_returns_query_unmodified(): void
    {
        $before = Payment::count();
        Payment::factory()->count(3)->create();

        $this->assertSame($before + 3, Payment::searchAll('   ')->count());
    }

    // HasComputedAttributes — calculated_total (payable_amount + bank_charges) / total_ratio (calculated_total / total_amount)

    public function test_calculated_total_adds_bank_charges_to_payable_amount(): void
    {
        $payment = new Payment(['payable_amount' => 1000, 'bank_charges' => 50]);

        $this->assertEquals(1050, $payment->calculated_total);
    }

    public function test_calculated_total_treats_a_null_payable_amount_or_bank_charges_as_zero(): void
    {
        $onlyCharges = new Payment(['bank_charges' => 50]);
        $onlyPayable = new Payment(['payable_amount' => 1000]);
        $neither = new Payment;

        $this->assertEquals(50, $onlyCharges->calculated_total);
        $this->assertEquals(1000, $onlyPayable->calculated_total);
        $this->assertEquals(0, $neither->calculated_total);
    }

    public function test_total_ratio_divides_calculated_total_by_total_amount(): void
    {
        $payment = new Payment(['payable_amount' => 1000, 'bank_charges' => 50, 'total_amount' => 2000]);

        $this->assertEquals(0.525, $payment->total_ratio);
    }

    public function test_total_ratio_is_null_when_total_amount_is_zero(): void
    {
        $payment = new Payment(['payable_amount' => 1000, 'bank_charges' => 50, 'total_amount' => 0]);

        $this->assertNull($payment->total_ratio);
    }

    // HasTargetableDisplay

    public function test_targetable_display_returns_a_dash_when_no_target_is_set(): void
    {
        $payment = new Payment;

        $this->assertSame('-', $payment->getTargetableDisplay());
    }

    public function test_targetable_display_returns_the_formatted_name_for_a_purchase_order_target(): void
    {
        $po = PurchaseOrder::factory()->create();
        $payment = Payment::factory()->forTargetable($po)->create();
        $fresh = $payment->fresh()->load('targetable');

        $this->assertSame($po->fresh()->formatted_name_without_date, $fresh->getTargetableDisplay());
        $this->assertSame($po->fresh()->formatted_name, $fresh->getTargetableDisplay(true));
    }

    public function test_targetable_display_returns_the_formatted_name_for_a_registered_order_target(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $payment = Payment::factory()->forTargetable($ro)->create();
        $fresh = $payment->fresh()->load('targetable');

        $this->assertSame($ro->fresh()->formatted_name_without_date, $fresh->getTargetableDisplay());
        $this->assertSame($ro->fresh()->formatted_name, $fresh->getTargetableDisplay(true));
    }

    // Casts

    public function test_payment_date_and_deadline_cast_to_carbon_dates(): void
    {
        $payment = Payment::factory()->create(['payment_date' => '2026-01-10', 'payment_deadline' => '2026-01-20']);

        $this->assertInstanceOf(Carbon::class, $payment->payment_date);
        $this->assertInstanceOf(Carbon::class, $payment->payment_deadline);
        $this->assertSame('2026-01-10', $payment->payment_date->format('Y-m-d'));
        $this->assertSame('2026-01-20', $payment->payment_deadline->format('Y-m-d'));
    }

    public function test_decimal_columns_round_trip_through_the_decimal_cast(): void
    {
        $payment = Payment::factory()->create([
            'payable_amount' => 123.456789,
            'total_amount' => 999.111111,
            'exchange_rate' => 50.5,
            'bank_charges' => 10.123456,
        ]);
        $fresh = $payment->fresh();

        $this->assertSame('123.45679', (string) $fresh->payable_amount);
        $this->assertSame('999.11111', (string) $fresh->total_amount);
        $this->assertSame('50.50000', (string) $fresh->exchange_rate);
        $this->assertSame('10.12346', (string) $fresh->bank_charges);
    }
}
