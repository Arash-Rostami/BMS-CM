<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Traits;

use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Priority;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Type;
use App\Models\Correspondence;
use App\Models\Status;
use App\Models\User;
use App\Rules\HasContent;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;

trait Form
{
    protected static ?Correspondence $replyParent = null;

    public static function getBodyField(): RichEditor
    {
        return RichEditor::make('body')
            ->label(__('resources/correspondence/strings.form.body'))
            ->markAsRequired()
            ->rule(new HasContent(__('resources/correspondence/strings.form.validation_required')))
            ->toolbarButtons(Correspondence::toolbarButtons())
            ->floatingToolbars(Correspondence::floatingToolbars())
            ->customTextColors()
            ->mergeTags(Correspondence::mergeTags())
            ->extraAttributes(Correspondence::extraAttributes())
            ->placeholder(Correspondence::placeholder())
            ->columnSpanFull()
            ->validationAttribute(__('resources/correspondence/strings.form.body'));
    }

    public static function getHiddenCorrespondableIdField(): Hidden
    {
        return Hidden::make('correspondable_id')
            ->default(fn() => static::getReplyParent()?->correspondable_id)
            ->dehydrated(fn($state) => filled($state));
    }

    public static function getHiddenCorrespondableTypeField(): Hidden
    {
        return Hidden::make('correspondable_type')
            ->default(fn() => static::getReplyParent()?->correspondable_type)
            ->dehydrated(fn($state) => filled($state));
    }

    public static function getHiddenParentIdField(): Hidden
    {
        return Hidden::make('parent_id')
            ->default(request()->query('parent_id'))
            ->dehydrated(fn($state) => filled($state));
    }

    public static function getIsInternalField(): Toggle
    {
        return Toggle::make('is_internal')
            ->label(__('resources/correspondence/strings.form.is_internal'))
            ->helperText(__('resources/correspondence/strings.form.helper_internal'))
            ->onColor('warning')
            ->inline(false)
            ->validationAttribute(__('resources/correspondence/strings.form.is_internal'));
    }

    public static function getIsPrivateField(): Toggle
    {
        return Toggle::make('is_private')
            ->label(__('resources/correspondence/strings.form.is_private'))
            ->helperText(__('resources/correspondence/strings.form.helper_private'))
            ->onColor('danger')
            ->onIcon('heroicon-s-lock-closed')
            ->offIcon('heroicon-s-lock-open')
            ->inline(false)
            ->validationAttribute(__('resources/correspondence/strings.form.is_private'));
    }

    public static function getParentField(): Select
    {
        return Select::make('parent_id')
            ->label(__('resources/correspondence/strings.form.parent'))
            ->relationship('parent', 'subject')
            ->searchable()
            ->hidden(fn($get) => blank($get('parent_id')))
            ->disabled()
            ->dehydrated()
            ->validationAttribute(__('resources/correspondence/strings.form.parent'));
    }

    public static function getPriorityField(): Select
    {
        return Select::make('priority')
            ->label(__('resources/correspondence/strings.form.priority'))
            ->options(Priority::class)
            ->default(Priority::NORMAL)
            ->required()
            ->native(false)
            ->validationAttribute(__('resources/correspondence/strings.form.priority'))
            ->validationMessages([
                'required' => __('resources/correspondence/strings.form.validation_required'),
            ])
            ->helperText(__('resources/correspondence/strings.form.helper_priority'));
    }

    public static function getRecipientsCcField(): TagsInput
    {
        return TagsInput::make('recipients_cc')
            ->label(__('resources/correspondence/strings.form.recipients_cc'))
            ->suggestions(fn() => User::pluck('name')->toArray())
            ->placeholder('🇨🇨')
            ->nullable()
            ->validationAttribute(__('resources/correspondence/strings.form.recipients_cc'));
    }

    public static function getRecipientsToField(): Select
    {
        return Select::make('recipients_to')
            ->label(__('resources/correspondence/strings.form.recipients_to'))
            ->options(fn() => User::pluck('name', 'id'))
            ->multiple()
            ->searchable()
            ->preload()
            ->suffixIcon('heroicon-o-user-group')
            ->placeholder('@️')
            ->required()
            ->validationAttribute(__('resources/correspondence/strings.form.recipients_to'))
            ->validationMessages([
                'required' => __('resources/correspondence/strings.form.validation_required'),
            ]);
    }

    public static function getStatusField(): Select
    {
        return Select::make('status_id')
            ->label(__('resources/correspondence/strings.form.status'))
            ->relationship(
                name: 'status',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn($query) => $query->where('english_type', Correspondence::TYPE_CORRESPONDENCE_STATUS)
            )
            ->default(fn($operation) => $operation === 'create'
                ? Status::findBy(Correspondence::TYPE_CORRESPONDENCE_STATUS, 'Submitted')?->id
                : null
            )
            ->searchable()
            ->preload()
            ->required()
            ->validationAttribute(__('resources/correspondence/strings.form.status'))
            ->validationMessages([
                'required' => __('resources/correspondence/strings.form.validation_required'),
            ]);
    }

    public static function getSubjectField(): TextInput
    {
        return TextInput::make('subject')
            ->label(__('resources/correspondence/strings.form.subject'))
            ->helperText(__('resources/correspondence/strings.form.helper_subject'))
            ->required()
            ->default(function () {
                $parent = static::getReplyParent();
                return $parent ? 'Re: ' . $parent->subject : null;
            })
            ->maxLength(255)
            ->columnSpanFull()
            ->validationAttribute(__('resources/correspondence/strings.form.subject'))
            ->validationMessages([
                'required' => __('resources/correspondence/strings.form.validation_required'),
                'max' => __('resources/correspondence/strings.form.validation_subject_max'),
            ]);
    }

    public static function getTypeField(): ToggleButtons
    {
        return ToggleButtons::make('type')
            ->label(__('resources/correspondence/strings.form.type'))
            ->options(Type::class)
            ->inline()
            ->default(Type::NOTE)
            ->required()
            ->validationAttribute(__('resources/correspondence/strings.form.type'))
            ->validationMessages([
                'required' => __('resources/correspondence/strings.form.validation_required'),
            ]);
    }

    protected static function getReplyParent()
    {
        if (static::$replyParent) {
            return static::$replyParent;
        }

        if ($parentId = request()->query('parent_id')) {
            $parent = Correspondence::find($parentId);

            while ($parent && $parent->parent_id) {
                $parent = $parent->parent;
            }

            static::$replyParent = $parent;
        }

        return static::$replyParent;
    }
}
