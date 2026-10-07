<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use App\Models\Product;
use App\Models\Target;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TargetModelTest extends TestCase
{
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

    // metrics accessor

    public function test_metrics_translates_the_code_for_display(): void
    {
        $target = Target::factory()->make(['metrics' => 'kg']);

        $this->assertSame('Kilogram', $target->metrics);
    }

    public function test_metrics_set_accepts_a_code_a_label_or_an_unknown_value_unchanged(): void
    {
        $byCode = new Target(['metrics' => 'kg']);
        $this->assertSame('kg', $byCode->getAttributes()['metrics']);

        $byLabel = new Target(['metrics' => 'Pound']);
        $this->assertSame('lb', $byLabel->getAttributes()['metrics']);

        $unknown = new Target(['metrics' => 'cubits']);
        $this->assertSame('cubits', $unknown->getAttributes()['metrics']);
    }

    // achievement progress

    public function test_achieved_percentage_prefers_quantity_then_amount_and_guards_zero_and_null(): void
    {
        $byQuantity = new Target(['quantity' => 200, 'achieved_quantity' => 50, 'amount' => 10, 'achieved_amount' => 10]);
        $this->assertSame(25.0, $byQuantity->achieved_percentage);
        $this->assertSame('danger', $byQuantity->achieved_color);

        $byAmount = new Target(['quantity' => 0, 'amount' => 100, 'achieved_amount' => 75]);
        $this->assertSame(75.0, $byAmount->achieved_percentage);
        $this->assertSame('warning', $byAmount->achieved_color);

        $done = new Target(['quantity' => 10, 'achieved_quantity' => 12]);
        $this->assertSame(120.0, $done->achieved_percentage);
        $this->assertSame('success', $done->achieved_color);

        $noAchieved = new Target(['quantity' => 10]);
        $this->assertSame(0.0, $noAchieved->achieved_percentage);

        $noGoal = new Target(['quantity' => 0, 'amount' => null, 'achieved_amount' => 5]);
        $this->assertNull($noGoal->achieved_percentage);
        $this->assertSame('gray', $noGoal->achieved_color);
    }

    public function test_ended_still_active_uses_the_today_boundary(): void
    {
        $yesterday = new Target(['status' => 'active', 'end_in' => today()->subDay()]);
        $today = new Target(['status' => 'active', 'end_in' => today()]);
        $inactive = new Target(['status' => 'inactive', 'end_in' => today()->subDay()]);

        $this->assertTrue($yesterday->ended_still_active);
        $this->assertFalse($today->ended_still_active);
        $this->assertFalse($inactive->ended_still_active);
    }

    // year accessor

    public function test_year_stays_gregorian_outside_the_fa_locale(): void
    {
        app()->setLocale('en');

        $this->assertSame(2026, Target::factory()->make(['year' => 2026])->year);
    }

    public function test_year_keeps_a_jalali_value_as_is_and_converts_a_gregorian_one_under_fa(): void
    {
        app()->setLocale('fa');

        $this->assertSame(1405, Target::factory()->make(['year' => 1405])->year);
        $this->assertSame(1405, Target::factory()->make(['year' => 2026])->year);

        app()->setLocale('en');
    }

    // targetable label

    public function test_targetable_label_uses_the_localized_name_for_a_category_target(): void
    {
        $uniq = uniqid();
        $category = Category::factory()->create(['name' => "دسته {$uniq}", 'english_name' => "Cat {$uniq}"]);
        $target = Target::factory()->forTargetable($category)->create();

        $this->assertSame("Cat {$uniq}", $target->targetable_label);
    }

    public function test_targetable_label_uses_the_sprint_format_for_a_product_target(): void
    {
        $uniq = uniqid();
        $product = Product::factory()->create(['code' => "PRD-{$uniq}", 'english_name' => "Prod {$uniq}"]);
        $target = Target::factory()->forTargetable($product)->create();

        $this->assertSame("Sprint: PRD-{$uniq} - Prod {$uniq}", $target->targetable_label);
    }

    public function test_targetable_label_is_a_dash_without_a_targetable(): void
    {
        $target = new Target(['year' => 2026]);

        $this->assertSame('-', $target->targetable_label);
    }

    // scopeSearchTargetable

    public function test_search_targetable_finds_category_names_and_product_codes(): void
    {
        $uniq = uniqid();
        $category = Category::factory()->create(['english_name' => "Paperwork {$uniq}"]);
        $product = Product::factory()->create(['code' => "PRD-{$uniq}", 'english_name' => 'Unrelated']);
        $unrelated = Category::factory()->create(['english_name' => 'Something else entirely']);

        $categoryTarget = Target::factory()->forTargetable($category)->create();
        $productTarget = Target::factory()->forTargetable($product)->create();
        $unrelatedTarget = Target::factory()->forTargetable($unrelated)->create();

        $ids = Target::searchTargetable($uniq)->pluck('id');

        $this->assertTrue($ids->contains($categoryTarget->id));
        $this->assertTrue($ids->contains($productTarget->id));
        $this->assertFalse($ids->contains($unrelatedTarget->id));
    }

    // casts

    public function test_casts_pin_precision_dates_and_arrays(): void
    {
        $target = Target::factory()->forTargetable(Category::factory()->create())->create([
            'quantity' => 12.5,
            'tags' => ['priority', 'regional'],
            'start_from' => '2026-01-15',
        ]);

        $this->assertSame('12.50000', $target->quantity);
        $this->assertSame(['priority', 'regional'], $target->tags);
        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $target->start_from);
    }

    // soft delete

    public function test_soft_delete_hides_the_row_and_restore_brings_it_back(): void
    {
        $target = Target::factory()->forTargetable(Category::factory()->create())->create();

        $target->delete();

        $this->assertNull(Target::find($target->id));
        $this->assertNotNull(Target::withTrashed()->find($target->id));

        $target->fresh()->restore();

        $this->assertNotNull(Target::find($target->id));
    }
}