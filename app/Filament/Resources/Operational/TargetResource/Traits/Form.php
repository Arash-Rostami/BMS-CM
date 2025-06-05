<?php

namespace App\Filament\Resources\Operational\TargetResource\Traits;


use App\Services\PersianCalendar;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use App\Models\Category;
use App\Models\Product;
use App\Filament\Resources\Operational\TargetResource\Enums\Status as TargetStatus;
use Illuminate\Support\Facades\Cache;

// Import Builder


trait Form
{
    public static function getTargetableField(): MorphToSelect
    {
        return MorphToSelect::make('targetable')
            ->label(__('resources/target/strings.form.targetable'))
            ->types([
                MorphToSelect\Type::make(Category::class)
                    ->label(__('resources/target/strings.form.targetable_category'))
                    ->titleAttribute(app()->isLocale('fa') ? 'name' : 'english_name')
                    ->searchColumns(['name', 'english_name']),
                MorphToSelect\Type::make(Product::class)
                    ->label(__('resources/target/strings.form.targetable_product'))
                    ->getOptionLabelFromRecordUsing(fn(Product $r): string => $r->customized_label)
                    ->searchColumns(['code', 'name', 'english_name'])
            ])
            ->searchable()
            ->columnSpan(2)
            ->required();
    }


    public static function getYearField(): Select
    {
        $calendar = app(PersianCalendar::class);

        return Select::make('year')
            ->label(__('resources/target/strings.form.year'))
            ->options($calendar->yearOptions(1, 5))
            ->searchable()
            ->required()
            ->validationMessages([
                'required' => __('resources/target/strings.form.validation_required'),
            ]);
    }


    public static function getStartFromField()
    {
        return DatePicker::make('start_from')
            ->label(__('resources/target/strings.form.start_from'))
            ->when(app()->isLocale('fa'), fn($column) => $column->jalali())
            ->required()
            ->validationMessages([
                'required' => __('resources/target/strings.form.validation_required'),
            ]);
    }

    public static function getEndInField()
    {
        return DatePicker::make('end_in')
            ->label(__('resources/target/strings.form.end_in'))
            ->when(app()->isLocale('fa'), fn($column) => $column->jalali())
            ->required()
            ->after('start_from')
            ->validationMessages([
                'required' => __('resources/target/strings.form.validation_required'),
                'after' => __('resources/target/strings.form.validation_end_in_after_start_from')
            ]);
    }

    public static function getQuantityField(): TextInput
    {
        return TextInput::make('quantity')
            ->label(__('resources/target/strings.form.quantity'))
            ->required()
            ->numeric()
            ->step(0.01)
            ->validationMessages([
                'required' => __('resources/target/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/target/strings.form.quantity'));
    }

    public static function getAmountField(): TextInput
    {
        return TextInput::make('amount')
            ->label(__('resources/target/strings.form.amount'))
            ->numeric()
            ->step(0.01)
            ->validationMessages([
                'numeric' => __('resources/target/strings.form.validation_numeric'),
            ])
            ->validationAttribute(__('resources/target/strings.form.amount'));
    }

    public static function getMetricsField(): Select
    {
        return Select::make('metrics')
            ->label(__('resources/target/strings.form.metrics'))
            ->options(__('resources/target/strings.metrics'))
            ->searchable()
            ->preload()
            ->nullable();
    }

    public static function getDescriptionField(): Textarea
    {
        return Textarea::make('description')
            ->label(__('resources/target/strings.form.description'))
            ->maxLength(65535)
            ->nullable();
    }

    public static function getStatusField(): ToggleButtons
    {
        return ToggleButtons::make('status')
            ->label(__('resources/target/strings.form.status'))
            ->required()
            ->default('active')
            ->options(TargetStatus::class)
            ->validationMessages([
                'required' => __('resources/target/strings.form.validation_required'),
            ])
            ->inline();
    }

    public static function getAchievedQuantityField(): TextInput
    {
        return TextInput::make('achieved_quantity')
            ->label(__('resources/target/strings.form.achieved_quantity'))
            ->numeric()
            ->step(0.01)
            ->nullable()
            ->validationMessages([
                'numeric' => __('resources/target/strings.form.validation_numeric'),
            ])
            ->validationAttribute(__('resources/target/strings.form.achieved_quantity'));
    }

    public static function getAchievedAmountField(): TextInput
    {
        return TextInput::make('achieved_amount')
            ->label(__('resources/target/strings.form.achieved_amount'))
            ->numeric()
            ->step(0.01)
            ->nullable()
            ->validationMessages([
                'numeric' => __('resources/target/strings.form.validation_numeric'),
            ])
            ->validationAttribute(__('resources/target/strings.form.achieved_amount'));
    }


    public static function getTagField(): TagsInput
    {
        return TagsInput::make('tags')
            ->label(__('resources/target/strings.form.tags'))
            ->columnSpan(2)
            ->nullable();
    }
}
