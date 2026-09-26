<?php

namespace Tests\Unit;

use App\Filament\Resources\Operational\PurchaseRequestResource\Imports\PurchaseRequestImporter;
use App\Filament\Resources\Operational\PurchaseRequestResource\Imports\PurchaseRequestItemImporter;
use App\Filament\Traits\ImportDefaults;
use App\Observers\CodeGeneratingObserver;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\QueryException;
use LogicException;
use PDOException;
use ReflectionMethod;
use Tests\TestCase;

class ImportDefaultsTest extends TestCase
{
    private function columnsFor(string $importerClass, array $options = []): \Illuminate\Support\Collection
    {
        $importer = new $importerClass(new Import, [], $options);

        return collect($importer->getCachedColumns())->keyBy(fn (ImportColumn $column) => $column->getName());
    }

    public function test_import_modal_wording_says_excel_in_all_locales(): void
    {
        $expected = [
            'en' => ['Upload an Excel file', 'Download example Excel file', 'Uploaded file is too large'],
            'fa' => ['آپلود یک فایل اکسل', 'دانلود نمونه فایل اکسل', 'فایل آپلود شده خیلی بزرگ است'],
            'fr' => ['Télécharger un fichier Excel', 'Télécharger le fichier Excel exemple', 'Le fichier est trop volumineux'],
        ];

        foreach ($expected as $locale => [$placeholder, $exampleLabel, $maxRowsTitle]) {
            app()->setLocale($locale);
            $this->assertSame($placeholder, __('filament-actions::import.modal.form.file.placeholder'), "Placeholder override missing for [{$locale}].");
            $this->assertSame($exampleLabel, __('filament-actions::import.modal.actions.download_example.label'), "Example-download override missing for [{$locale}].");
            $this->assertSame($maxRowsTitle, __('filament-actions::import.notifications.max_rows.title'), "Max-rows override missing for [{$locale}].");
        }
    }

    public function test_text_column_folds_persian_and_arabic_digits(): void
    {
        $columns = $this->columnsFor(PurchaseRequestImporter::class);

        $this->assertSame('PR-14020501', $columns['pr_number']->castState('  PR-۱۴۰۲۰۵۰۱  '));
        $this->assertSame('PR-14020501', $columns['pr_number']->castState('PR-١٤٠٢٠٥٠١'));
    }

    public function test_number_column_folds_digits_and_stays_a_string(): void
    {
        $columns = $this->columnsFor(PurchaseRequestImporter::class);

        $cast = $columns['total_estimated_cost']->castState('۱۲۳۴.۵۶');

        $this->assertSame('1234.56', $cast);
        $this->assertIsString($cast);
    }

    public function test_number_column_rejects_eu_style_and_parenthesized_values(): void
    {
        $columns = $this->columnsFor(PurchaseRequestImporter::class);
        $column = $columns['total_estimated_cost'];

        $rules = $column->getDataValidationRules();
        $failures = [];
        $fail = function (string $message) use (&$failures): \Illuminate\Translation\PotentiallyTranslatedString {
            $failures[] = $message;

            return new \Illuminate\Translation\PotentiallyTranslatedString($message, app('translator'));
        };

        foreach ($rules as $rule) {
            if ($rule instanceof \Closure) {
                $rule('total_estimated_cost', '1.234,56', $fail);
                $rule('total_estimated_cost', '(100)', $fail);
            }
        }

        $this->assertNotEmpty($failures);
    }

    public function test_date_column_rejects_invalid_leap_day_gregorian(): void
    {
        $columns = $this->columnsFor(PurchaseRequestImporter::class, ['date_format' => 'Y-m-d', 'jalali' => false]);

        $this->expectException(RowImportFailedException::class);
        $columns['required_by_date']->castState('2023-02-30');
    }

    public function test_date_column_rejects_invalid_leap_day_jalali(): void
    {
        $columns = $this->columnsFor(PurchaseRequestImporter::class, ['date_format' => 'Y-m-d', 'jalali' => true]);

        $this->expectException(RowImportFailedException::class);
        $columns['required_by_date']->castState('1402-12-30');
    }

