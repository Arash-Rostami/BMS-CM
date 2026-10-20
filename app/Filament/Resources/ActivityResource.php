<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Master\ActivityResource\Pages\ManageActivities;
use App\Filament\Resources\Master\ActivityResource\Traits\Filters as ActivityFilters;
use App\Filament\Resources\Master\ActivityResource\Traits\Infolist as ActivityInfolist;
use App\Filament\Resources\Master\ActivityResource\Traits\Table as ActivityTable;
use App\Filament\Traits\HasResourcePermissions;
use App\Services\AuditChanges;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends Resource
{
    use ActivityFilters, ActivityInfolist, ActivityTable, HasResourcePermissions;

    protected static ?string $model = Activity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canEditAny(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete($record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore($record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getUpdateAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function getForceDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getForceDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function getRestoreAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getRestoreAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->inLog(AuditChanges::LOG_NAME)
            ->whereIn('subject_type', app(AuditChanges::class)->viewableModels())
            ->with([
                'causer' => fn ($relation) => $relation->withTrashed(),
                'subject' => fn ($relation) => $relation->withTrashed(),
            ]);
    }

    public static function getModelLabel(): string
    {
        return __('resources/activity/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.alerts');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageActivities::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/activity/strings.general.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                static::viewCreatedAt(),
                static::viewCauser(),
                static::viewSubjectType(),
                static::viewRecord(),
                static::viewEvent(),
                static::viewChanges(),
            ])
                ->columnSpanFull()
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return TableComponents::emptyState($table
            ->columns([
                static::showId(),
                static::showCreationTime(),
                static::showCauser(),
                static::showSubjectType(),
                static::showRecord(),
                static::showEvent(),
                static::showChanges(),
            ])
            ->filters([
                static::getSubjectTypeFilter(),
                static::getEventFilter(),
                static::getCauserFilter(),
                static::getCreationDateFilter(),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                ]),
            ])
            ->striped()
            ->reorderableColumns()
            ->defaultSort('id', 'desc'));
    }
}
