<?php

namespace App\Services\Reports\Rules;

use App\Models\Currency;
use App\Models\EntityAttribute;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ShipmentCurrencyResolver
{
    public function resolve(Shipment $shipment): array
    {
        return $this->resolveMany(collect([$shipment]))->get($shipment->id);
    }

    public function resolveMany(Collection $shipments): Collection
    {
        (new EloquentCollection($shipments->all()))->loadMissing('registeredOrder.proformaInvoices');
        $names = $this->nameMap();
        $invoiceCurrencies = $this->invoiceCurrencies($shipments);

        return $shipments->mapWithKeys(fn (Shipment $shipment) => [$shipment->id => $this->resolveWith($shipment, $names, $invoiceCurrencies[$shipment->id] ?? null)]);
    }

    protected function invoiceCurrencies(Collection $shipments): array
    {
        return EntityAttribute::where('entity_type', Shipment::class)
            ->where('key', 'commercial_invoice')
            ->whereIn('entity_id', $shipments->pluck('id')->all())
            ->pluck('value', 'entity_id')
            ->map(fn ($value) => $this->invoiceHeader($value)['currency'] ?? null)
            ->all();
    }

    protected function invoiceHeader(mixed $value): ?array
    {
        $header = is_string($value) ? json_decode($value, true) : $value;
        $header = is_string($header) ? json_decode($header, true) : $header;

        return is_array($header) ? $header : null;
    }

    protected function resolveWith(Shipment $shipment, array $names, mixed $invoiceCurrency): array
    {
        $invoice = $names[$this->normalize($invoiceCurrency)] ?? null;
        $order = $this->toId($shipment->registeredOrder?->currency_id);
        $proforma = $this->toId($shipment->registeredOrder?->proformaInvoices->first()?->main_currency_id);

        $source = match (true) {
            $invoice !== null => 'invoice',
            $order !== null => 'order',
            $proforma !== null => 'proforma',
            default => null,
        };

        return [
            'currency_id' => $invoice ?? $order ?? $proforma,
            'source' => $source,
            'mismatch' => $invoice !== null && $order !== null && $invoice !== $order,
        ];
    }

    protected function toId(mixed $id): ?int
    {
        return $id === null ? null : (int) $id;
    }

    protected function nameMap(): array
    {
        $map = [];

        Currency::query()->orderByDesc('id')->get(['id', 'name', 'english_name'])->each(function (Currency $currency) use (&$map) {
            foreach ([$currency->name, $currency->english_name] as $label) {
                $map[$this->normalize($label)] = $currency->id;
            }
        });

        unset($map['']);

        return $map;
    }

    protected function normalize(mixed $value): string
    {
        return is_string($value) ? mb_strtolower(trim($value)) : '';
    }
}
