<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ExportReport;
use App\Models\BankProfile;
use App\Models\Company;
use App\Models\Permission;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Reports\StubReportPage;
use Tests\TestCase;

class ExportReportTest extends TestCase
{
    private RegisteredOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        Notification::fake();
        Storage::fake('local');
        StubReportPage::register();
        $this->order = RegisteredOrder::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app()->setLocale('en');
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

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'export_report_role_'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $user->assignRole($role);

        return $user;
    }

    private function job(User $user, string $report = 'fx_supply', string $locale = 'en'): ExportReport
    {
        return new ExportReport($report, ['registered_order_id' => $this->order->id], $user->id, $locale);
    }

    public function test_an_authorised_user_gets_the_file_and_a_signed_download_notification(): void
    {
        $user = $this->userWith(['report.view_fx_supply']);
        $profile = BankProfile::factory()->create(['registered_order_id' => $this->order->id]);

        $this->job($user)->handle();

        $files = Storage::disk('local')->files("exports/{$user->id}");
        $this->assertCount(1, $files);
        $this->assertStringContainsString('-REPORT-FX_SUPPLY-', $files[0]);

        $csv = Storage::disk('local')->get($files[0]);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('ID,"BP Number"', $csv);
        $this->assertStringContainsString((string) $profile->bp_number, $csv);
        $this->assertStringNotContainsString('must not be exported', $csv);

        Notification::assertSentTo($user, DatabaseNotification::class, function (DatabaseNotification $notification) use ($user) {
            $data = $notification->toArray(null);

            return $data['status'] === 'success'
                && str_contains($data['body'], '1')
                && str_contains($data['actions'][0]['url'], '/exports/'.$user->id.'/');
        });
    }

    public function test_the_operations_export_carries_the_last_operation_text_for_a_viewer_with_module_access(): void
    {
        $user = $this->userWith(['report.view_operations', 'registered_order.view']);
        $to = Status::factory()->create(['type' => RegisteredOrder::TYPE_REGISTERED_ORDER, 'english_type' => RegisteredOrder::TYPE_REGISTERED_ORDER, 'name' => 'Export Last Op '.uniqid(), 'english_name' => 'Export Last Op '.uniqid()]);
        $this->order->update(['status_id' => $to->id]);

        (new ExportReport('operations', ['registered_order_id' => $this->order->id], $user->id, 'en'))->handle();

        $csv = Storage::disk('local')->get(Storage::disk('local')->files("exports/{$user->id}")[0]);
        $this->assertStringContainsString('→', $csv);
        $this->assertStringContainsString((string) $to->localized_name, $csv);
    }

    public function test_the_job_exports_dates_in_the_calendar_chosen_when_it_was_dispatched_and_leaves_no_state_behind(): void
    {
        $user = $this->userWith(['report.view_operations']);
        $this->order->update(['validity_date' => '2026-03-21']);
        $filters = ['registered_order_id' => $this->order->id];
        session()->forget('calendar_type');

        (new ExportReport('operations', $filters, $user->id, 'en', 'jalali'))->handle();
        $jalali = Storage::disk('local')->get(Storage::disk('local')->files("exports/{$user->id}")[0]);

        $this->assertStringContainsString(toPersianDate('2026-03-21'), $jalali);
        $this->assertFalse(auth()->check());
        $this->assertNull(session('calendar_type'));

        Storage::disk('local')->deleteDirectory("exports/{$user->id}");
        (new ExportReport('operations', $filters, $user->id, 'en', 'gregorian'))->handle();
        $gregorian = Storage::disk('local')->get(Storage::disk('local')->files("exports/{$user->id}")[0]);

        $this->assertStringContainsString(toGregorianDate('2026-03-21'), $gregorian);
        $this->assertStringNotContainsString(toPersianDate('2026-03-21'), $gregorian);
    }

    public function test_the_job_keeps_the_quick_filter_and_restores_the_acting_user(): void
    {
        $actor = $this->userWith([]);
        $this->actingAs($actor);
        $user = $this->userWith(['report.view_operations']);
        $seller = Company::factory()->create();
        $expired = RegisteredOrder::factory()->create(['seller_id' => $seller->id, 'validity_date' => today()->subDays(3)->toDateString()]);
        $valid = RegisteredOrder::factory()->create(['seller_id' => $seller->id, 'validity_date' => today()->addDays(90)->toDateString()]);

        (new ExportReport('operations', ['seller_id' => $seller->id, 'quick' => 'expired'], $user->id, 'en'))->handle();

        $csv = Storage::disk('local')->get(Storage::disk('local')->files("exports/{$user->id}")[0]);
        $this->assertStringContainsString((string) $expired->ro_number, $csv);
        $this->assertStringNotContainsString((string) $valid->ro_number, $csv);
        $this->assertSame($actor->id, auth()->id());
    }

    public function test_export_labels_for_is_used_when_the_service_provides_it(): void
    {
        app()->bind(\Tests\Support\Reports\StubReportService::class, fn () => new class extends \Tests\Support\Reports\StubReportService
        {
            public function exportLabelsFor(\App\Services\Reports\Support\ReportFilters $filters): array
            {
                return ['id' => 'Mode '.$filters->viewMode, 'bp_number' => 'BP'];
            }
        });
        $user = $this->userWith(['report.view_fx_supply']);

        (new ExportReport('fx_supply', ['registered_order_id' => $this->order->id, 'view_mode' => 'product'], $user->id, 'en'))->handle();

        $csv = Storage::disk('local')->get(Storage::disk('local')->files("exports/{$user->id}")[0]);
        $this->assertStringContainsString('"Mode product",BP', $csv);
        $this->assertStringNotContainsString('"BP Number"', $csv);
    }

    public function test_the_filters_array_narrows_the_exported_rows(): void
    {
        $user = $this->userWith(['report.view_fx_supply']);
        $inside = BankProfile::factory()->create(['registered_order_id' => $this->order->id]);
        $outside = BankProfile::factory()->create(['registered_order_id' => RegisteredOrder::factory()->create()->id]);

        $this->job($user)->handle();

        $csv = Storage::disk('local')->get(Storage::disk('local')->files("exports/{$user->id}")[0]);
        $this->assertStringContainsString((string) $inside->bp_number, $csv);
        $this->assertStringNotContainsString((string) $outside->bp_number, $csv);
    }

    public function test_an_unauthorised_user_gets_no_file_and_no_notification(): void
    {
        $user = $this->userWith(['report.view_operations']);

        $this->job($user)->handle();

        $this->assertSame([], Storage::disk('local')->allFiles());
        Notification::assertNothingSent();
    }

    public function test_an_unknown_report_or_user_is_refused_silently(): void
    {
        $user = $this->userWith(['report.view_fx_supply']);

        $this->job($user, 'no_such_report')->handle();
        (new ExportReport('fx_supply', [], 999999999, 'en'))->handle();

        $this->assertSame([], Storage::disk('local')->allFiles());
        Notification::assertNothingSent();
    }

    public function test_the_job_locale_is_applied_to_the_notification(): void
    {
        $user = $this->userWith(['report.view_fx_supply']);

        $this->job($user, locale: 'fa')->handle();

        $this->assertSame('fa', app()->getLocale());
        Notification::assertSentTo($user, DatabaseNotification::class, function (DatabaseNotification $notification) {
            return $notification->toArray(null)['body'] === __('resources/general/strings.export.completed', ['successful' => 0], 'fa');
        });
    }

    public function test_a_write_failure_sends_the_error_notification(): void
    {
        $user = $this->userWith(['report.view_fx_supply']);
        Carbon::setTestNow(Carbon::parse('2026-10-19 10:11:12'));
        $file = config('app.name').'-REPORT-FX_SUPPLY-101112.csv';
        Storage::disk('local')->makeDirectory("exports/{$user->id}/{$file}");

        $this->job($user)->handle();

        Notification::assertSentTo($user, DatabaseNotification::class, function (DatabaseNotification $notification) {
            return $notification->toArray(null)['status'] === 'danger';
        });
    }
}
