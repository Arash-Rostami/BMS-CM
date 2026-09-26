<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Imports;

use App\Contracts\Imports\Importable;
use App\Filament\Traits\ImportDefaults;
use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Company;
use App\Models\Currency;
use App\Models\RegisteredOrder;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use Filament\Actions\Imports\Importer;

class BankProfileImporter extends Importer implements Importable
{
    use ImportDefaults;

    public const COLUMN_LABEL_KEYS = [
        'bp_number' => 'resources/bankProfile/strings.form.bp_number',
        'registered_order_id' => 'resources/bankProfile/strings.form.registered_order',
        'company_id' => 'resources/bankProfile/strings.form.company',
        'bank_id' => 'resources/bankProfile/strings.form.bank',
        'status_id' => 'resources/bankProfile/strings.form.status',
        'requested_amount' => 'resources/bankProfile/strings.form.requested_amount',
        'requested_currency_id' => 'resources/bankProfile/strings.form.requested_currency',
        'purchased_equivalent' => 'resources/bankProfile/strings.form.purchased_equivalent',
        'purchased_currency_id' => 'resources/bankProfile/strings.form.purchased_currency',
        'commission_rate' => 'resources/bankProfile/strings.form.commission_rate',
        'payment_due_date' => 'resources/bankProfile/strings.form.payment_due_date',
        'notes' => 'resources/bankProfile/strings.form.notes',
    ];

    protected static ?string $model = BankProfile::class;

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = self::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::optional('bp_number', $labels['bp_number']),
            ImportColumnDefinition::match('registered_order_id', $labels['registered_order_id'], 'registeredOrder', RegisteredOrder::class, ['ro_number']),
            ImportColumnDefinition::match('company_id', $labels['company_id'], 'company', Company::class, ['name', 'english_name'])
                ->allowNullOnMismatch(),
            ImportColumnDefinition::match('bank_id', $labels['bank_id'], 'bank', Bank::class, ['name', 'english_name'])
                ->allowNullOnMismatch(),
            ImportColumnDefinition::matchStatus('status_id', $labels['status_id'], BankProfile::TYPE_BANK_PROFILE)
                ->allowNullOnMismatch(),
            ImportColumnDefinition::optional('requested_amount', $labels['requested_amount'], isNumber: true)
                ->withFallback(fn (ImportRowContext $ctx) => 0, rejectIfStillBlank: false),
            ImportColumnDefinition::match('requested_currency_id', $labels['requested_currency_id'], 'requestedCurrency', Currency::class, ['name', 'english_name'])
                ->allowNullOnMismatch(),
            ImportColumnDefinition::optional('purchased_equivalent', $labels['purchased_equivalent'], isNumber: true)
                ->withFallback(fn (ImportRowContext $ctx) => 0, rejectIfStillBlank: false),
            ImportColumnDefinition::match('purchased_currency_id', $labels['purchased_currency_id'], 'purchasedCurrency', Currency::class, ['name', 'english_name'])
                ->allowNullOnMismatch(),
            ImportColumnDefinition::optional('commission_rate', $labels['commission_rate'], isNumber: true)
                ->withFallback(fn (ImportRowContext $ctx) => 0, rejectIfStillBlank: false),
            ImportColumnDefinition::optional('payment_due_date', $labels['payment_due_date'], isDate: true)
                ->withFallback(fn (ImportRowContext $ctx) => now()->addWeeks(2)->format('Y-m-d')),
            ImportColumnDefinition::optional('notes', $labels['notes']),
        ];
    }

    public static function getColumns(): array
    {
        return static::assertColumnNamesAllowed(array_map(
            fn (ImportColumnDefinition $definition) => ImportColumnFactory::build($definition),
            static::importColumns(),
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function columnLabels(): array
    {
        return array_map(__(...), self::COLUMN_LABEL_KEYS);
    }

    public static function eavEnabled(): bool
    {
        return false;
    }

    public static function dateDefaultsSummary(): ?string
    {
        return __('resources/bankProfile/strings.import.date_defaults');
    }

    public static function filledExamplePath(): ?string
    {
        return 'import-examples/bank-profile-filled-example.csv';
    }

    public function beforeCreate(): void
    {
        $this->fillReservedNumberIfBlank('bp_number');
    }

    public function afterFill(): void
    {
        $context = new ImportRowContext($this->record, [...$this->options, '__importer' => $this]);
        $context->columns = static::importColumns();
        $context->rawData = $this->buildRawDataMap();

        ImportPipeline::runBeforeSave($context);
    }

    public function afterSave(): void
    {
        $context = new ImportRowContext($this->record, [...$this->options, '__importer' => $this]);
        $context->columns = static::importColumns();

        ImportPipeline::runAfterSave($context);
    }

    public function recalculateAfterPersist(): void {}
}
