<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Master\EntityAttributeResource\Pages\ManageEntityAttributes;
use App\Filament\Resources\Master\EntityAttributeResource\Traits\Filters as EntityAttributeFilters;
use App\Filament\Resources\Master\EntityAttributeResource\Traits\Infolist as EntityAttributeInfolist;
use App\Filament\Resources\Master\EntityAttributeResource\Traits\Table as EntityAttributeTable;
use App\Models\EntityAttribute;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EntityAttributeResource extends Resource
{
    use EntityAttributeTable, EntityAttributeInfolist, EntityAttributeFilters;

    protected static ?string $model = EntityAttribute::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';
    protected static ?int $navigationSort = 99;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['creator', 'updater'])
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getModelLabel(): string
    {
        return __('resources/entityAttribute/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.base');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEntityAttributes::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/entityAttribute/strings.general.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                static::viewEntityType(),
                static::viewEntityId(),
                static::viewKey(),
                static::viewValue(),
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
                static::showEntityType(),
                static::showEntityId(),
                static::showKey(),
                static::showValue(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getEntityTypeFilter(),
                static::getKeyFilter(),
                static::getCreatorFilter(),
                static::getTrashedFilter(),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->striped()
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
