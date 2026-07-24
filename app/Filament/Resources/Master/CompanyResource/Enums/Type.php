<?php

namespace App\Filament\Resources\Master\CompanyResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;

enum Type: string implements HasColor, HasIcon
{
    case Seller = 'Seller';
    case Buyer = 'Buyer';
    case Supplier = 'Supplier';
    case Distributor = 'Distributor';
    case Manufacturer = 'Manufacturer';
    case Retailer = 'Retailer';
    case Wholesaler = 'Wholesaler';
    case ServiceProvider = 'Service Provider';

    private const FARSI_MAP = [
        'فروشنده' => 'Seller',
        'خریدار' => 'Buyer',
        'تامین‌کننده' => 'Supplier',
        'توزیع‌کننده' => 'Distributor',
        'تولیدکننده' => 'Manufacturer',
        'خرده‌فروش' => 'Retailer',
        'عمده‌فروش' => 'Wholesaler',
        'ارائه‌دهنده خدمات' => 'Service Provider',
    ];

    public function getColor(): string
    {
        return match ($this) {
            self::Seller => 'success',
            self::Buyer => 'info',
            self::Supplier => 'warning',
            self::Manufacturer => 'danger',
            self::Distributor => 'primary',
            self::Retailer => 'gray',
            self::Wholesaler => 'indigo',
            self::ServiceProvider => 'purple',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Seller => 'heroicon-s-arrow-trending-up',
            self::Buyer => 'heroicon-s-shopping-cart',
            self::Supplier => 'heroicon-s-truck',
            self::Manufacturer => 'heroicon-s-cog',
            self::Distributor => 'heroicon-s-share',
            self::Retailer => 'heroicon-s-building-storefront',
            self::Wholesaler => 'heroicon-s-cube',
            self::ServiceProvider => 'heroicon-s-wrench-screwdriver',
        };
    }

    public static function tryFromLocalised(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $englishValue = self::FARSI_MAP[$value] ?? $value;
        $case = self::tryFrom($englishValue);

        if ($case === null) {
            return null;
        }

        return $case;
    }
}
