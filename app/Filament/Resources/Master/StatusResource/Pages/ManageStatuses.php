<?php

namespace App\Filament\Resources\Master\StatusResource\Pages;

use App\Filament\Pages\ManageRecords;
use App\Filament\Resources\StatusResource;
use App\Models\Status;
use Filament\Actions\CreateAction;

class ManageStatuses extends ManageRecords
{
    protected static string $resource = StatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
                ->mutateDataUsing(fn (array $data, ?Status $record): array => StatusResource::processApprovalWorkflow(
                    static::processCustomFields($data),
                    $record
                ))
                ->after(fn (array $data) => StatusResource::syncApprovalUsers($data)),
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
