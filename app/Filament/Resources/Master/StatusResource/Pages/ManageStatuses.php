<?php

namespace App\Filament\Resources\Master\StatusResource\Pages;

use App\Filament\Resources\StatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageStatuses extends ManageRecords
{
    protected static string $resource = StatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-sparkles')
                ->mutateFormDataUsing(fn(array $data): array => static::processCustomFields($data)),
        ];
    }

    public static function processCustomFields(array $data): array
    {
        $fieldMappings = [
            'type' => 'custom_type',
            'english_type' => 'custom_english_type',
        ];

        foreach ($fieldMappings as $field => $flag) {
            if ($data[$flag] ?? false) {
                $data[$field] = $data["{$field}_custom"] ?? null;
            }
            unset($data[$flag], $data["{$field}_custom"]);
        }

        return $data;
    }
}
