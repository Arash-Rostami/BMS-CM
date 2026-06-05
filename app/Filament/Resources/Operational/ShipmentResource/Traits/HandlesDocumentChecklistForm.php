<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Traits;

trait HandlesDocumentChecklistForm
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $defaults = __('resources/shipment/strings.form.docs_options');
        if (! is_array($defaults)) {
            return $data;
        }

        $docs = $data['docs'] ?? [];
        $docs = array_is_list($docs) ? $docs : ($docs['items'] ?? []);
        $rows = collect($docs);

        $track = $rows->firstWhere('name', 'track');
        $data['doc_tracking'] = $track === null ? true : (bool) ($track['received'] ?? false);

        $options = collect($defaults)->except('track');
        $keyOf = fn ($name) => array_key_exists($name, $options->all())
            ? $name
            : (array_search($name, $options->all(), true) ?: null);

        $received = $rows->mapWithKeys(function ($row) use ($keyOf) {
            $key = $keyOf($row['name'] ?? null);
            return $key ? [$key => (bool) ($row['received'] ?? false)] : [];
        });

        $merged = $options->map(fn ($label, $key) => [
            'name'     => $key,
            'received' => (bool) $received->get($key, false),
        ])->values();

        $customs = $rows->reject(fn ($row) => ($row['name'] ?? null) === 'track' || $keyOf($row['name'] ?? null) !== null)->values();

        $data['docs'] = $merged->concat($customs)->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->reserveDocumentTracking($data);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->reserveDocumentTracking($data);
    }

    private function reserveDocumentTracking(array $data): array
    {
        $tracking = (bool) ($data['doc_tracking'] ?? true);
        unset($data['doc_tracking']);

        $rows = array_values($data['docs'] ?? []);
        array_unshift($rows, ['name' => 'track', 'received' => $tracking]);

        $data['docs'] = $rows;

        return $data;
    }
}
