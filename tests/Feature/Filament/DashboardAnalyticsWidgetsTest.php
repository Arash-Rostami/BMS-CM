<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\ConcentrationRiskWidget;
use App\Filament\Widgets\ExposureAgingWidget;
use App\Filament\Widgets\OpenCurrencyExposureWidget;
use App\Filament\Widgets\PipelineStallWidget;
use App\Filament\Widgets\ShipmentPunctualityWidget;
use App\Filament\Widgets\TradeCycleTimeWidget;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class DashboardAnalyticsWidgetsTest extends TestCase
{
    private const KEYS = ['concentration', 'cycle_time', 'exposure_aging', 'open_exposure', 'pipeline_stalls', 'shipment_punctuality'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        app()->setLocale('en');
        $this->forgetKeys();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        $this->forgetKeys();
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

    private function forgetKeys(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget("analytics:{$key}");
        }
    }

    private function stub(string $key, mixed $value): void
    {
        Cache::put("analytics:{$key}", $value, now()->addMinutes(5));
    }

    private function dataOf(ChartWidget $widget): array
    {
        return (new ReflectionMethod($widget, 'getData'))->invoke($widget);
    }

    public function test_cycle_time_chart_labels_every_stage_with_its_sample_size_and_plots_median_and_p90(): void
    {
        $this->stub('cycle_time', [
            'request_to_order' => ['count' => 12, 'p50' => 4, 'p90' => 9],
            'order_to_payment' => ['count' => 1, 'p50' => 10, 'p90' => 10],
            'payment_to_shipment' => ['count' => 0, 'p50' => null, 'p90' => null],
            'shipment_to_clearance' => ['count' => 3, 'p50' => 2, 'p90' => 5],
        ]);

        $data = $this->dataOf(new TradeCycleTimeWidget);

        $this->assertSame(['Request → Order (n=12)', 'Order → Payment (n=1)', 'Payment → Shipment (n=0)', 'Shipment → Clearance (n=3)'], $data['labels']);
        $this->assertSame([4, 10, null, 2], $data['datasets'][0]['data']);
        $this->assertSame([9, 10, null, 5], $data['datasets'][1]['data']);
    }

    public function test_punctuality_chart_plots_the_five_buckets_in_a_fixed_order_as_integers(): void
    {
        $this->stub('shipment_punctuality', ['on_time' => '3', 'late_1_3' => '2', 'late_4_7' => '0', 'late_8_plus' => '1', 'currently_overdue' => '4']);

        $data = $this->dataOf(new ShipmentPunctualityWidget);

        $this->assertSame([3, 2, 0, 1, 4], $data['datasets'][0]['data']);
        $this->assertCount(5, $data['labels']);
        $this->assertSame('Warehouse receipt date vs estimated arrival (ETA)', (new ShipmentPunctualityWidget)->getDescription());
    }

    public function test_aging_chart_has_one_bar_per_currency_and_one_dataset_per_bucket_in_the_locale_name(): void
    {
        $this->stub('exposure_aging', [
            (object) ['id' => 1, 'name' => 'دلار', 'english_name' => 'Dollar', 'bucket_0_30' => '10', 'bucket_31_60' => '20', 'bucket_61_90' => '30', 'bucket_90_plus' => '40'],
            (object) ['id' => 2, 'name' => 'یورو', 'english_name' => 'Euro', 'bucket_0_30' => '1', 'bucket_31_60' => '2', 'bucket_61_90' => '3', 'bucket_90_plus' => '4'],
        ]);

        $data = $this->dataOf(new ExposureAgingWidget);

        $this->assertSame(['Dollar', 'Euro'], $data['labels']);
        $this->assertSame([[10.0, 1.0], [20.0, 2.0], [30.0, 3.0], [40.0, 4.0]], array_column($data['datasets'], 'data'));

        app()->setLocale('fa');
        $this->assertSame(['دلار', 'یورو'], $this->dataOf(new ExposureAgingWidget)['labels']);
    }

    public function test_open_exposure_chart_plots_one_bar_per_currency(): void
    {
        $this->stub('open_exposure', [(object) ['id' => 1, 'name' => 'دلار', 'english_name' => 'Dollar', 'open_exposure' => '500.50000']]);

        $data = $this->dataOf(new OpenCurrencyExposureWidget);

        $this->assertSame(['Dollar'], $data['labels']);
        $this->assertSame([500.5], $data['datasets'][0]['data']);
    }

    public function test_concentration_stats_use_the_2500_and_1500_hhi_thresholds(): void
    {
        $widget = new ConcentrationRiskWidget;
        $stat = new ReflectionMethod($widget, 'stat');

        foreach ([[2500.0, 'danger'], [1500.0, 'warning'], [1499.0, 'success'], [0.0, 'success']] as [$hhi, $color]) {
            $this->assertSame($color, $stat->invoke($widget, $hhi, 'label')->getColor(), (string) $hhi);
        }
    }

    public function test_the_stall_list_renders_records_with_working_edit_links(): void
    {
        $this->stub('pipeline_stalls', [
            (object) ['record_type' => 'registered_order', 'record_number' => 'RO-1', 'days_overdue' => 70, 'id' => 7],
            (object) ['record_type' => 'payment', 'record_number' => 'P-2', 'days_overdue' => 50, 'id' => 8],
            (object) ['record_type' => 'shipment', 'record_number' => 'S-3', 'days_overdue' => 40, 'id' => 9],
            (object) ['record_type' => 'purchase_request', 'record_number' => 'PR-4', 'days_overdue' => 20, 'id' => 10],
        ]);
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(PipelineStallWidget::class)->assertSuccessful();

        foreach (['RO-1', 'P-2', 'S-3', 'PR-4'] as $number) {
            $component->assertSee($number);
        }
        $component->assertSeeHtml('/registered-orders/7/edit')->assertSeeHtml('/payments/8/edit')->assertSeeHtml('/shipments/9/edit')->assertSeeHtml('/purchase-requests/10/edit');
    }

    public function test_every_chart_widget_renders_with_its_legend_and_without_data(): void
    {
        $this->stub('cycle_time', array_fill_keys(['request_to_order', 'order_to_payment', 'payment_to_shipment', 'shipment_to_clearance'], ['count' => 0, 'p50' => null, 'p90' => null]));
        $this->stub('shipment_punctuality', ['on_time' => 0, 'late_1_3' => 0, 'late_4_7' => 0, 'late_8_plus' => 0, 'currently_overdue' => 0]);
        $this->stub('exposure_aging', []);
        $this->stub('open_exposure', []);
        $this->actingAs(User::factory()->create());

        foreach ([TradeCycleTimeWidget::class, ShipmentPunctualityWidget::class, ExposureAgingWidget::class, OpenCurrencyExposureWidget::class] as $widget) {
            Livewire::test($widget)->assertSuccessful()->assertSee(__('resources/dashboard/strings.widgets.legend.toggle_show'));
        }
    }
}
