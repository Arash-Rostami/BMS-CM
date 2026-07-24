<?php

namespace App\Filament\Resources\Master\ProductResource\Pages;

use App\Filament\Pages\ManageRecords;
use App\Filament\Resources\ProductResource;
use Filament\Actions\CreateAction;

class ManageProducts extends ManageRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
                ->mutateDataUsing(fn (array $data) => self::setSlugAndCategory($data)),
        ];

    }

    public static function setSlugAndCategory(array $data): array
    {
        $deepest = null;
        if (! empty($data['categories']) && is_array($data['categories'])) {
            foreach ($data['categories'] as $catId) {
                if (! empty($catId)) {
                    $deepest = $catId;
                }
            }
        }

        $data['category_id'] = $deepest;
        unset($data['categories']);

        return $data;
    }
}
