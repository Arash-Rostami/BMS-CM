<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Master\StatusResource\Exports\StatusExporter;
use App\Filament\Resources\Master\StatusResource\Pages\ManageStatuses;
use App\Filament\Resources\Master\StatusResource\Traits\Filters as StatusFilters;
use App\Filament\Resources\Master\StatusResource\Traits\Form as StatusForm;
use App\Filament\Resources\Master\StatusResource\Traits\Infolist as StatusInfolist;
use App\Filament\Resources\Master\StatusResource\Traits\Table as StatusTable;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\Status;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StatusResource extends Resource
{
    use StatusForm, StatusTable, StatusInfolist, StatusFilters, HasResourcePermissions;

    protected static ?string $model = Status::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::getType(),
                        static::getCustomType(),
                        static::getTypeCustomField(),
                        static::getEnglishType(),
                        static::getCustomEnglishType(),
                        static::getEnglishTypeCustomField(),
                        static::getName(),
                        static::getEnglishName(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'creator',
                'updater',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $date = toYmdDate($record);
        $name = $record->getLocalizedNameAttribute() ?? '-';

        return "🏷️   {$name} (📆 {$date})";
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('index', ['search' => $record->english_name ?? $record->name ?? '']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'english_name'];
    }

    public static function getModelLabel(): string
    {
        return __('resources/status/strings.general.model_label');
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.base');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStatuses::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/status/strings.general.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::viewType(),
                        static::viewEnglishType(),
                        static::viewName(),
                        static::viewEnglishName(),
                        static::viewCreator(),
                        static::viewUpdater(),
                        static::viewCreatedAt(),
                        static::viewUpdatedAt(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::showType(),
                static::showEnglishType(),
                static::showName(),
                static::showEnglishName(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getTypeFilter(),
                static::getThrashedFilter(),
                static::getCreatorFilter(),
                static::getUpdaterFilter(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(StatusExporter::class)
                ]),
            ])
            ->striped()
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
