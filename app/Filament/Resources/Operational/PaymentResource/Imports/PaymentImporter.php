<?php

namespace App\Filament\Resources\Operational\PaymentResource\Imports;

use App\Contracts\Imports\Importable;
use App\Filament\Traits\ImportDefaults;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use App\Services\Imports\LocalizedMatcher;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;

class PaymentImporter extends Importer implements Importable
{
    use ImportDefaults;

    public const COLUMN_LABEL_KEYS = [
        'payment_no' => 'resources/payment/strings.form.payment_no',
        'purchase_order_number' => 'resources/payment/strings.import.purchase_order_number',
        'registered_order_number' => 'resources/payment/strings.import.registered_order_number',
        'payment_date' => 'resources/payment/strings.form.payment_date',
        'payment_deadline' => 'resources/payment/strings.form.payment_deadline',
        'status_id' => 'resources/payment/strings.form.status',
        'payor_id' => 'resources/payment/strings.form.payor',
        'payee_id' => 'resources/payment/strings.form.payee',
        'currency_id' => 'resources/payment/strings.form.currency',
        'bank_id' => 'resources/payment/strings.form.bank',
        'payable_amount' => 'resources/payment/strings.form.payable_amount',
        'total_amount' => 'resources/payment/strings.form.total_amount',
        'exchange_rate' => 'resources/payment/strings.form.exchange_rate',
        'bank_charges' => 'resources/payment/strings.form.bank_charges',
        'beneficiary_name' => 'resources/payment/strings.form.beneficiary_name',
        'beneficiary_address' => 'resources/payment/strings.form.beneficiary_address',
        'bank_address' => 'resources/payment/strings.form.bank_address',
        'account_no' => 'resources/payment/strings.form.account_no',
        'swift' => 'resources/payment/strings.form.swift',
        'iban' => 'resources/payment/strings.form.iban',
        'notes' => 'resources/payment/strings.form.notes',
    ];

    protected static ?string $model = Payment::class;

    public static function importColumns(): array
    {
        $labels = self::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::optional('payment_no', $labels['payment_no']),
            ImportColumnDefinition::optional('payment_date', $labels['payment_date'], isDate: true)
                ->withFallback(fn (ImportRowContext $ctx) => now()->format('Y-m-d')),
            ImportColumnDefinition::optional('payment_deadline', $labels['payment_deadline'], isDate: true),
            ImportColumnDefinition::matchStatus('status_id', $labels['status_id'], Payment::TYPE_PAYMENT)
                ->allowNullOnMismatch(),
            ImportColumnDefinition::match('payor_id', $labels['payor_id'], 'payor', Company::class, ['name', 'english_name'])
                ->allowNullOnMismatch(),
            ImportColumnDefinition::match('payee_id', $labels['payee_id'], 'payee', Company::class, ['name', 'english_name'])
                ->allowNullOnMismatch(),
            ImportColumnDefinition::match('currency_id', $labels['currency_id'], 'currency', Currency::class, ['name', 'english_name'])
                ->allowNullOnMismatch(),
            ImportColumnDefinition::match('bank_id', $labels['bank_id'], 'bank', Bank::class, ['name', 'english_name'])
                ->allowNullOnMismatch(),
            ImportColumnDefinition::optional('payable_amount', $labels['payable_amount'], isNumber: true)
                ->withFallback(fn (ImportRowContext $ctx) => 0, rejectIfStillBlank: false),
            ImportColumnDefinition::optional('total_amount', $labels['total_amount'], isNumber: true)
                ->withFallback(fn (ImportRowContext $ctx) => 0, rejectIfStillBlank: false),
            ImportColumnDefinition::optional('exchange_rate', $labels['exchange_rate'], isNumber: true)
                ->withFallback(fn (ImportRowContext $ctx) => 0, rejectIfStillBlank: false),
            ImportColumnDefinition::optional('bank_charges', $labels['bank_charges'], isNumber: true)
                ->withFallback(fn (ImportRowContext $ctx) => 0, rejectIfStillBlank: false),
            ImportColumnDefinition::optional('beneficiary_name', $labels['beneficiary_name']),
            ImportColumnDefinition::optional('beneficiary_address', $labels['beneficiary_address']),
            ImportColumnDefinition::optional('bank_address', $labels['bank_address']),
            ImportColumnDefinition::optional('account_no', $labels['account_no']),
            ImportColumnDefinition::optional('swift', $labels['swift']),
            ImportColumnDefinition::optional('iban', $labels['iban']),
            ImportColumnDefinition::optional('notes', $labels['notes']),
        ];
    }

    public static function getColumns(): array
    {
        return static::assertColumnNamesAllowed([
            ...array_map(fn (ImportColumnDefinition $definition) => ImportColumnFactory::build($definition), static::importColumns()),
            static::targetableColumn('purchase_order_number'),
            static::targetableColumn('registered_order_number'),
        ]);
    }

    protected static function targetableColumn(string $name): ImportColumn
    {
        $labelKey = self::COLUMN_LABEL_KEYS[$name];
        $label = LocalizedMatcher::columnLabel($name, $labelKey);

        return ImportColumn::make($name)
            ->label($label)
            ->exampleHeader($label)
            ->guess(LocalizedMatcher::localizedGuesses($labelKey))
            ->ignoreBlankState()
            ->castStateUsing(fn ($state) => is_string($state) ? trim($state) : $state);
    }

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
        return __('resources/payment/strings.import.date_defaults');
    }

    public static function filledExamplePath(): ?string
    {
        return 'import-examples/payment-filled-example.csv';
    }

    public function beforeCreate(): void
    {
        $this->fillReservedNumberIfBlank('payment_no');
    }

    public function beforeFill(): void
    {
        $poRaw = trim((string) ($this->data['purchase_order_number'] ?? ''));
        $roRaw = trim((string) ($this->data['registered_order_number'] ?? ''));
        unset($this->data['purchase_order_number'], $this->data['registered_order_number']);

        if (filled($poRaw) && filled($roRaw)) {
            throw new RowImportFailedException(__('resources/payment/strings.import.targetable_ambiguous'));
        }

        if (filled($poRaw)) {
            $target = PurchaseOrder::where('po_number', $poRaw)->first();

            if (! $target) {
                throw new RowImportFailedException(__('resources/payment/strings.import.targetable_not_found', ['value' => $poRaw]));
            }

            $this->record->targetable_type = PurchaseOrder::class;
            $this->record->targetable_id = $target->id;
        } elseif (filled($roRaw)) {
            $target = RegisteredOrder::where('ro_number', $roRaw)->first();

            if (! $target) {
                throw new RowImportFailedException(__('resources/payment/strings.import.targetable_not_found', ['value' => $roRaw]));
            }

            $this->record->targetable_type = RegisteredOrder::class;
            $this->record->targetable_id = $target->id;
        } elseif (! $this->record->exists) {
            throw new RowImportFailedException(__('resources/payment/strings.import.targetable_required'));
        }
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
