<?php

namespace App\Filament\Resources\Master\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageProducts extends ManageRecords
{
    protected static string $resource = ProductResource::class;


    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-sparkles')
                ->mutateFormDataUsing(fn(array $data) => self::setSlugAndCategory($data))
        ];

    }

    public static function setSlugAndCategory(array $data): array
    {
        $deepest = null;
        if (!empty($data['categories']) && is_array($data['categories'])) {
            foreach ($data['categories'] as $catId) {
                if (!empty($catId)) {
                    $deepest = $catId;
                }
            }
        }

        $data['category_id'] = $deepest;
        unset($data['categories']);

        return $data;
    }
}
