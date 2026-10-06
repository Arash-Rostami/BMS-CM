<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Support\HtmlString;
use League\Csv\Bom;
use League\Csv\Writer;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\BooleanCell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportAction extends \Filament\Actions\ImportAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->maxRows(5000);
        $this->job(\App\Jobs\ImportCsv::class);

        $modalActions = [
            Action::make('downloadExample')
                ->label(__('resources/general/strings.import.download_blank_template'))
                ->icon('heroicon-o-document-text')
                ->link()
                ->action(function (): StreamedResponse {
                    $importer = $this->getImporter();
                    $autoFilledColumns = method_exists($importer, 'autoFilledColumnNames') ? $importer::autoFilledColumnNames() : [];

                    $columns = array_filter(
                        $importer::getColumns(),
                        fn (ImportColumn $column): bool => ! in_array($column->getName(), $autoFilledColumns, true),
                    );

                    $csv = Writer::createFromFileObject(new SplTempFileObject);

                    if (filled($csvDelimiter = $this->getCsvDelimiter())) {
                        $csv->setDelimiter($csvDelimiter);
                    }

                    $csv->insertOne(array_map(
                        fn (ImportColumn $column): string => $column->getExampleHeader(),
                        $columns,
                    ));

                    $columnExamples = array_map(
                        fn (ImportColumn $column): array => $column->getExamples(),
                        $columns,
                    );

                    $exampleRowsCount = array_reduce(
                        $columnExamples,
                        fn (int $count, array $exampleData): int => max($count, count($exampleData)),
                        initial: 0,
                    );

                    $exampleRows = [];

                    foreach ($columnExamples as $exampleData) {
                        for ($i = 0; $i < $exampleRowsCount; $i++) {
                            $exampleRows[$i][] = $exampleData[$i] ?? '';
                        }
                    }

                    $csv->insertAll($exampleRows);

                    return response()->streamDownload(function () use ($csv): void {
                        $csv->setOutputBOM(Bom::Utf8);

                        echo $csv->toString();
                    }, __('filament-actions::import.example_csv.file_name', ['importer' => (string) str($importer)->classBasename()->kebab()]), [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),
            Action::make('downloadFilledExample')
                ->label(__('resources/general/strings.import.download_filled_example'))
                ->icon('heroicon-o-document-check')
                ->link()
                ->action(fn () => response()->download(storage_path('app/'.$this->filledExamplePath()))),
        ];

        $this->registerModalActions($modalActions);

        $this->modalDescription(function (): HtmlString {
            $downloadLinks = '<div style="display:flex;flex-direction:column;gap:0.5rem">'
                .'<div>'.($this->getModalAction('downloadExample')?->toHtml() ?? '').'</div>';

            if (filled($this->filledExamplePath())) {
                $downloadLinks .= '<div>'.($this->getModalAction('downloadFilledExample')?->toHtml() ?? '').'</div>';
            }

            $downloadLinks .= '</div>';

            return new HtmlString($downloadLinks.$this->buildImportGuideHtml().$this->buildAutoFilledColumnHidingStyle());
        });
    }

    protected function filledExamplePath(): ?string
    {
        $importer = $this->getImporter();

        return method_exists($importer, 'filledExamplePath') ? $importer::filledExamplePath() : null;
    }

    protected function buildAutoFilledColumnHidingStyle(): string
    {
        $importer = $this->getImporter();
        $autoFilledColumns = method_exists($importer, 'autoFilledColumnNames') ? $importer::autoFilledColumnNames() : [];

        if (! $autoFilledColumns) {
            return '';
        }

        $selectors = collect($autoFilledColumns)
            ->map(fn (string $column): string => '[data-field-wrapper]:has(select[wire\:model$="columnMap.'.e($column).'"])')
            ->implode(',');

        return '<style>'.$selectors.'{display:none}</style>';
    }

    protected function buildImportGuideHtml(): string
    {
        $importer = $this->getImporter();
        $dateDefaults = method_exists($importer, 'dateDefaultsSummary') ? $importer::dateDefaultsSummary() : null;

        $sections = array_filter([
            [__('resources/general/strings.import.guide.lookup_title'), __('resources/general/strings.import.guide.lookup_body')],
            $this instanceof GroupedImportAction
                ? [__('resources/general/strings.import.guide.grouped_rows_title'), __('resources/general/strings.import.guide.grouped_rows_body')]
                : null,
            $dateDefaults ? [__('resources/general/strings.import.guide.date_defaults_title'), $dateDefaults] : null,
            [__('resources/general/strings.import.guide.eav_title'), __('resources/general/strings.import.guide.eav_body')],
            [__('resources/general/strings.import.guide.reject_title'), __('resources/general/strings.import.guide.reject_body')],
        ]);

        $items = collect($sections)
            ->map(fn (array $section): string => '<li style="margin-bottom:0.5rem"><strong>'.e($section[0]).'</strong><br>'.e($section[1]).'</li>')
            ->implode('');

        return '<details style="margin-top:0.75rem"><summary style="cursor:pointer;font-weight:600">'
            .e(__('resources/general/strings.import.guide.title'))
            .'</summary><ul style="margin-top:0.5rem;padding-inline-start:1.25rem;list-style:disc">'
            .$items.'</ul></details>';
    }

    public function resourceGate(string $resourceClass): static
    {
        return $this->authorize(fn (): bool => $resourceClass::canCreate() && $resourceClass::canEdit(null));
    }

    /**
     * @return resource|false
     */
    public function getUploadedFileStream(TemporaryUploadedFile $file)
    {
        $realPath = $file->getRealPath();
        $bytes = $realPath ? file_get_contents($realPath) : false;

        if ($bytes === false) {
            return false;
        }

        if (str_starts_with($bytes, "PK\x03\x04")) {
            return $this->convertXlsxToCsvStream($realPath);
        }

        $withoutBom = str_starts_with($bytes, "\xEF\xBB\xBF") ? substr($bytes, 3) : $bytes;

        if (! mb_check_encoding($withoutBom, 'UTF-8')) {
            throw new RuntimeException(__('resources/general/strings.import.non_utf8'));
        }

        return parent::getUploadedFileStream($file);
    }

    /**
     * @return resource
     */
    protected function convertXlsxToCsvStream(string $path)
    {
        $options = new \OpenSpout\Reader\XLSX\Options;
        $options->SHOULD_FORMAT_DATES = true;

        $reader = new XlsxReader($options);
        $reader->open($path);

        $sheets = iterator_to_array($reader->getSheetIterator());

        if (count($sheets) > 1) {
            $reader->close();

            throw new RuntimeException(__('resources/general/strings.import.multi_sheet_rejected'));
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'bms_import_');
        register_shutdown_function(static fn () => @unlink($tempPath));

        $handle = fopen($tempPath, 'w+');

        foreach (reset($sheets)->getRowIterator() as $row) {
            $line = array_map($this->cellToString(...), $row->getCells());
            fputcsv($handle, $line);
        }

        fclose($handle);
        $reader->close();

        return fopen($tempPath, 'r');
    }

    protected function cellToString(Cell $cell): string
    {
        return match (true) {
            $cell instanceof FormulaCell => $this->formulaCellToString($cell),
            $cell instanceof ErrorCell => throw new RuntimeException(__('resources/general/strings.import.formula_cell_rejected')),
            $cell instanceof DateTimeCell => $cell->getValue()->format('Y-m-d'),
            $cell instanceof BooleanCell => $cell->getValue() ? 'true' : 'false',
            $cell instanceof EmptyCell => '',
            default => (string) $cell->getValue(),
        };
    }

    protected function formulaCellToString(FormulaCell $cell): string
    {
        $computed = $cell->getComputedValue();

        if ($computed === null) {
            throw new RuntimeException(__('resources/general/strings.import.formula_cell_rejected'));
        }

        return match (true) {
            $computed instanceof \DateTimeInterface => $computed->format('Y-m-d'),
            $computed instanceof \DateInterval => $computed->format('%r%a days'),
            is_bool($computed) => $computed ? 'true' : 'false',
            default => (string) $computed,
        };
    }

    /**
     * @return array<mixed>
     */
    public function getFileValidationRules(): array
    {
        $fileRules = [
            'extensions:csv,txt,xlsx',
            'mimetypes:text/csv,text/x-csv,application/csv,application/x-csv,text/comma-separated-values,text/x-comma-separated-values,text/plain,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip',
            'max:10240',
            fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                try {
                    $csvStream = $this->getUploadedFileStream($value);
                } catch (RuntimeException $exception) {
                    $fail($exception->getMessage());

                    return;
                }

                if (! $csvStream) {
                    return;
                }

                $csvReader = \League\Csv\Reader::from($csvStream);

                if (filled($csvDelimiter = $this->getCsvDelimiter($csvReader))) {
                    $csvReader->setDelimiter($csvDelimiter);
                }

                $csvReader->setHeaderOffset($this->getHeaderOffset() ?? 0);

                $csvColumns = $csvReader->getHeader();
                $duplicateCsvColumns = [];

                foreach (array_count_values($csvColumns) as $header => $count) {
                    if ($count > 1) {
                        $duplicateCsvColumns[] = $header;
                    }
                }

                $filledDuplicateCsvColumns = array_filter($duplicateCsvColumns, fn ($value): bool => filled($value));

                if (! empty($filledDuplicateCsvColumns)) {
                    $fail(trans_choice('filament-actions::import.modal.form.file.rules.duplicate_columns', count($filledDuplicateCsvColumns), [
                        'columns' => implode(', ', $filledDuplicateCsvColumns),
                    ]));
                }
            },
        ];

        foreach ($this->fileValidationRules as $rules) {
            $rules = $this->evaluate($rules);

            if (is_string($rules)) {
                $rules = explode('|', $rules);
            }

            $fileRules = [...$fileRules, ...$rules];
        }

        return $fileRules;
    }
}
