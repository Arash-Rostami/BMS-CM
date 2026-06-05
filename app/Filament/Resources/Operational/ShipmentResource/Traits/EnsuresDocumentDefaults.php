<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Traits;

trait EnsuresDocumentDefaults
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $defaults = __('resources/shipment/strings.form.docs_options');
        if (! is_array($defaults)) {
            return $data;
        }

        $docs = $data['docs'] ?? [];
        $docs = array_is_list($docs) ? $docs : ($docs['items'] ?? []); // tolerate legacy {tracking, items}

        $rows  = collect($docs);
        $keyOf = fn ($name) => array_key_exists($name, $defaults)
            ? $name
            : (array_search($name, $defaults, true) ?: null);

        $received = $rows->mapWithKeys(function ($row) use ($keyOf) {
            $key = $keyOf($row['name'] ?? null);
            return $key ? [$key => (bool) ($row['received'] ?? false)] : [];
        });

        $merged = collect($defaults)->map(fn ($label, $key) => [
            'name'     => $key,
            'received' => (bool) $received->get($key, $key === 'track'),
        ])->values();

        $customs = $rows->reject(fn ($row) => $keyOf($row['name'] ?? null) !== null)->values();

        $data['docs'] = $merged->concat($customs)->all();

        return $data;
    }
}
