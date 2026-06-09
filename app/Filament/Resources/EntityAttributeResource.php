<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Master\EntityAttributeResource\Pages\ManageEntityAttributes;
use App\Models\EntityAttribute;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EntityAttributeResource extends Resource
{
    protected static ?string $model = EntityAttribute::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';
    protected static ?int $navigationSort = 99;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('entity_type')
                    ->label(__('resources/entityAttribute/strings.form.entity_type'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('entity_id')
                    ->label(__('resources/entityAttribute/strings.form.entity_id'))
                    ->required()
                    ->numeric(),
                TextInput::make('key')
                    ->label(__('resources/entityAttribute/strings.form.key'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('value')
                    ->label(__('resources/entityAttribute/strings.form.value'))
                    ->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entity_type')
                    ->label(__('resources/entityAttribute/strings.table.entity_type'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('entity_id')
                    ->label(__('resources/entityAttribute/strings.table.entity_id'))
                    ->sortable(),
                TextColumn::make('key')
                    ->label(__('resources/entityAttribute/strings.table.key'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('value')
                    ->label(__('resources/entityAttribute/strings.table.value'))
                    ->formatStateUsing(fn ($state): string => is_string($state) ? $state : json_encode($state, JSON_UNESCAPED_UNICODE))
                    ->limit(80)
                    ->searchable(),
                TextColumn::make('creator.name')
                    ->label(__('resources/entityAttribute/strings.table.created_by'))
                    ->sortable(),
                TextColumn::make('updater.name')
                    ->label(__('resources/entityAttribute/strings.table.updated_by'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('resources/entityAttribute/strings.table.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('resources/entityAttribute/strings.table.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('entity_type')
                    ->label(__('resources/entityAttribute/strings.filters.entity_type'))
                    ->options(fn () => EntityAttribute::query()->distinct()->pluck('entity_type', 'entity_type')->toArray()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->striped();
    }

    public static function getModelLabel(): string
    {
        return __('resources/entityAttribute/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/entityAttribute/strings.general.plural_model_label');
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
}
