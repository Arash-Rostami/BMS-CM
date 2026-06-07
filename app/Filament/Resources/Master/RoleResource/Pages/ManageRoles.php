<?php

namespace App\Filament\Resources\Master\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Models\Role;
use Filament\Actions\CreateAction;
use App\Filament\Pages\ManageRecords;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
                ->mutateDataUsing(function (array $data): array {
                    $data['name'] = $data['name'] . '_' . $data['grade'];
                    return $data;
                })
                ->after(function (Role $record, array $data) {
                    $permissions = $data['permissions'] ?? [];
                    $record->syncPermissions($permissions);
                }),
        ];
    }
}
