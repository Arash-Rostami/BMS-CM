<?php

namespace App\Filament\Resources\General;

use App\Filament\Resources\ActivityResource;
use App\Services\AuditChanges;
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
            ->visible(fn ($record): bool => static::hasHistory($record) || static::canSeeAudit($record))
            ->badge(fn ($record) => ((static::hasHistory($record) ? $record->statusHistories()->count() : 0) + (static::canSeeAudit($record) ? $record->activities()->inLog(AuditChanges::LOG_NAME)->count() : 0)) ?: null)
            ->badgeColor('info')
            ->schema([
                static::historySection(),
                static::auditSection(),
            ]);
    }

    protected static function historySection(): Section
    {
        return Section::make(__('resources/general/strings.status_history.section'))
            ->icon('heroicon-o-clock')
            ->extraAttributes(fn ($record): array => static::canSeeAudit($record) ? self::CSS : [])
            ->visible(fn ($record): bool => static::hasHistory($record))
            ->schema([
                RepeatableEntry::make('statusHistories')
                        ->hiddenLabel()
                        ->getStateUsing(fn ($record) => static::hasHistory($record) ? $record->statusHistories()->with(['status', 'fromStatus', 'user'])->latest()->get() : [])
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
            ]);
    }

    protected static function auditSection(): Section
    {
        return Section::make(__('resources/general/strings.audit.section'))
            ->icon('heroicon-o-clipboard-document-list')
            ->visible(fn ($record): bool => static::canSeeAudit($record))
            ->schema([
                RepeatableEntry::make('audit')
                        ->hiddenLabel()
                        ->getStateUsing(fn ($record) => static::auditRows($record))
                        ->schema([
                            TextEntry::make('created_at')
                                ->label(__('resources/general/strings.audit.when'))
                                ->adaptiveDateTime()
                                ->color('gray'),
                            TextEntry::make('causer')
                                ->label(__('resources/general/strings.audit.who'))
                                ->icon('heroicon-m-user')
                                ->placeholder(__('resources/general/strings.audit.system')),
                            TextEntry::make('event')
                                ->label(__('resources/general/strings.audit.event'))
                                ->formatStateUsing(fn (?string $state): string => __('resources/general/strings.audit.events.'.$state))
                                ->badge()
                                ->color(fn (?string $state): string => ActivityResource::eventColor($state)),
                            TextEntry::make('changes')
                                ->label(__('resources/general/strings.audit.changes'))
                                ->listWithLineBreaks()
                                ->columnSpanFull(),
                        ])
                        ->columns(3),
            ]);
    }

    protected static function hasHistory(?object $record): bool
    {
        return $record !== null && method_exists($record, 'statusHistories');
    }

    protected static function canSeeAudit(?object $record): bool
    {
        return $record !== null && method_exists($record, 'activities') && (auth()->user()?->can('activity.view') ?? false);
    }

    protected static function auditRows(?object $record): array
    {
        if (! static::canSeeAudit($record)) {
            return [];
        }

        $activities = $record->activities()->inLog(AuditChanges::LOG_NAME)->with(['causer' => fn ($relation) => $relation->withTrashed()])->latest('id')->limit(50)->get();
        $fields = app(AuditChanges::class)->fields($activities);

        return $activities->map(fn ($activity): array => [
            'created_at' => $activity->created_at,
            'causer' => $activity->causer?->name,
            'event' => $activity->event,
            'changes' => array_map(fn (array $field): string => "{$field['label']}: {$field['old']} | {$field['new']}", $fields[$activity->id]),
        ])->all();
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
