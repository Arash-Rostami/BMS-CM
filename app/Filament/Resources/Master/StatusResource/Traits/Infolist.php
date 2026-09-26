<?php

namespace App\Filament\Resources\Master\StatusResource\Traits;

use App\Models\Permission;
use App\Models\Status;
use Filament\Infolists\Components\TextEntry;

trait Infolist
{
    public static function viewApprovalGate(): TextEntry
    {
        return TextEntry::make('approval_permission')
            ->label(__('resources/status/strings.infolist.approval_gate'))
            ->badge()
            ->icon(fn (?string $state) => filled($state) ? 'heroicon-m-lock-closed' : 'heroicon-m-lock-open')
            ->color(fn (?string $state) => filled($state) ? 'warning' : 'gray')
            ->formatStateUsing(fn (?string $state) => filled($state)
                ? __('resources/status/strings.infolist.approval_gate_on')
                : __('resources/status/strings.infolist.approval_gate_off'))
            ->placeholder('-');
    }

    public static function viewApprovalUsers(): TextEntry
    {
        return TextEntry::make('approval_users')
            ->label(__('resources/status/strings.infolist.approval_users'))
            ->getStateUsing(fn (?Status $record) => $record?->approval_permission
                ? Permission::where('name', $record->approval_permission)->first()?->users()->pluck('name')->implode(', ')
                : null)
            ->visible(fn (?Status $record) => filled($record?->approval_permission))
            ->placeholder('-');
    }

    public static function viewStageOrder(): TextEntry
    {
        return TextEntry::make('stage_order')
            ->label(__('resources/status/strings.infolist.stage_order'))
            ->badge()
            ->color('info')
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/status/strings.infolist.created_at'))
            ->adaptiveDateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/status/strings.infolist.creator'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewEnglishName(): TextEntry
    {
        return TextEntry::make('english_name')
            ->label(__('resources/status/strings.infolist.english_name'))
            ->icon('heroicon-m-language')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewEnglishType(): TextEntry
    {
        return TextEntry::make('english_type')
            ->label(__('resources/status/strings.infolist.english_type'))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-language')
            ->placeholder('-');
    }

    public static function viewName(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/status/strings.infolist.name'))
            ->icon('heroicon-m-tag')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewType(): TextEntry
    {
        return TextEntry::make('type')
            ->label(__('resources/status/strings.infolist.type'))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-rectangle-stack')
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/status/strings.infolist.updated_at'))
            ->adaptiveDateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/status/strings.infolist.updater'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }
}
