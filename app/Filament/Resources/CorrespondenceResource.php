<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\FormComponents;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Priority;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Type;
use App\Filament\Resources\Operational\CorrespondenceResource\Pages\CreateCorrespondence;
use App\Filament\Resources\Operational\CorrespondenceResource\Pages\EditCorrespondence;
use App\Filament\Resources\Operational\CorrespondenceResource\Pages\ListCorrespondences;
use App\Filament\Resources\Operational\CorrespondenceResource\Traits\Filters as CorrespondenceFilters;
use App\Filament\Resources\Operational\CorrespondenceResource\Traits\Form as CorrespondenceForm;
use App\Filament\Resources\Operational\CorrespondenceResource\Traits\Infolist as CorrespondenceInfolist;
use App\Filament\Resources\Operational\CorrespondenceResource\Traits\Table as CorrespondenceTable;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\Correspondence;
use DB;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CorrespondenceResource extends Resource
{
    use CorrespondenceFilters, CorrespondenceForm, CorrespondenceInfolist, CorrespondenceTable, HasResourcePermissions;

    protected static ?string $model = Correspondence::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('resources/correspondence/strings.form.section_main'))
                            ->schema([
                                static::getHiddenParentIdField(),
                                static::getHiddenCorrespondableTypeField(),
                                static::getHiddenCorrespondableIdField(),
                                static::getSubjectField(),
                                static::getBodyField(),
                                FormComponents::getAttachmentsField(),
                            ])
                            ->columnSpan(['lg' => 2]),
                    ])
                    ->columnSpan(['lg' => 2]),
                Group::make()
                    ->schema([
                        Section::make(__('resources/correspondence/strings.form.section_settings'))
                            ->schema([
                                static::getTypeField(),
                                static::getPriorityField(),
                                static::getStatusField(),
                                static::getParentField(),
                                Group::make([
                                    static::getIsInternalField(),
                                    static::getIsPrivateField(),
                                ])->columns(2),
                            ]),
                        Section::make(__('resources/correspondence/strings.form.section_recipients'))
                            ->schema([
                                static::getRecipientsToField(),
                                static::getRecipientsCcField(),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'creator',
                'updater',
                'status',
                'recipients',
                'attachments',
                'correspondable',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $date = toYmdDate($record);
        $subject = $record->subject ?? '—';

        return "💬 {$subject} (📆 {$date})";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['subject', 'body'];
    }

    public static function getModelLabel(): string
    {
        return __('resources/correspondence/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.operational_second');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCorrespondences::route('/'),
            'create' => CreateCorrespondence::route('/create'),
            'edit' => EditCorrespondence::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/correspondence/strings.general.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Correspondence Details')
                    ->tabs([
                        Tab::make(__('resources/correspondence/strings.infolist.tab_general'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                static::viewType(),
                                                static::viewPriority(),
                                                static::viewStatus(),
                                                static::viewVisibility(),
                                            ])
                                            ->columnSpanFull(),
                                        static::viewSubject(),
                                        static::viewBody(),
                                        static::viewCreator(),
                                        static::viewCreatedAt(),
                                        static::viewUpdater(),
                                        static::viewUpdatedAt(),
                                    ])
                                    ->extraAttributes(['style' => 'margin-top: 1.5rem;'])
                                    ->columns(3),
                            ]),
                        Tab::make(__('resources/correspondence/strings.infolist.tab_recipients'))
                            ->icon('heroicon-o-users')
                            ->label(fn ($record) => tabBadge(
                                __('resources/correspondence/strings.infolist.tab_recipients'),
                                $record?->recipients->count() ?? 0,
                                'info'
                            ))
                            ->schema([
                                Section::make()
                                    ->schema([static::viewRecipients()]),
                            ])->columnSpanFull(),
                        Tab::make(__('resources/correspondence/strings.infolist.tab_attachments'))
                            ->icon('heroicon-o-paper-clip')
                            ->label(fn ($record) => tabBadge(
                                __('resources/correspondence/strings.infolist.tab_attachments'),
                                $record?->attachments->count() ?? 0,
                                'info'
                            ))
                            ->schema([
                                Section::make()
                                    ->schema([
                                        static::viewAttachments(),
                                    ]),
                            ])->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->select('*', DB::raw('COALESCE(parent_id, id) as conversation_id'));
            })
            ->columns([
                static::showReadStatus(),
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
                static::getCreatorFilter(),
                static::getTrashedFilter(),
                static::getCreationDateFilter(),
                static::getMyMentionsFilter(),
                static::getUnreadFilter(),
            ])
            ->filtersFormColumns(3)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->mutateRecordDataUsing(function (array $data, Correspondence $record): array {
                            if ($userId = auth()->id()) {
                                $record->markReadBy($userId);
                            }

                            return $data;
                        }),
                    Action::make('reply')
                        ->label(__('resources/correspondence/strings.general.reply'))
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->url(function (Correspondence $record) {
                            // to link to master thread as nested in group
                            $root = $record;
                            while ($root->parent_id) {
                                $root = $root->parent;
                            }

                            return CreateCorrespondence::getUrl(['parent_id' => $root->id]);
                        }),
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
                TableGroup::make('correspondable_id')
                    ->label(__('resources/general/strings.relevant_module.table.related_to'))
                    ->getTitleFromRecordUsing(fn ($record) => $record->correspondable?->formatted_name ?? '-')
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
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
