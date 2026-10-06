<?php

namespace App\Filament\Resources\Master\ProductResource\Exports;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class ProductExporter
{
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = static::columnLabels();

        $records = $query
            ->with(['category', 'category.ancestors', 'specifications', 'specifications.creator', 'specifications.updater', 'creator', 'updater'])
            ->orderBy('id')
            ->lazy();

        $stream = fopen($absolutePath, 'w+');

        fwrite($stream, "\xEF\xBB\xBF");

        $csv = Writer::from($stream);
        $csv->insertOne(array_values($labels));

        $rows = 0;

        foreach ($records as $record) {
            $csv->insertOne(static::parentRow($record, $labels));
            $rows++;
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    public static function columnLabels(): array
    {
        return [
            'id' => __('resources/product/strings.export.id'),
            'category_path' => __('resources/product/strings.export.category_path'),
            'main_category' => __('resources/product/strings.export.main_category'),
            'name' => __('resources/product/strings.export.name'),
            'english_name' => __('resources/product/strings.export.english_name'),
            'code' => __('resources/product/strings.export.code'),
            'attributes' => __('resources/product/strings.export.attributes'),
            'slug' => __('resources/product/strings.export.slug'),
            'description' => __('resources/product/strings.export.description'),
            'in_stock' => __('resources/product/strings.export.in_stock'),
            'is_active' => __('resources/product/strings.export.is_active'),
            'roll_sheet_type' => __('resources/product/strings.export.roll_sheet_type'),
            'hs_code' => __('resources/product/strings.export.hs_code'),
            'import_duty' => __('resources/product/strings.export.import_duty'),
            'packing_type' => __('resources/product/strings.export.packing_type'),
            'vat_exempt' => __('resources/product/strings.export.vat_exempt'),
            'tax_id' => __('resources/product/strings.export.tax_id'),
            'manufacturer' => __('resources/product/strings.export.manufacturer'),
            'import_licenses' => __('resources/product/strings.export.import_licenses'),
            'extra' => __('resources/product/strings.export.extra'),
            'creator' => __('resources/product/strings.export.creator'),
            'updater' => __('resources/product/strings.export.updater'),
            'created_at' => __('resources/product/strings.export.created_at'),
            'updated_at' => __('resources/product/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function parentRow(Product $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');
        $specification = $record->specifications->first();

        $values['id'] = (string) $record->id;
        $values['category_path'] = static::plainText($record->category ? $record->category->sortAncestors() : '');
        $values['main_category'] = static::plainText($record->category?->name ?? '');
        $values['name'] = static::plainText($record->name);
        $values['english_name'] = static::plainText($record->english_name);
        $values['code'] = static::plainText($record->code);
        $values['attributes'] = static::plainText(implode(', ', Arr::wrap($record->attributes)));
        $values['slug'] = static::plainText($record->slug);
        $values['description'] = static::plainText($record->description);
        $values['in_stock'] = $record->in_stock ? __('resources/product/strings.export.yes') : __('resources/product/strings.export.no');
        $values['is_active'] = $record->is_active ? __('resources/product/strings.export.yes') : __('resources/product/strings.export.no');
        $values['roll_sheet_type'] = $record->determineRollOrSheetType() ?? '-';
        $values['hs_code'] = static::plainText($specification?->hs_code ?? '');
        $values['import_duty'] = static::plainText($specification?->import_duty ?? '');
        $values['packing_type'] = static::plainText($specification?->packing_type ?? '');
        $values['vat_exempt'] = $specification?->vat_exempt ? __('resources/product/strings.export.yes') : __('resources/product/strings.export.no');
        $values['tax_id'] = static::plainText($specification?->tax_id ?? '');
        $values['manufacturer'] = static::plainText($specification?->manufacturer ?? '');
        $values['import_licenses'] = static::importLicensesValue($specification?->import_licenses);
        $values['extra'] = static::extraValue($specification?->extra);
        $values['creator'] = static::plainText($record->creator?->name ?? '');
        $values['updater'] = static::plainText($record->updater?->name ?? '');
        $values['created_at'] = static::jalaliDate($record->created_at);
        $values['updated_at'] = static::jalaliDate($record->updated_at);

        return array_values($values);
    }

    protected static function importLicensesValue(mixed $licenses): string
    {
        if (! is_array($licenses) || empty($licenses)) {
            return '';
        }

        $allLicenses = Lang::get('resources/product/strings.form.licenses');

        return static::escapeCsvFormula(collect($licenses)
            ->map(fn ($licenseKey) => $allLicenses[$licenseKey] ?? $licenseKey)
            ->implode(', '));
    }

    protected static function extraValue(mixed $extra): string
    {
        if (empty($extra) || ! is_array($extra)) {
            return '';
        }

        return static::escapeCsvFormula(collect($extra)
            ->map(fn ($value, $key) => __('resources/product/strings.export.extra_template', ['key' => $key, 'value' => $value]))
            ->implode('| '));
    }

    protected static function plainText(mixed $value): string
    {
        $text = trim(html_entity_decode(strip_tags((string) ($value ?? '')), ENT_QUOTES));

        return static::escapeCsvFormula($text);
    }

    protected static function escapeCsvFormula(string $value): string
    {
        return (new EscapeFormula)->escapeRecord([$value])[0];
    }

    protected static function jalaliDate(mixed $date): string
    {
        return $date ? jdate($date)->format('Y-m-d') : '';
    }
}
