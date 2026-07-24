<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers;

use App\Filament\Resources\CorrespondenceResource;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Priority;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Type;
use App\Filament\Resources\Operational\CorrespondenceResource\Exports\CorrespondenceExporter;
use App\Filament\Resources\Operational\CorrespondenceResource\Traits\Filters as CorrespondenceFilters;
use App\Filament\Resources\Operational\CorrespondenceResource\Traits\Form as CorrespondenceForm;
use App\Filament\Resources\Operational\CorrespondenceResource\Traits\HandlesRecipients;
use App\Filament\Resources\Operational\CorrespondenceResource\Traits\Infolist as CorrespondenceInfolist;
use App\Filament\Resources\Operational\CorrespondenceResource\Traits\Table as CorrespondenceTable;
use App\Models\Correspondence;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CorrespondenceRelationManager extends RelationManager
{
    use CorrespondenceFilters, CorrespondenceForm, CorrespondenceInfolist, CorrespondenceTable, HandlesRecipients;

    protected static string $relationship = 'correspondences';

    public function form(Schema $schema): Schema
    {
        return CorrespondenceResource::form($schema);
    }

    public static function getModelLabel(): string
    {
        return __('resources/correspondence/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/correspondence/strings.general.plural_model_label');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/correspondence/strings.general.plural_model_label');
    }

    public function infolist(Schema $schema): Schema
    {
        return CorrespondenceResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                static::showSubject(),
                static::showType(),
                static::showPriority(),
                static::showStatus(),
                static::showRecipients(),
                static::showFlags(),
                static::showCreator(),
                static::showLastUpdated(),
            ])
            ->filters([
                static::getTypeFilter(),
                static::getPriorityFilter(),
                static::getStatusFilter(),
                static::getMyMentionsFilter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('resources/correspondence/strings.general.create_new'))
                    ->modalWidth('7xl')
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    })
                    ->using(function (array $data, string $model): Model {
                        [$cleanData, $to, $cc] = self::extractRecipients($data);
                        $record = $this->getRelationship()->create($cleanData);
                        self::syncRecipients($record, $to, $cc);

                        return $record;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->modalWidth('7xl')
                        ->mutateRecordDataUsing(function (array $data, Model $record): array {
                            $data['recipients_to'] = $record->recipients->where('pivot.type', 'to')->pluck('name')->toArray();
                            $data['recipients_cc'] = $record->recipients->where('pivot.type', 'cc')->pluck('name')->toArray();

                            return $data;
                        })
                        ->using(function (Model $record, array $data): Model {
                            [$cleanData, $to, $cc] = self::extractRecipients($data);
                            $record->update($cleanData);
                            self::syncRecipients($record, $to, $cc);

                            return $record->fresh();
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(CorrespondenceExporter::class)
                        ->formats([ExportFormat::Xlsx]),
                ]),
            ])
            ->groups([
                TableGroup::make('thread')
                    ->label(__('resources/correspondence/strings.table.thread'))
                    ->getKeyFromRecordUsing(fn (Correspondence $record) => $record->getThreadKey())
                    ->getTitleFromRecordUsing(fn (Correspondence $record) => $record->getThreadTitle())
                    ->orderQueryUsing(fn (Builder $query, string $direction) => Correspondence::orderThreadQuery($query, $direction))
                    ->scopeQueryByKeyUsing(fn (Builder $query, string $key) => Correspondence::scopeThreadQuery($query, $key))
                    ->collapsible(),
                TableGroup::make('status.name')
                    ->label(__('resources/correspondence/strings.table.status'))
                    ->getTitleFromRecordUsing(fn ($record) => $record->status?->getLocalizedNameAttribute())
                    ->collapsible(),
                TableGroup::make('priority')
                    ->label(__('resources/correspondence/strings.table.priority'))
                    ->getTitleFromRecordUsing(fn ($record) => Priority::tryFrom($record->priority)?->getLabel())
                    ->collapsible(),
                TableGroup::make('type')
                    ->label(__('resources/correspondence/strings.table.type'))
                    ->getTitleFromRecordUsing(fn ($record) => Type::tryFrom($record->type)?->getLabel())
                    ->collapsible(),
            ])
            ->defaultGroup('thread')
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Custom helper to handle Text Input (Names) -> Database IDs
     */
    protected function syncRecipientsByNames(Model $record, array $toNames, array $ccNames): void
    {
        if (empty($toNames) && empty($ccNames)) {
            self::syncRecipients($record, [], []);

            return;
        }

        $toIds = empty($toNames) ? [] : User::whereIn('name', $toNames)->pluck('id')->toArray();

        self::syncRecipients($record, $toIds, $ccNames);
    }
}
