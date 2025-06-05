<?php

// Create these directories and files:
// app/Filament/Resources/Master/ProductResource/Enums/InStockStatus.php
// app/Filament/Resources/Master/ProductResource/RelationManagers/ProductAttributesRelationManager.php
// app/Filament/Resources/Master/ProductResource/Pages/ListProducts.php
// app/Filament/Resources/Master/ProductResource/Pages/CreateProduct.php
// app/Filament/Resources/Master/ProductResource/Pages/EditProduct.php
// app/Filament/Resources/Master/ProductResource/Pages/ViewProduct.php
// lang/en/resources/product/strings.php
// lang/fa/resources/product/strings.php



namespace App\Filament\Resources\Master\ProductResource\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InStockStatus: int implements HasLabel, HasIcon, HasColor
{
    case IN_STOCK = 1;
    case OUT_OF_STOCK = 0;

    public function getLabel(): string
    {
        return match ($this) {
            self::IN_STOCK => __('resources/product/strings.table.in_stock_true'), // Example localized label
            self::OUT_OF_STOCK => __('resources/product/strings.table.in_stock_false'), // Example localized label
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::IN_STOCK => 'heroicon-o-check-circle', // Example icon
            self::OUT_OF_STOCK => 'heroicon-o-x-circle', // Example icon
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::IN_STOCK => 'success', // Example color
            self::OUT_OF_STOCK => 'danger', // Example color
        };
    }
}
