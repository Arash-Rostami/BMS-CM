<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TrackingStatus: string implements HasColor, HasIcon, HasLabel
{
    case DepartedFromOrigin = 'Departed From Origin';
    case InTransit = 'In Transit';
    case ArrivedAtDestination = 'Arrived At Destination';
    case CustomsClearance = 'Customs Clearance';
    case OutForDelivery = 'Out for Delivery';
    case Delivered = 'Delivered';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DepartedFromOrigin => 'info',
            self::InTransit => 'primary',
            self::ArrivedAtDestination => 'success',
            self::CustomsClearance => 'warning',
            self::OutForDelivery => 'primary',
            self::Delivered => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DepartedFromOrigin => 'heroicon-o-paper-airplane',
            self::InTransit => 'heroicon-o-truck',
            self::ArrivedAtDestination => 'heroicon-o-map-pin',
            self::CustomsClearance => 'heroicon-o-clipboard-document-check',
            self::OutForDelivery => 'heroicon-o-home',
            self::Delivered => 'heroicon-o-check-badge',
        };
    }

    public function getLabel(): ?string
    {
        return __('resources/shipment/strings.general.tracking_status.'.str($this->name)->snake());
    }
}
