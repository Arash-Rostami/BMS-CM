<?php

namespace Tests\Feature\Traits;

use App\Filament\Traits\ExportDefaults;
use App\Models\Bank;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Tests\TestCase;

/**
 * App\Filament\Traits\ExportDefaults standardizes getFileName() ("{app}-{MODEL}-{His}"),
 * the completed-notification body, and modifyQuery() (eager-loads creator/updater +
 * eagerLoadRelations() + a hard 1000-row cap) across every Filament\Actions\Exports\Exporter
 * subclass (BankExporter, CategoryExporter, TargetExporter, UserExporter, StatusExporter,
 * ProductExporter, ShipmentExporter, CustomExporter, PaymentExporter, CurrencyExporter,
 * CompanyExporter, …). Cross-cutting, no existing dedicated test — filamentPattern.md §1.8
 * documents a real 2026-09-22 bug where this exact cap/eager-load pipeline silently never
 * ran because of a dead `getQuery()` method name; this file is the regression guard.
 * The query is only ever built, never executed, so no DB round trip is needed.
 */
class ExportDefaultsTest extends TestCase
{
    public function test_get_file_name_follows_the_app_model_timestamp_convention(): void
    {
        $export = new Export;
        $fileName = (new ExportDefaultsBankProbe($export, [], []))->getFileName($export);

        $app = config('app.name');
        $this->assertMatchesRegularExpression('/^'.preg_quote($app, '/').'-BANK-\d{6}$/', $fileName);
    }

    public function test_modify_query_applies_a_hard_1000_row_limit(): void
    {
        $query = ExportDefaultsBankProbe::modifyQuery(Bank::query());

        $this->assertSame(1000, $query->getQuery()->limit);
    }

    public function test_modify_query_eager_loads_creator_and_updater_by_default(): void
    {
        $query = ExportDefaultsBankProbe::modifyQuery(Bank::query());

        $this->assertArrayHasKey('creator', $query->getEagerLoads());
        $this->assertArrayHasKey('updater', $query->getEagerLoads());
    }

    public function test_modify_query_merges_in_the_overridden_eager_load_relations(): void
    {
        $query = ExportDefaultsBankWithExtraEagerLoadsProbe::modifyQuery(Bank::query());

        $this->assertArrayHasKey('creator', $query->getEagerLoads());
        $this->assertArrayHasKey('someRelation', $query->getEagerLoads());
    }

    public function test_completed_notification_body_mentions_only_successful_rows_when_nothing_failed(): void
    {
        $export = new Export;
        $export->successful_rows = 42;
        $export->total_rows = 42;

        $body = ExportDefaultsBankProbe::getCompletedNotificationBody($export);

        $this->assertStringContainsString('42', $body);
        $this->assertStringNotContainsString(__('resources/general/strings.export.failed', ['failed' => 1]), $body);
    }

    public function test_completed_notification_body_appends_a_failed_count_when_rows_failed(): void
    {
        $export = new Export;
        $export->successful_rows = 8;
        $export->total_rows = 10;

        $body = ExportDefaultsBankProbe::getCompletedNotificationBody($export);

        $this->assertStringContainsString('8', $body);
        $this->assertStringContainsString('2', $body);
    }
}

class ExportDefaultsBankProbe extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Bank::class;

    public static function getColumns(): array
    {
        return [];
    }
}

class ExportDefaultsBankWithExtraEagerLoadsProbe extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Bank::class;

    public static function getColumns(): array
    {
        return [];
    }

    protected static function eagerLoadRelations(): array
    {
        return ['someRelation'];
    }
}
