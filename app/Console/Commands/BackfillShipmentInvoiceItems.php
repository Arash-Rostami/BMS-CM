<?php

namespace App\Console\Commands;

use App\Filament\Resources\ShipmentResource;
use App\Models\EntityAttribute;
use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\Shipment;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillShipmentInvoiceItems extends Command
{
    protected $signature = 'shipments:backfill-invoice-items {--dry-run : Report what would change without writing} {--prune : Remove items from the stored JSON of fully copied shipments}';

    protected $description = 'Copy commercial invoice items from the shipment EAV JSON into shipment_items';

    private ?Collection $products = null;

    private array $stats = ['shipments' => 0, 'copied' => 0, 'skipped_existing' => 0, 'lines' => 0, 'unmatched' => 0, 'pruned' => 0, 'failed' => 0];

    private array $byStep = [];

    public function handle(): int
    {
        EntityAttribute::where('entity_type', Shipment::class)
            ->where('key', 'commercial_invoice')
            ->orderBy('id')
            ->each(fn (EntityAttribute $attr) => $this->backfill($attr));

        $this->report();

        return $this->stats['failed'] ? self::FAILURE : self::SUCCESS;
    }

    private function backfill(EntityAttribute $attr): void
    {
        $header = $this->decode($attr->value);
        $shipment = Shipment::withTrashed()->find($attr->entity_id);

        if (! $shipment || ! is_array($header)) {
            return;
        }

        $this->stats['shipments']++;
        $lines = ShipmentResource::normalizeInvoiceKeys(['items' => array_map(
            fn (array $line) => ['name' => $line['name'] ?? $line['description'] ?? null, 'english_name' => $line['english_name'] ?? $line['english_description'] ?? null] + $line,
            array_values(array_filter($header['items'] ?? [], 'is_array')),
        )])['items'];

        if ($shipment->items()->exists()) {
            $this->stats['skipped_existing']++;
            $this->pruneCopied($attr, $header, $shipment, count($lines));

            return;
        }

        $rows = $this->rows($lines, $header, $shipment);
        $this->stats['lines'] += count($rows);
        $this->stats['unmatched'] += count(array_filter($rows, fn ($row) => $row['product_id'] === null));

        if ($this->option('dry-run')) {
            return;
        }

        try {
            DB::transaction(fn () => $this->write($attr, $shipment, $header, $rows));
        } catch (\Throwable $e) {
            $this->stats['failed']++;
            $this->error("Shipment {$shipment->id}: {$e->getMessage()}");
        }
    }

    private function write(EntityAttribute $attr, Shipment $shipment, array $header, array $rows): void
    {
        if ($shipment->items()->lockForUpdate()->exists()) {
            $this->stats['skipped_existing']++;

            return;
        }

        $shipment->items()->createMany($rows);

        if ($shipment->items()->count() !== count($rows)) {
            throw new \RuntimeException("Item count mismatch for shipment {$shipment->id}");
        }

        Shipment::withTrashed()->whereKey($shipment->id)->toBase()->update([
            'total_net_weight' => round(array_sum(array_column($rows, 'net_weight')), 5),
            'total_gross_weight' => round(array_sum(array_column($rows, 'gross_weight')), 5),
            'grand_total' => $header['grand_total'] ?? null,
        ]);

        $this->stats['copied']++;

        if ($this->option('prune')) {
            $this->prune($attr, $header);
        }
    }

    private function pruneCopied(EntityAttribute $attr, array $header, Shipment $shipment, int $lineCount): void
    {
        if ($this->option('dry-run') || ! $this->option('prune') || ! isset($header['items']) || $shipment->items()->count() !== $lineCount) {
            return;
        }

        $this->prune($attr, $header);
    }

    private function prune(EntityAttribute $attr, array $header): void
    {
        $attr->update(['value' => Arr::except($header, ['items'])]);
        $this->stats['pruned']++;
    }

    private function rows(array $lines, array $header, Shipment $shipment): array
    {
        $pi = ProformaInvoice::with('items.product')->find($header['proforma_invoice_id'] ?? null);
        $shipment->loadMissing('registeredOrder.items.product');

        $scopes = [
            'proforma invoice' => $this->names($pi?->items->pluck('product')),
            'registered order' => $this->names($shipment->registeredOrder?->items->pluck('product')),
            'catalogue' => $this->catalogue(),
        ];
        $singleProduct = $pi?->items->pluck('product_id')->filter()->unique();

        return array_map(function (array $line) use ($scopes, $singleProduct) {
            [$productId, $step] = $this->match($line, $scopes, $singleProduct);
            $this->byStep[$step] = ($this->byStep[$step] ?? 0) + 1;

            return [
                'product_id' => $productId,
                'name' => $line['name'],
                'english_name' => $line['english_name'],
                'hs_code' => $line['hs_code'] ?? null,
                'origin' => $line['origin'] ?? null,
                'unit' => $line['unit'] ?? null,
                'quantity' => $line['quantity'] ?? null,
                'unit_price' => $line['unit_price'] ?? null,
                'total_amount' => $line['total_amount'] ?? null,
                'net_weight' => $line['net_weight'] ?? null,
                'gross_weight' => $line['gross_weight'] ?? null,
            ];
        }, $lines);
    }

    private function match(array $line, array $scopes, ?Collection $singleProduct): array
    {
        $keys = $this->normalized([$line['english_name'], $line['name']]);

        foreach ($scopes as $step => $names) {
            $ids = $names->only($keys)->flatten()->unique();

            if ($ids->count() === 1) {
                return [$ids->first(), $step];
            }
        }

        return $singleProduct?->count() === 1 ? [$singleProduct->first(), 'single-product invoice'] : [null, 'unmatched'];
    }

    private function names(?iterable $products): Collection
    {
        return collect($products ?? [])->filter()->unique('id')
            ->flatMap(fn (Product $product) => collect($this->normalized([$product->english_name, $product->name]))
                ->map(fn ($key) => ['key' => $key, 'id' => $product->id]))
            ->groupBy('key')->map(fn (Collection $group) => $group->pluck('id')->unique());
    }

    private function catalogue(): Collection
    {
        return $this->products ??= $this->names(Product::query()->select(['id', 'name', 'english_name'])->get());
    }

    private function normalized(array $values): array
    {
        return collect($values)->filter()
            ->map(fn ($value) => trim(preg_replace('/\s+/u', ' ', Str::lower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string) $value)))))
            ->filter()->unique()->values()->all();
    }

    private function decode(mixed $value): mixed
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }

    private function report(): void
    {
        $this->table(['Metric', 'Count'], collect($this->stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all());
        $this->table(['Matched by', 'Lines'], collect($this->byStep)->map(fn ($count, $step) => [$step, $count])->values()->all());

        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing was written.');
        }
    }
}
