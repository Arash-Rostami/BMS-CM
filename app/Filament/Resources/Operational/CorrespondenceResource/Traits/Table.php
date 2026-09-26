<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Traits;

use App\Filament\Resources\CorrespondenceResource;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Priority;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Type;
use App\Jobs\ExportCorrespondences;
use App\Models\Correspondence;
use App\Models\CorrespondenceRecipient;
use App\Models\Status;
use App\Services\SmartCacheManager;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait Table
{
    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportCorrespondences')
            ->label(__('resources/correspondence/strings.export.export_correspondences'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => CorrespondenceResource::canViewAny())
            ->action(function (Collection $records): void {
                ExportCorrespondences::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function getMarkAsReadBulkAction(): BulkAction
    {
        return BulkAction::make('markAsRead')
            ->label(__('resources/correspondence/strings.bulk.mark_as_read'))
            ->icon('heroicon-o-envelope-open')
            ->action(function (Collection $records): void {
                $userId = auth()->id();

                if (! $userId) {
                    return;
                }

                CorrespondenceRecipient::query()
                    ->whereIn('correspondence_id', $records->pluck('id'))
                    ->where('user_id', $userId)
                    ->whereNull('read_at')
                    ->update(['read_at' => now(), 'updated_at' => now()]);

                SmartCacheManager::invalidate('Correspondence');

                Notification::make()
                    ->title(__('resources/correspondence/strings.bulk.mark_as_read_notification'))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/correspondence/strings.table.creator'))
            ->description(fn (Correspondence $record) => adaptiveDate($record->created_at))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showFlags(): IconColumn
    {
        return IconColumn::make('flags')
            ->label(__('resources/correspondence/strings.table.visibility'))
            ->state(function (Correspondence $record): string {
                if ($record->is_private) {
                    return 'private';
                }
                if ($record->is_internal) {
                    return 'internal';
                }

                return 'public';
            })
            ->icon(fn (string $state): string => match ($state) {
                'private' => 'heroicon-s-lock-closed',
                'internal' => 'heroicon-s-building-office',
                default => 'heroicon-o-globe-alt',
            })
            ->color(fn (string $state): string => match ($state) {
                'private' => 'danger',
                'internal' => 'warning',
                default => 'gray',
            })
            ->tooltip(fn (string $state): string => match ($state) {
                'private' => __('resources/correspondence/strings.table.visibility_private'),
                'internal' => __('resources/correspondence/strings.table.visibility_internal'),
                default => __('resources/correspondence/strings.table.visibility_public'),
            })
            ->toggleable();
    }

    public static function showReadStatus(): IconColumn
    {
        return IconColumn::make('read_status')
            ->label(__('resources/correspondence/strings.table.read_status'))
            ->state(function (Correspondence $record): ?bool {
                $userId = auth()->id();

                if (! $userId) {
                    return null;
                }

                $pivot = $record->recipients->firstWhere('id', $userId)?->pivot;

                return $pivot ? $pivot->read_at === null : null;
            })
            ->icon(fn (?bool $state): ?string => match ($state) {
                true => 'heroicon-s-envelope',
                false => 'heroicon-o-envelope-open',
                default => null,
            })
            ->color(fn (?bool $state): ?string => match ($state) {
                true => 'primary',
                false => 'gray',
                default => null,
            })
            ->tooltip(fn (?bool $state): ?string => match ($state) {
                true => __('resources/correspondence/strings.table.unread_tooltip'),
                false => __('resources/correspondence/strings.table.read_tooltip'),
                default => null,
            });
    }

    public static function showLastUpdated(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/correspondence/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPriority(): TextColumn
    {
        return TextColumn::make('priority')
            ->label(__('resources/correspondence/strings.table.priority'))
            ->badge()
            ->sortable()
            ->searchable()
            ->formatStateUsing(fn (string $state): string => Priority::tryFrom($state)?->getLabel() ?? $state)
            ->icon(fn (string $state): ?string => Priority::tryFrom($state)?->getIcon())
            ->color(fn (string $state): string => Priority::tryFrom($state)?->getColor() ?? 'gray')
            ->toggleable();
    }

    public static function showRecipients(): TextColumn
    {
        return TextColumn::make('recipients.name')
            ->label(__('resources/correspondence/strings.table.recipients'))
            ->badge()
            ->separator(',')
            ->limitList(3)
            ->tooltip(fn (Correspondence $record) => $record->recipients->pluck('name')->implode(', '))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showStatus(): TextColumn
    {
        return TextColumn::make('status.name')
            ->label(__('resources/correspondence/strings.table.status'))
            ->badge()
            ->sortable()
            ->formatStateUsing(fn (Model $record): ?string => $record->status?->getLocalizedNameAttribute())
            ->color(function (Model $record): string {
                static $colors = null;

                if ($colors === null) {
                    $colors = collect([
                        'success' => ['Approved', 'Sent', 'Published'],
                        'gray' => ['Draft', 'Pending'],
                        'danger' => ['Rejected', 'Archived'],
                    ])->map(fn ($names) => collect($names)
                        ->map(fn ($name) => Status::findBy(Correspondence::TYPE_CORRESPONDENCE_STATUS, $name)?->id)
                        ->filter()
                        ->all()
                    )->all();
                }

                $id = $record->status?->id;

                if (! $id) {
                    return 'info';
                }

                foreach ($colors as $color => $ids) {
                    if (in_array($id, $ids, true)) {
                        return $color;
                    }
                }

                return 'info';
            });
    }

    public static function showSubject(): TextColumn
    {
        return TextColumn::make('subject')
            ->label(__('resources/correspondence/strings.table.subject'))
            ->searchable()
            ->sortable()
            ->limit(50)
            ->weight(FontWeight::Bold)
            ->description(fn (Correspondence $record): string => Str::limit(strip_tags($record->body), 60));
    }

    public static function showType(): TextColumn
    {
        return TextColumn::make('type')
            ->label(__('resources/correspondence/strings.table.type'))
            ->badge()
            ->sortable()
            ->searchable()
            ->formatStateUsing(fn (string $state): string => Type::tryFrom($state)?->getLabel() ?? $state)
            ->icon(fn (string $state): ?string => Type::tryFrom($state)?->getIcon())
            ->color(fn (string $state): string => Type::tryFrom($state)?->getColor() ?? 'gray');
    }
}
