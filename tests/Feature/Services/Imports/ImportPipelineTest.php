<?php

namespace Tests\Feature\Services\Imports;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Department;
use App\Models\ProformaInvoice;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Status;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportRowContext;
use App\Services\Imports\LocalizedMatcher;
use App\Services\Imports\Stages\AppendUnresolvedMatchNotes;
use App\Services\Imports\Stages\ApplyColumnFallbacks;
use App\Services\Imports\Stages\PersistChildRows;
use App\Services\Imports\Stages\RejectMissingManualColumns;
use App\Services\StatusWorkflow;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use LogicException;
use Tests\TestCase;

class ImportPipelineTest extends TestCase
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

    public function test_column_definition_can_combine_match_and_fallback(): void
    {
        $definition = ImportColumnDefinition::match('department_id', 'label', 'department', Department::class, ['name'])
            ->withFallback(fn () => 5, rejectIfStillBlank: true);

        $this->assertTrue($definition->hasMatchConfig());
        $this->assertTrue($definition->hasFallback());
        $this->assertTrue($definition->rejectIfStillBlank());
    }

    public function test_manual_set_has_no_match_config_and_rejects_when_blank(): void
    {
        $definition = ImportColumnDefinition::manualSet('main_currency_id', 'label');

        $this->assertFalse($definition->hasMatchConfig());
        $this->assertTrue($definition->hasFallback());
        $this->assertTrue($definition->rejectIfStillBlank());
    }

    public function test_apply_column_fallbacks_only_fills_blank_columns_on_new_records(): void
    {
        $record = new PurchaseRequest(['requester_id' => null, 'cost_center_id' => 7]);
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::optional('requester_id', 'label')->withFallback(fn () => 99),
            ImportColumnDefinition::optional('cost_center_id', 'label')->withFallback(fn () => 1),
        ];

        (new ApplyColumnFallbacks)->handle($context, fn ($c) => $c);

        $this->assertSame(99, $record->requester_id);
        $this->assertSame(7, $record->cost_center_id);
    }

    public function test_apply_column_fallbacks_skips_existing_records(): void
    {
        $record = new PurchaseRequest(['requester_id' => null]);
        $record->exists = true;
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::optional('requester_id', 'label')->withFallback(fn () => 99),
        ];

        (new ApplyColumnFallbacks)->handle($context, fn ($c) => $c);

        $this->assertNull($record->requester_id);
    }

    public function test_reject_missing_manual_columns_throws_on_blank_required_field(): void
    {
        $record = new ProformaInvoice(['main_currency_id' => null]);
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::manualSet('main_currency_id', 'resources/proformaInvoice/strings.form.main_currency'),
        ];

        $this->expectException(RowImportFailedException::class);

        (new RejectMissingManualColumns)->handle($context, fn ($c) => $c);
    }

    public function test_reject_missing_manual_columns_passes_when_filled(): void
    {
        $record = new ProformaInvoice(['main_currency_id' => 3]);
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::manualSet('main_currency_id', 'label'),
        ];

        $result = (new RejectMissingManualColumns)->handle($context, fn ($c) => $c);

        $this->assertSame($context, $result);
    }

    public function test_reject_missing_manual_columns_does_not_reject_existing_records(): void
    {
        $record = new ProformaInvoice(['main_currency_id' => null]);
        $record->exists = true;
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::manualSet('main_currency_id', 'label'),
        ];

        $result = (new RejectMissingManualColumns)->handle($context, fn ($c) => $c);

        $this->assertSame($context, $result);
    }

    private function bindColumnToRecord(\Filament\Actions\Imports\ImportColumn $column, \Illuminate\Database\Eloquent\Model $record): \Filament\Actions\Imports\ImportColumn
    {
        $importer = new class(new \Filament\Actions\Imports\Models\Import, [], []) extends \Filament\Actions\Imports\Importer
        {
            public static function getColumns(): array
            {
                return [];
            }

            public static function getCompletedNotificationBody(\Filament\Actions\Imports\Models\Import $import): string
            {
                return '';
            }
        };

        \Closure::bind(function () use ($record) {
            $this->record = $record;
        }, $importer, \Filament\Actions\Imports\Importer::class)();

        return $column->importer($importer);
    }

    public function test_import_column_factory_builds_a_relationship_bound_column_from_a_match_definition(): void
    {
        $column = ImportColumnFactory::build(
            ImportColumnDefinition::match('department_id', 'resources/purchaseRequest/strings.form.department', 'department', Department::class, ['name', 'english_name'])
        );

        $this->assertSame('department_id', $column->getName());
        $this->assertSame('department', $column->getRelationshipName());
        $this->assertStringContainsString('(department_id)', $column->getLabel());
        $this->assertStringContainsString('(department_id)', $column->getExampleHeader());
    }

    public function test_persist_child_rows_fails_loudly_when_pending_rows_have_no_child_importer_hook(): void
    {
        $record = new PurchaseRequest;
        $record->exists = true;
        $context = new ImportRowContext($record, ['__importer' => new class
        {
            // deliberately no childImporterInstance() — simulates a future module
            // that declares pending child rows but forgets to wire the hook.
        }]);
        $context->pendingChildRows = [['product_id' => 1]];

        $this->expectException(LogicException::class);

        (new PersistChildRows)->handle($context, fn ($c) => $c);
    }

    public function test_localized_matcher_enum_map_merges_all_three_locales(): void
    {
        $map = LocalizedMatcher::enumMap('resources/purchaseRequest/strings.general.urgency');

        foreach (['en', 'fa', 'fr'] as $locale) {
            $label = mb_strtolower((string) trans('resources/purchaseRequest/strings.general.urgency', [], $locale)['high']);
            $this->assertArrayHasKey($label, $map);
            $this->assertSame('high', $map[$label]);
        }
    }

    public function test_relation_match_column_rejects_unresolved_value_by_default(): void
    {
        $column = $this->bindColumnToRecord(
            ImportColumnFactory::build(
                ImportColumnDefinition::match('department_id', 'label', 'department', Department::class, ['name', 'english_name'])
            ),
            new PurchaseRequest
        );

        $fails = Validator::make(['v' => 'NoSuchDepartment'], ['v' => $column->getDataValidationRules()])->fails();

        $this->assertTrue($fails);
    }

    public function test_relation_match_column_with_allow_null_on_mismatch_does_not_reject_unresolved_value(): void
    {
        $column = ImportColumnFactory::build(
            ImportColumnDefinition::match('secondary_currency_id', 'label', 'secondaryCurrency', Currency::class, ['name', 'english_name'])
                ->allowNullOnMismatch()
        );

        $this->assertNull($column->castState('NoSuchCurrency'));
    }

    public function test_status_match_column_rejects_unresolved_value_by_default(): void
    {
        // RegisteredOrder's status type has no ordered/initial status configured today,
        // so it's unaffected by StatusWorkflow's forced-initial rule (see the next two tests).
        $column = ImportColumnFactory::build(
            ImportColumnDefinition::matchStatus('status_id', 'label', RegisteredOrder::TYPE_REGISTERED_ORDER)
        );

        $this->expectException(RowImportFailedException::class);

        $column->castState('NoSuchStatus');
    }

    public function test_status_match_column_with_allow_null_on_mismatch_casts_unresolved_value_to_null(): void
    {
        $column = ImportColumnFactory::build(
            ImportColumnDefinition::matchStatus('status_id', 'label', RegisteredOrder::TYPE_REGISTERED_ORDER)
                ->allowNullOnMismatch()
        );

        $this->assertNull($column->castState('NoSuchStatus'));
    }

    public function test_apply_column_fallbacks_forces_the_initial_status_on_new_records_when_the_type_has_a_workflow_order(): void
    {
        $initial = StatusWorkflow::initialFor(PurchaseRequest::TYPE_PURCHASE_REQUEST);
        $gatedStatus = Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)
            ->whereNotNull('approval_permission')
            ->first();

        $record = new PurchaseRequest(['status_id' => $gatedStatus->id]);
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::matchStatus('status_id', 'label', PurchaseRequest::TYPE_PURCHASE_REQUEST),
        ];

        (new ApplyColumnFallbacks)->handle($context, fn ($c) => $c);

        $this->assertSame($initial->id, $record->status_id);
    }

    public function test_apply_column_fallbacks_leaves_status_untouched_on_existing_records(): void
    {
        $record = new PurchaseRequest(['status_id' => 999]);
        $record->exists = true;
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::matchStatus('status_id', 'label', PurchaseRequest::TYPE_PURCHASE_REQUEST),
        ];

        (new ApplyColumnFallbacks)->handle($context, fn ($c) => $c);

        $this->assertSame(999, $record->status_id);
    }

    public function test_apply_column_fallbacks_does_not_force_a_status_for_a_type_with_no_workflow_order(): void
    {
        $record = new PurchaseRequest(['status_id' => null]);
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::matchStatus('status_id', 'label', RegisteredOrder::TYPE_REGISTERED_ORDER)
                ->withFallback(fn () => 321),
        ];

        (new ApplyColumnFallbacks)->handle($context, fn ($c) => $c);

        $this->assertSame(321, $record->status_id);
    }

    public function test_status_match_column_with_reject_if_still_blank_rejects_when_the_fallback_itself_resolves_to_null_on_a_new_record(): void
    {
        $record = new RegisteredOrder(['status_id' => null]);
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::matchStatus('status_id', 'label', RegisteredOrder::TYPE_REGISTERED_ORDER)
                ->withFallback(fn () => null, rejectIfStillBlank: true),
        ];

        (new ApplyColumnFallbacks)->handle($context, fn ($c) => $c);

        $this->expectException(RowImportFailedException::class);

        (new RejectMissingManualColumns)->handle($context, fn ($c) => $c);
    }

    public function test_status_match_column_with_reject_if_still_blank_does_not_reject_existing_records(): void
    {
        $record = new RegisteredOrder(['status_id' => 999]);
        $record->exists = true;
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::matchStatus('status_id', 'label', RegisteredOrder::TYPE_REGISTERED_ORDER)
                ->withFallback(fn () => null, rejectIfStillBlank: true),
        ];

        (new ApplyColumnFallbacks)->handle($context, fn ($c) => $c);
        $result = (new RejectMissingManualColumns)->handle($context, fn ($c) => $c);

        $this->assertSame($context, $result);
        $this->assertSame(999, $record->status_id);
    }

    public function test_status_match_column_memoizes_lookups_for_the_lifetime_of_the_built_column(): void
    {
        $status = Status::factory()->create([
            'type' => RegisteredOrder::TYPE_REGISTERED_ORDER,
            'english_type' => RegisteredOrder::TYPE_REGISTERED_ORDER,
        ]);

        $column = ImportColumnFactory::build(
            ImportColumnDefinition::matchStatus('status_id', 'label', RegisteredOrder::TYPE_REGISTERED_ORDER)
        );

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach (range(1, 5) as $_) {
            $this->assertSame($status->id, $column->castState($status->english_name));
        }

        $statusQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'from `statuses`'))
            ->count();

        DB::disableQueryLog();

        $this->assertSame(1, $statusQueries);
    }

    public function test_status_match_column_cache_does_not_leak_across_separately_built_columns(): void
    {
        $status = Status::factory()->create([
            'type' => RegisteredOrder::TYPE_REGISTERED_ORDER,
            'english_type' => RegisteredOrder::TYPE_REGISTERED_ORDER,
        ]);

        $firstRunColumn = ImportColumnFactory::build(
            ImportColumnDefinition::matchStatus('status_id', 'label', RegisteredOrder::TYPE_REGISTERED_ORDER)
        );
        $firstRunColumn->castState($status->english_name);

        $secondRunColumn = ImportColumnFactory::build(
            ImportColumnDefinition::matchStatus('status_id', 'label', RegisteredOrder::TYPE_REGISTERED_ORDER)
        );

        DB::flushQueryLog();
        DB::enableQueryLog();

        $secondRunColumn->castState($status->english_name);

        $statusQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'from `statuses`'))
            ->count();

        DB::disableQueryLog();

        $this->assertSame(1, $statusQueries);
    }

    public function test_enum_match_column_rejects_unresolved_value_by_default(): void
    {
        $column = ImportColumnFactory::build(
            ImportColumnDefinition::matchEnum('urgency_level', 'label', 'resources/purchaseRequest/strings.general.urgency')
        );

        $cast = $column->castState('not-a-real-urgency');
        $fails = Validator::make(['v' => $cast], ['v' => $column->getDataValidationRules()])->fails();

        $this->assertTrue($fails);
    }

    public function test_enum_match_column_with_allow_null_on_mismatch_casts_unresolved_value_to_null(): void
    {
        $column = ImportColumnFactory::build(
            ImportColumnDefinition::matchEnum('urgency_level', 'label', 'resources/purchaseRequest/strings.general.urgency')
                ->allowNullOnMismatch()
        );

        $this->assertNull($column->castState('not-a-real-urgency'));
    }

    public function test_blank_input_on_a_match_column_is_unaffected_by_the_reject_flag(): void
    {
        $strict = ImportColumnFactory::build(ImportColumnDefinition::matchEnum('urgency_level', 'label', 'resources/purchaseRequest/strings.general.urgency'));
        $lenient = ImportColumnFactory::build(ImportColumnDefinition::matchEnum('urgency_level', 'label', 'resources/purchaseRequest/strings.general.urgency')->allowNullOnMismatch());

        $strictFails = Validator::make(['v' => $strict->castState('')], ['v' => $strict->getDataValidationRules()])->fails();
        $lenientFails = Validator::make(['v' => $lenient->castState('')], ['v' => $lenient->getDataValidationRules()])->fails();

        $this->assertFalse($strictFails);
        $this->assertFalse($lenientFails);
    }

    public function test_append_unresolved_match_notes_appends_a_note_when_a_null_on_mismatch_column_fails_to_resolve(): void
    {
        $record = new ProformaInvoice(['secondary_currency_id' => null, 'notes' => null]);
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::match('secondary_currency_id', 'resources/proformaInvoice/strings.form.secondary_currency', 'secondaryCurrency', Currency::class, ['name', 'english_name'])
                ->allowNullOnMismatch(),
        ];
        $context->rawData = ['secondary_currency_id' => 'Not A Real Currency'];

        (new AppendUnresolvedMatchNotes)->handle($context, fn ($c) => $c);

        $this->assertStringContainsString('Not A Real Currency', $record->notes);
        $this->assertNull($record->secondary_currency_id);
    }

    public function test_append_unresolved_match_notes_skips_reject_on_mismatch_columns(): void
    {
        $record = new ProformaInvoice(['notes' => null]);
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::match('main_currency_id', 'label', 'mainCurrency', Currency::class, ['name']),
        ];
        $context->rawData = ['main_currency_id' => 'Nonexistent'];

        (new AppendUnresolvedMatchNotes)->handle($context, fn ($c) => $c);

        $this->assertNull($record->notes);
    }

    public function test_append_unresolved_match_notes_preserves_existing_notes_content(): void
    {
        $record = new ProformaInvoice(['notes' => 'Original note', 'secondary_currency_id' => null]);
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::match('secondary_currency_id', 'label', 'secondaryCurrency', Currency::class, ['name'])->allowNullOnMismatch(),
        ];
        $context->rawData = ['secondary_currency_id' => 'Bogus'];

        (new AppendUnresolvedMatchNotes)->handle($context, fn ($c) => $c);

        $this->assertStringStartsWith('Original note', $record->notes);
        $this->assertStringContainsString('Bogus', $record->notes);
    }

    public function test_append_unresolved_match_notes_skips_gracefully_for_a_model_without_a_notes_column(): void
    {
        $record = new Currency;
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::match('secondary_currency_id', 'label', 'secondaryCurrency', Currency::class, ['name'])
                ->allowNullOnMismatch(),
        ];
        $context->rawData = ['secondary_currency_id' => 'Bogus'];

        $result = (new AppendUnresolvedMatchNotes)->handle($context, fn ($c) => $c);

        $this->assertSame($context, $result);
        $this->assertFalse($record->isDirty('notes'));
    }

    public function test_append_unresolved_match_notes_does_not_crash_for_a_model_with_no_notes_column(): void
    {
        $record = new Category(['parent_id' => null]);
        $context = new ImportRowContext($record);
        $context->columns = [
            ImportColumnDefinition::match('parent_id', 'label', 'parent', Category::class, ['name', 'english_name'])
                ->allowNullOnMismatch(),
        ];
        $context->rawData = ['parent_id' => 'No Such Category'];

        (new AppendUnresolvedMatchNotes)->handle($context, fn ($c) => $c);

        $this->assertNull($record->parent_id);
    }
}
