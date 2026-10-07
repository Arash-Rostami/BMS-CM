<?php

namespace App\Filament\Resources\Operational\TargetResource\Traits;

use App\Filament\Resources\Operational\TargetResource\Enums\Status as TargetStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Target;
use App\Services\PersianCalendar;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Morilog\Jalali\Jalalian;

trait Form
{
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
            ->validationAttribute(__('resources/target/strings.form.achieved_amount'))
            ->helperText(__('resources/target/strings.form.helper_achieved'));
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
            ->validationAttribute(__('resources/target/strings.form.achieved_quantity'))
            ->helperText(__('resources/target/strings.form.helper_achieved'));
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
            ->validationAttribute(__('resources/target/strings.form.amount'))
            ->helperText(__('resources/target/strings.form.helper_amount'));
    }

    public static function getDescriptionField(): Textarea
    {
        return Textarea::make('description')
            ->label(__('resources/target/strings.form.description'))
            ->maxLength(65535)
            ->nullable()
            ->validationMessages([
                'max' => __('resources/target/strings.form.validation_description_max'),
            ]);
    }

    public static function getEndInField()
    {
        return DatePicker::make('end_in')
            ->label(__('resources/target/strings.form.end_in'))
            ->helperText(__('resources/target/strings.form.helper_end_in'))
            ->adaptive()
            ->native(false)
            ->required()
            ->after('start_from')
            ->rule(static::getNoActiveOverlapRule())
            ->validationAttribute(__('resources/target/strings.form.end_in'))
            ->validationMessages([
                'required' => __('resources/target/strings.form.validation_required'),
                'date' => __('resources/target/strings.form.validation_date'),
                'after' => __('resources/target/strings.form.validation_end_in_after_start_from'),
            ]);
    }

    public static function getMetricsField(): Select
    {
        return Select::make('metrics')
            ->label(__('resources/target/strings.form.metrics'))
            ->options(__('resources/general/strings.metrics'))
            ->searchable()
            ->preload()
            ->nullable();
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
                'numeric' => __('resources/target/strings.form.validation_numeric'),
            ])
            ->validationAttribute(__('resources/target/strings.form.quantity'));
    }

    public static function getStartFromField()
    {
        return DatePicker::make('start_from')
            ->label(__('resources/target/strings.form.start_from'))
            ->adaptive()
            ->native(false)
            ->required()
            ->validationAttribute(__('resources/target/strings.form.start_from'))
            ->validationMessages([
                'required' => __('resources/target/strings.form.validation_required'),
                'date' => __('resources/target/strings.form.validation_date'),
            ]);
    }

    public static function getStatusField(): ToggleButtons
    {
        return ToggleButtons::make('status')
            ->label(__('resources/target/strings.form.status'))
            ->required()
            ->default('active')
            ->options(TargetStatus::class)
            ->validationAttribute(__('resources/target/strings.form.status'))
            ->validationMessages([
                'required' => __('resources/target/strings.form.validation_required'),
                'in' => __('resources/target/strings.form.validation_status_in'),
            ])
            ->inline();
    }

    public static function getTagField(): TagsInput
    {
        return TagsInput::make('tags')
            ->label(__('resources/target/strings.form.tags'))
            ->columnSpan(2)
            ->nullable();
    }

    public static function getTargetableField(): MorphToSelect
    {
        return MorphToSelect::make('targetable')
            ->label(__('resources/target/strings.form.targetable'))
            ->types([
                Type::make(Category::class)
                    ->label(__('resources/target/strings.form.targetable_category'))
                    ->titleAttribute(app()->isLocale('fa') ? 'name' : 'english_name')
                    ->searchColumns(['name', 'english_name']),
                Type::make(Product::class)
                    ->label(__('resources/target/strings.form.targetable_product'))
                    ->getOptionLabelFromRecordUsing(fn (Product $r): string => $r->customized_label)
                    ->searchColumns(['code', 'name', 'english_name']),
            ])
            ->searchable()
            ->columnSpan(2)
            ->required()
            ->modifyTypeSelectUsing(fn (Select $select): Select => $select
                ->validationAttribute(__('resources/target/strings.form.targetable'))
                ->validationMessages([
                    'required' => __('resources/target/strings.form.validation_required'),
                    'in' => __('resources/target/strings.form.validation_targetable_in'),
                ]))
            ->modifyKeySelectUsing(fn (Select $select): Select => $select
                ->validationAttribute(__('resources/target/strings.form.targetable'))
                ->validationMessages([
                    'required' => __('resources/target/strings.form.validation_required'),
                    'in' => __('resources/target/strings.form.validation_targetable_in'),
                ]));
    }

    public static function getYearField(): Select
    {
        $calendar = app(PersianCalendar::class);

        return Select::make('year')
            ->label(__('resources/target/strings.form.year'))
            ->options($calendar->yearOptions(1, 5))
            ->searchable()
            ->required()
            ->live()
            ->afterStateUpdated(function ($state, Select $component, Get $get, Set $set): void {
                if (! array_key_exists($state, $component->getOptions()) || filled($get('start_from')) || filled($get('end_in'))) {
                    return;
                }

                [$start, $end] = static::getYearBounds((int) $state);
                $set('start_from', $start->toDateString());
                $set('end_in', $end->toDateString());
            })
            ->validationAttribute(__('resources/target/strings.form.year'))
            ->validationMessages([
                'required' => __('resources/target/strings.form.validation_required'),
                'in' => __('resources/target/strings.form.validation_year_in'),
            ]);
    }

    public static function getYearBounds(int $year): array
    {
        if (! app()->isLocale('fa')) {
            return [Carbon::create($year, 1, 1), Carbon::create($year, 12, 31)];
        }

        $jalaliYear = Jalalian::fromCarbon(Carbon::create($year, 3, 21))->getYear();

        return [
            (new Jalalian($jalaliYear, 1, 1))->toCarbon(),
            (new Jalalian($jalaliYear + 1, 1, 1))->toCarbon()->subDay(),
        ];
    }

    public static function activeOverlapQuery(?string $type, mixed $id, mixed $start, mixed $end, ?Model $record = null): Builder
    {
        return Target::query()
            ->where('status', TargetStatus::Active->value)
            ->where('targetable_type', $type)
            ->where('targetable_id', $id)
            ->where('start_from', '<=', $end)
            ->where('end_in', '>=', $start)
            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()));
    }

    public static function getNoActiveOverlapRule(): Closure
    {
        return fn (Get $get, ?Model $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record): void {
            if ($get('status') !== TargetStatus::Active->value || blank($get('targetable_id')) || blank($get('start_from'))) {
                return;
            }

            $overlaps = static::activeOverlapQuery($get('targetable_type'), $get('targetable_id'), $get('start_from'), $value, $record)->exists();

            if ($overlaps) {
                $fail(__('resources/target/strings.form.validation_end_in_overlap'));
            }
        };
    }
}