    public function test_date_column_accepts_valid_gregorian_and_jalali_dates(): void
    {
        $gregorian = $this->columnsFor(PurchaseRequestImporter::class, ['date_format' => 'Y-m-d', 'jalali' => false]);
        $this->assertSame('2026-09-22', $gregorian['required_by_date']->castState('2026-09-22'));

        $jalali = $this->columnsFor(PurchaseRequestImporter::class, ['date_format' => 'Y-m-d', 'jalali' => true]);
        $this->assertSame('2024-03-20', $jalali['required_by_date']->castState('1403-01-01'));
    }

    public function test_denylisted_column_names_throw_at_definition_time(): void
    {
        $this->expectException(LogicException::class);

        $importer = new class(new Import, [], []) extends Importer
        {
            use ImportDefaults;

            protected static ?string $model = \App\Models\PurchaseRequest::class;

            public static function getColumns(): array
            {
                return self::assertColumnNamesAllowed([ImportColumn::make('user_id')]);
            }
        };

        $importer::getColumns();
    }

    public function test_registered_importers_never_expose_denylisted_columns(): void
    {
        $denied = ['id', 'user_id', 'updated_by_id'];

        foreach ([PurchaseRequestImporter::class, PurchaseRequestItemImporter::class] as $importerClass) {
            foreach ($importerClass::getColumns() as $column) {
                $name = $column->getName();
                $this->assertNotContains($name, $denied, "{$importerClass} exposes denylisted column [{$name}]");
                $this->assertFalse(str_ends_with($name, '_type'), "{$importerClass} exposes a *_type column [{$name}]");
            }
        }
    }

    public function test_during_import_restores_flag_via_finally_even_when_closure_throws(): void
    {
        try {
            CodeGeneratingObserver::duringImport(function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        $reflection = new \ReflectionProperty(CodeGeneratingObserver::class, 'duringImport');
        $reflection->setAccessible(true);

        $this->assertFalse($reflection->getValue());
    }

    public function test_nested_during_import_does_not_lift_the_outer_suspension(): void
    {
        $reflection = new \ReflectionProperty(CodeGeneratingObserver::class, 'duringImport');
        $reflection->setAccessible(true);

        CodeGeneratingObserver::duringImport(function () use ($reflection): void {
            $this->assertTrue($reflection->getValue());

            CodeGeneratingObserver::duringImport(function () use ($reflection): void {
                $this->assertTrue($reflection->getValue());
            });

            $this->assertTrue($reflection->getValue(), 'Outer suspension must still be active after the nested call returns.');
        });

        $this->assertFalse($reflection->getValue());
    }

    public function test_nested_notification_suspension_does_not_lift_the_outer_suspension(): void
    {
        $reflection = new \ReflectionProperty(\App\Observers\NotificationDispatcher::class, 'suspended');
        $reflection->setAccessible(true);

        \App\Observers\NotificationDispatcher::suspended(function () use ($reflection): void {
            $this->assertTrue($reflection->getValue());

            \App\Observers\NotificationDispatcher::suspended(function () use ($reflection): void {
                $this->assertTrue($reflection->getValue());
            });

            $this->assertTrue($reflection->getValue(), 'Outer suspension must still be active after the nested call returns.');
        });

        $this->assertFalse($reflection->getValue());
    }

    public function test_is_deadlock_classifies_mysql_error_codes(): void
    {
        $method = new ReflectionMethod(ImportDefaultsProbe::class, 'isDeadlock');
        $method->setAccessible(true);

        $deadlockPrevious = new PDOException('deadlock');
        $deadlockPrevious->errorInfo = ['40001', 1213, 'Deadlock found'];
        $deadlock = new QueryException('mysql', 'select 1', [], $deadlockPrevious);

        $lockWaitPrevious = new PDOException('lock wait timeout');
        $lockWaitPrevious->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded'];
        $lockWaitTimeout = new QueryException('mysql', 'select 1', [], $lockWaitPrevious);

        $duplicatePrevious = new PDOException('duplicate');
        $duplicatePrevious->errorInfo = ['23000', 1062, 'Duplicate entry'];
        $notDeadlock = new QueryException('mysql', 'select 1', [], $duplicatePrevious);

        $this->assertTrue($method->invoke(null, $deadlock));
        $this->assertTrue($method->invoke(null, $lockWaitTimeout));
        $this->assertFalse($method->invoke(null, $notDeadlock));
    }
}

class ImportDefaultsProbe extends Importer
{
    use ImportDefaults;

    protected static ?string $model = \App\Models\PurchaseRequest::class;

    public static function getColumns(): array
    {
        return [];
    }
}
