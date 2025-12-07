<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Traits;

use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Priority;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Type;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Storage;

trait Infolist
{
    public static function viewAttachments(): RepeatableEntry
    {
        return RepeatableEntry::make('attachments')
            ->label(__('resources/general/strings.attachments.attachments'))
            ->schema([
                TextEntry::make('path')
                    ->hiddenLabel()
                    ->formatStateUsing(fn(string $state): string => basename($state))
                    ->tooltip(fn($record) => $record->name ?? '')
                    ->icon('heroicon-m-paper-clip')
                    ->color('primary')
                    ->url(fn($record) => Storage::disk('public')->url($record->path), shouldOpenInNewTab: true),
            ])
            ->columns(1)
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewBody(): TextEntry
    {
        return TextEntry::make('body')
            ->label(__('resources/correspondence/strings.form.body'))
            ->html()
            ->columnSpanFull()
            ->markdown()
            ->prose()
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/correspondence/strings.table.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/correspondence/strings.table.creator'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewPriority(): TextEntry
    {
        return TextEntry::make('priority')
            ->label(__('resources/correspondence/strings.form.priority'))
            ->formatStateUsing(fn(string $state): string => Priority::tryFrom($state)?->getLabel() ?? $state)
            ->badge()
            ->color(fn(string $state): string => Priority::tryFrom($state)?->getColor() ?? 'gray')
            ->icon('heroicon-m-flag')
            ->placeholder('-');
    }

    public static function viewRecipients(): RepeatableEntry
    {
        return RepeatableEntry::make('recipients')
            ->label(__('resources/correspondence/strings.form.recipients'))
            ->schema([
                TextEntry::make('name')
                    ->hiddenLabel()
                    ->badge()
                    ->icon(fn($record) => $record?->pivot->type === 'to' ? 'heroicon-m-user' : 'heroicon-m-users')
                    ->formatStateUsing(fn($state, $record) => $state . " (" . ($record?->pivot->type === 'to' ? 'To' : 'CC') . ")")
                    ->color(fn($record) => $record?->pivot->type === 'to' ? 'primary' : 'gray'),
            ])
            ->grid(3)
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewStatus(): TextEntry
    {
        return TextEntry::make('status.name')
            ->label(__('resources/correspondence/strings.form.status'))
            ->formatStateUsing(fn($record) => $record->status?->getLocalizedNameAttribute())
            ->badge()
            ->color('info')
            ->placeholder('-');
    }

    public static function viewSubject(): TextEntry
    {
        return TextEntry::make('subject')
            ->label(__('resources/correspondence/strings.form.subject'))
            ->icon('heroicon-m-envelope')
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewType(): TextEntry
    {
        return TextEntry::make('type')
            ->label(__('resources/correspondence/strings.form.type'))
            ->formatStateUsing(fn(string $state): string => Type::tryFrom($state)?->getLabel() ?? $state)
            ->badge()
            ->color(fn(string $state): string => Type::tryFrom($state)?->getColor() ?? 'gray')
            ->icon('heroicon-m-tag')
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/correspondence/strings.table.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/correspondence/strings.table.updater'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }

    public static function viewVisibility(): IconEntry
    {
        return IconEntry::make('is_internal')
            ->label(__('resources/correspondence/strings.form.is_internal'))
            ->boolean()
            ->trueIcon('heroicon-m-lock-closed')
            ->falseIcon('heroicon-m-globe-alt')
            ->trueColor('warning')
            ->falseColor('success');
    }
}
