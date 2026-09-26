<?php

namespace App\Filament\Resources\General;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

class InfoComponents
{
    protected const CSS = [
        'style' => 'position: relative; padding-bottom: 0.5rem; margin-bottom: 0.5rem; border-radius: 2px; background: linear-gradient(to right,rgba(0,0,0,0) 0%,rgba(99,102,241,0.15) 15%,rgba(99,102,241,0.25) 50%,rgba(99,102,241,0.15) 85%,rgba(0,0,0,0) 100%) bottom / 100% 2px no-repeat;',
    ];

    public static function viewProformaInvoices(): TextEntry
    {
        return TextEntry::make('proformaInvoices.formatted_name')
            ->label(__('resources/general/strings.relevant_module.table.proforma_invoices'))
            ->wrap()
            ->html()
            ->columnSpanFull()
            ->listWithLineBreaks()
            ->visible(fn ($record) => self::relationNotEmpty($record, 'proformaInvoices'));
    }

    public static function viewPurchaseOrders(): TextEntry
    {
        return TextEntry::make('purchaseOrders.formatted_name')
            ->label(__('resources/general/strings.relevant_module.form.purchase_orders'))
            ->wrap()
            ->html()
            ->columnSpanFull()
            ->listWithLineBreaks()
            ->visible(fn ($record) => self::relationNotEmpty($record, 'purchaseOrders'));
    }

    public static function viewPurchaseRequests(): TextEntry
    {
        return TextEntry::make('purchaseRequests.formatted_name')
            ->label(__('resources/general/strings.relevant_module.form.purchase_requests'))
            ->wrap()
            ->html()
            ->columnSpanFull()
            ->listWithLineBreaks()
            ->visible(fn ($record) => self::relationNotEmpty($record, 'purchaseRequests'));
    }

    public static function viewRegisteredOrders(): TextEntry
    {
        return TextEntry::make('registeredOrders.formatted_name')
            ->label(__('resources/general/strings.relevant_module.table.registered_orders'))
            ->wrap()
            ->html()
            ->columnSpanFull()
            ->listWithLineBreaks()
            ->visible(fn ($record) => self::relationNotEmpty($record, 'registeredOrders'));
    }

    public static function getStatusHistoryTab(): Tab
    {
        return Tab::make(__('resources/general/strings.status_history.tab_label'))
            ->icon('heroicon-o-clock')
            ->badge(fn ($record) => $record?->statusHistories()->count() ?: null)
            ->badgeColor('info')
            ->schema([
                Section::make()->schema([
                    RepeatableEntry::make('statusHistories')
                        ->hiddenLabel()
                        ->getStateUsing(fn ($record) => $record?->statusHistories()->with(['status', 'fromStatus', 'user'])->latest()->get())
                        ->schema([
                            TextEntry::make('fromStatus.localized_name')
                                ->label(__('resources/general/strings.status_history.from'))
                                ->badge()
                                ->color('gray')
                                ->placeholder(__('resources/general/strings.status_history.no_previous_status')),
                            TextEntry::make('status.localized_name')
                                ->label(__('resources/general/strings.status_history.to'))
                                ->badge()
                                ->color('primary'),
                            TextEntry::make('user.name')
                                ->label(__('resources/general/strings.status_history.actor'))
                                ->icon('heroicon-m-user')
                                ->placeholder('-'),
                            TextEntry::make('created_at')
                                ->label(__('resources/general/strings.status_history.changed_at'))
                                ->adaptiveDateTime()
                                ->color('gray'),
                            TextEntry::make('reason')
                                ->label(__('resources/general/strings.status_history.reason'))
                                ->visible(fn ($record) => filled($record?->reason))
                                ->color('gray')
                                ->columnSpanFull(),
                        ])
                        ->columns(4),
                ]),
            ]);
    }

    protected static function relationNotEmpty(?object $record, string $relation): bool
    {
        if (! $record) {
            return false;
        }

        // owner model case: relation method exists
        if (method_exists($record, $relation) && is_callable([$record, $relation])) {
            try {
                return $record->{$relation}()->exists();
            } catch (\Throwable $e) {
                return false;
            }
        }

        // child or loaded relation case: check property/attribute safely
        return collect($record->{$relation} ?? null)->isNotEmpty();
    }
}
