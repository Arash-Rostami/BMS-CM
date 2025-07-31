<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Traits;

use App\Filament\Resources\Operational\PurchaseRequestResource\Enums\Status;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Storage;

trait Infolist
{
    // Main Request Details
    public static function viewRequester(): TextEntry
    {
        return TextEntry::make('requester.name')
            ->label(__('resources/purchaseRequest/strings.form.requester'));
    }

    public static function viewDepartment(): TextEntry
    {
        return TextEntry::make('department.localized_name')
            ->label(__('resources/purchaseRequest/strings.form.department'));
    }

    public static function viewCostCenter(): TextEntry
    {
        return TextEntry::make('costCenter.localized_name')
            ->label(__('resources/purchaseRequest/strings.form.cost_center'));
    }

    public static function viewRequiredByDate(): TextEntry
    {
        return TextEntry::make('required_by_date')
            ->label(__('resources/purchaseRequest/strings.form.required_by_date'))
            ->date();
    }

    public static function viewUrgency(): TextEntry
    {
        return TextEntry::make('urgency_level')
            ->label(__('resources/purchaseRequest/strings.form.urgency_level'))
            ->formatStateUsing(fn(string $state): string => __('resources/purchaseRequest/strings.general.urgency.' . strtolower($state)) ?? $state)
            ->badge();
    }

    public static function viewTotalCost(): TextEntry
    {
        return TextEntry::make('total_estimated_cost')
            ->label(__('resources/purchaseRequest/strings.form.total_estimated_cost'))
            ->formatStateUsing(
                fn($state, $record): string => isset($record->total_estimated_cost)
                    ? number_format($record->total_estimated_cost, 2, '.', ',')
                    : ''
            );
    }

    public static function viewStatus(): TextEntry
    {
        return TextEntry::make('status.english_name')
            ->label(__('resources/purchaseRequest/strings.form.status'))
            ->badge()
            ->formatStateUsing(fn(?string $state): ?string => Status::tryFrom($state)?->getLabel() ?? $state)
            ->color(fn(?string $state): ?string => Status::tryFrom($state)?->getColor() ?? 'info');
    }

    public static function viewApprover(): TextEntry
    {
        return TextEntry::make('approver.name')
            ->label(__('resources/purchaseRequest/strings.form.approver'));
    }

    public static function viewApprovalDate(): TextEntry
    {
        return TextEntry::make('approval_date')
            ->label(__('resources/purchaseRequest/strings.form.approval_date'))
            ->dateTime();
    }

    public static function viewRejectionReason(): TextEntry
    {
        return TextEntry::make('rejection_reason')
            ->columnSpanFull()
            ->label(__('resources/purchaseRequest/strings.form.rejection_reason'));
    }

    public static function viewNotes(): TextEntry
    {
        return TextEntry::make('notes')
            ->columnSpanFull()
            ->label(__('resources/purchaseRequest/strings.form.notes'));
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/purchaseRequest/strings.table.creator'));
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/purchaseRequest/strings.table.updater'));
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/purchaseRequest/strings.table.created_at'))
            ->dateTime();
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/purchaseRequest/strings.table.updated_at'))
            ->dateTime();
    }

    // Purchase Items List
    public static function viewPurchaseItems(): RepeatableEntry
    {
        return RepeatableEntry::make('items')
            ->label(__('resources/purchaseRequest/strings.infolist.purchase_items'))
            ->schema([
                TextEntry::make('product.name')
                    ->label(__('resources/purchaseRequest/strings.infolist.item_product'))
                    ->formatStateUsing(fn($record) => $record->product?->getLocalizedNameAttribute())
                    ->columnSpan(2),
                TextEntry::make('quantity')
                    ->label(__('resources/purchaseRequest/strings.infolist.item_quantity')),
                TextEntry::make('unit')
                    ->label(__('resources/purchaseRequest/strings.infolist.item_unit'))
                    ->formatStateUsing(fn($state) => __('resources/target/strings.metrics.' . $state) ?? $state),
                TextEntry::make('estimated_cost')
                    ->label(__('resources/purchaseRequest/strings.infolist.item_estimated_cost'))
                    ->money('usd', true),
                TextEntry::make('status.name')
                    ->label(__('resources/purchaseRequest/strings.infolist.item_status'))
                    ->formatStateUsing(fn($record) => $record->status?->getLocalizedNameAttribute())
                    ->badge(),
                TextEntry::make('notes')
                    ->label(__('resources/purchaseRequest/strings.infolist.item_notes'))
                    ->columnSpanFull(),
            ])->columns(5);
    }

    // Main Attachments
    public static function viewAttachments(): RepeatableEntry
    {
        return RepeatableEntry::make('attachments')
            ->label(__('resources/purchaseRequest/strings.infolist.attachments'))
            ->schema([
                TextEntry::make('path')
                    ->label('')
                    ->formatStateUsing(fn(string $state): string => basename($state))
                    ->tooltip(fn($record) => $record->name ?? '')
                    ->icon('heroicon-o-document-text')
                    ->url(fn($record): string => Storage::disk('public')->url($record->path), shouldOpenInNewTab: true),
            ])
            ->columns(1);
    }
}
