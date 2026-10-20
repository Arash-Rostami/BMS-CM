<?php

namespace App\Filament\Pages\Reports\Concerns;

use App\Filament\Resources\Operational\CustomResource\Enums\ClearanceStatus;
use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Custom;
use App\Models\Department;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Status;
use App\Models\User;
use App\Services\AuditChanges;
use App\Services\PermissionLabeler;
use App\Services\Reports\Builders\OperationsReportService;
use App\Services\Reports\Checks\CustomChecks;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Rules\RequesterStages;
use App\Services\Reports\Support\ReportFilters;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

trait HasReportFilters
{
    private const WIDE_SELECTS = ['date_basis', 'departure_basis', 'rate_basis', 'arrival_basis', 'value_basis', 'cleared_from_stage', 'commitment_state', 'activity_window', 'stage'];

    public static function getDateFromField(): DatePicker
    {
        return DatePicker::make('date_from')
            ->label(__('resources/report/strings.filters.date_from'))
            ->native(false)
            ->adaptive();
    }

    public static function getDateUntilField(): DatePicker
    {
        return DatePicker::make('date_until')
            ->label(__('resources/report/strings.filters.date_until'))
            ->native(false)
            ->adaptive();
    }

    public static function getSellerField(): Select
    {
        return static::masterSelect('seller_id', __('resources/report/strings.filters.seller'))
            ->options(fn () => static::localizedOptions(Company::active()->ofAnyType(Company::TYPE_SERVICE_ALL_SELLERS)));
    }

    public static function getBuyerField(): Select
    {
        return static::masterSelect('buyer_id', __('resources/report/strings.filters.buyer'))
            ->options(fn () => static::localizedOptions(Company::active()->buyers()));
    }

    public static function getCompanyField(): Select
    {
        return static::masterSelect('company_id', __('resources/bankProfile/strings.filters.company'))
            ->options(fn () => static::localizedOptions(Company::active()));
    }

    public static function getBankField(): Select
    {
        return static::masterSelect('bank_id', __('resources/bankProfile/strings.filters.bank'))
            ->options(fn () => static::localizedOptions(Bank::active()));
    }

    public static function getCurrencyField(): Select
    {
        return static::masterSelect('currency_id', __('resources/report/strings.filters.currency'))
            ->options(fn () => static::localizedOptions(Currency::active()));
    }

    public static function getCostCenterField(): Select
    {
        return static::masterSelect('cost_center_id', __('resources/report/strings.filters.cost_center'))
            ->options(fn () => Department::active()->orderBy('id')->get(['id', 'name', 'english_name'])->pluck('localized_name', 'id'));
    }

    public static function getProductField(): Select
    {
        return static::masterSelect('product_id', __('resources/report/strings.filters.product'))
            ->options(fn () => Product::active()
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'english_name', 'attributes'])
                ->pluck('customized_label', 'id'));
    }

    public static function getCategoryField(): Select
    {
        return static::masterSelect('category_id', __('resources/product/strings.filters.category'))
            ->options(fn () => static::categoryPaths());
    }

    public static function getRegisteredOrderField(): Select
    {
        return static::masterSelect('registered_order_id', __('resources/bankProfile/strings.filters.registered_order'))
            ->columnSpan(2)
            ->options(fn () => RegisteredOrder::query()
                ->select(['id', 'ro_number', 'seller_id', 'buyer_id'])
                ->with(['sellerCompany:id,name,english_name', 'buyerCompany:id,name,english_name'])
                ->orderByDesc('id')
                ->get()
                ->pluck('formatted_name_without_date', 'id'));
    }

    public static function getSupplySourceField(): Select
    {
        return Select::make('supply_source')
            ->label(__('resources/bankProfile/strings.form.supply_source'))
            ->options(fn () => __('resources/bankProfile/strings.general.supply_sources'))
            ->native(false)
            ->searchable();
    }

    public static function getStatusField(string $name, string $labelKey, string $englishType, bool $multiple = false): Select
    {
        return static::hinted(Select::make($name)
            ->label(__($labelKey))
            ->options(fn () => static::stageOptions($englishType))
            ->multiple($multiple)
            ->native(false)
            ->searchable()
            ->preload(), Str::afterLast($labelKey, '.'));
    }

    public static function getBankProfileStatusField(): Select
    {
        return static::getStatusField('status_id', 'resources/report/strings.filters.status', BankProfile::TYPE_BANK_PROFILE, true);
    }

    public static function getRegisteredOrderStatusField(): Select
    {
        return static::getStatusField('status_id', 'resources/report/strings.filters.status', RegisteredOrder::TYPE_REGISTERED_ORDER, true);
    }

    public static function getActivityWindowField(): Select
    {
        return static::hinted(Select::make('activity_window')
            ->label(__('resources/report/strings.filters.activity_window'))
            ->options(fn (): array => collect(ReportFilters::ACTIVITY_WINDOWS)->mapWithKeys(fn (string $window): array => [$window => __("resources/report/strings.filters.windows.{$window}")])->all())
            ->placeholder(__('resources/report/strings.filters.windows.'.ReportFilters::DEFAULT_ACTIVITY_WINDOW))
            ->native(false), 'activity_window');
    }

    public static function getCauserField(): Select
    {
        return static::masterSelect('causer_id', __('resources/report/strings.filters.causer'))
            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'));
    }

    public static function getModuleField(): Select
    {
        return static::masterSelect('module', __('resources/report/strings.filters.module'))
            ->options(fn () => collect(AuditChanges::MODELS)->mapWithKeys(fn (string $class): array => [Str::snake(class_basename($class)) => PermissionLabeler::getEntityLabel($class)]));
    }

    public static function getIdleDaysField(): TextInput
    {
        return static::hinted(TextInput::make('idle_days')
            ->label(__('resources/report/strings.filters.idle_days'))
            ->numeric()
            ->minValue(1)
            ->placeholder((string) ReportFilters::IDLE_DAYS), 'idle_days');
    }

    public static function getClearanceTypeField(): Select
    {
        return Select::make('clearance_type')
            ->label(__('resources/custom/strings.filters.clearance_type'))
            ->options(fn () => __('resources/custom/strings.general.clearance_types'))
            ->native(false)
            ->searchable();
    }

    public static function getClearanceStatusField(): Select
    {
        return static::getStatusField('clearance_status_id', 'resources/custom/strings.filters.clearance_status', Custom::TYPE_CLEARANCE_STATUS, true);
    }

    public static function getBankGuaranteeStatusField(): Select
    {
        return static::getStatusField('bank_guarantee_status_id', 'resources/custom/strings.filters.bank_guarantee_status', Custom::TYPE_BANK_GUARANTEE_STATUS);
    }

    public static function getCommitmentStatusField(): Select
    {
        return static::getStatusField('commitment_status_id', 'resources/custom/strings.filters.commitment_status', Custom::TYPE_COMMITMENT_STATUS);
    }

    public static function getClearedFromStageField(): Select
    {
        return static::hinted(static::masterSelect('cleared_from_stage', __('resources/report/strings.filters.cleared_from_stage'))
            ->options(fn (): Collection => static::stageOptions(Custom::TYPE_CLEARANCE_STATUS))
            ->placeholder(fn (): string => (string) app(ReportStages::class)->statusByName(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::Completed->value)?->localized_name), 'cleared_from_stage');
    }

    public static function getArrivalBasisField(): Select
    {
        return static::hinted(Select::make('arrival_basis')
            ->label(__('resources/report/strings.filters.arrival_basis'))
            ->options(fn () => __('resources/report/strings.filters.arrival_basis_options'))
            ->placeholder(__('resources/report/strings.filters.arrival_basis_options.doc_submission_date'))
            ->native(false), 'arrival_basis');
    }

    public static function getStageField(): Select
    {
        return static::hinted(static::masterSelect('stage', __('resources/report/strings.filters.stage'))
            ->options(fn (): array => collect(RequesterStages::STAGES)->mapWithKeys(fn (string $stage): array => [$stage => RequesterStages::label($stage)])->all()), 'stage');
    }

    public static function getShowClosedField(string $labelKey = 'show_closed'): Toggle
    {
        return static::hinted(Toggle::make('show_closed')
            ->label(__('resources/report/strings.filters.'.$labelKey)), $labelKey);
    }

    public static function getRequesterField(): Select
    {
        return static::masterSelect('requester_id', __('resources/purchaseRequest/strings.filters.requester'))
            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'));
    }

    public static function getPurchaseRequestStatusField(): Select
    {
        return static::getStatusField('status_id', 'resources/purchaseRequest/strings.filters.status', PurchaseRequest::TYPE_PURCHASE_REQUEST, true);
    }

    public static function getShowArrivedField(): Toggle
    {
        return static::hinted(Toggle::make('show_arrived')
            ->label(__('resources/report/strings.filters.show_arrived')), 'show_arrived');
    }

    public static function getCarrierField(): Select
    {
        return static::masterSelect('company_id', __('resources/report/strings.filters.carrier'))
            ->options(fn () => static::localizedOptions(Company::active()));
    }

    public static function getStuckDaysField(string $labelKey = 'stuck_days', int $default = CustomChecks::STUCK_DAYS): TextInput
    {
        return static::hinted(TextInput::make('stuck_days')
            ->label(__('resources/report/strings.filters.'.$labelKey))
            ->numeric()
            ->minValue(1)
            ->maxValue(365)
            ->placeholder((string) $default), $labelKey);
    }

    public static function getThresholdField(string $key, ?string $labelKey = null): TextInput
    {
        $default = $key === 'idle' ? ReportFilters::IDLE_DAYS : ReportFilters::THRESHOLD_DEFAULTS[$key];
        [$min, $max] = ReportFilters::thresholdBounds($key);

        $labelKey ??= "th_{$key}";

        return static::hinted(TextInput::make(match ($key) {
            'stuck' => 'stuck_days',
            'idle' => 'idle_days',
            default => "th_{$key}",
        })
            ->label(__('resources/report/strings.filters.'.$labelKey))
            ->numeric()
            ->minValue($min)
            ->maxValue($max)
            ->placeholder((string) $default), $labelKey);
    }

    /**
     * @param  array<string, string>  $optionKeys  basis key => lang key of the existing field label
     */
    public static function getDateBasisField(array $optionKeys): Select
    {
        return static::hinted(Select::make('date_basis')
            ->label(__('resources/report/strings.filters.date_basis'))
            ->options(fn (): array => array_map(fn (string $langKey): string => __($langKey), $optionKeys))
            ->placeholder(fn (): string => __(reset($optionKeys)))
            ->native(false), 'date_basis');
    }

    public static function getDepartureBasisField(): Select
    {
        return static::hinted(Select::make('departure_basis')
            ->label(__('resources/report/strings.filters.departure_basis'))
            ->options(fn (): array => collect(ReportFilters::DEPARTURE_BASES)->mapWithKeys(fn (string $basis): array => [$basis => __("resources/report/strings.filters.departure_basis_options.{$basis}")])->all())
            ->placeholder(fn (): string => __('resources/report/strings.filters.departure_basis_options.'.ReportFilters::DEPARTURE_BASES[0]))
            ->native(false), 'departure_basis');
    }

    public static function getIncludeUndatedField(): Toggle
    {
        return static::hinted(Toggle::make('include_undated')
            ->label(__('resources/report/strings.filters.include_undated'))
            ->default(true), 'include_undated');
    }

    public static function getIncludeVoidField(): Toggle
    {
        return static::hinted(Toggle::make('include_void')
            ->label(__('resources/report/strings.filters.include_void')), 'include_void');
    }

    public static function getIncludeFlaggedInvoicesField(): Toggle
    {
        return static::hinted(Toggle::make('include_flagged_invoices')
            ->label(__('resources/report/strings.filters.include_flagged_invoices')), 'include_flagged_invoices');
    }

    public static function getIncludeOutliersField(): Toggle
    {
        return static::hinted(Toggle::make('include_outliers')
            ->label(__('resources/report/strings.filters.include_outliers')), 'include_outliers');
    }

    public static function getFallbackOrderAmountField(): Toggle
    {
        return static::hinted(Toggle::make('fallback_order_amount')
            ->label(__('resources/report/strings.filters.fallback_order_amount')), 'fallback_order_amount');
    }

    public static function getWeightBasisField(): Select
    {
        return static::choiceField('weight_basis', ReportFilters::WEIGHT_BASES);
    }

    public static function getValueBasisField(): Select
    {
        return static::choiceField('value_basis', ReportFilters::VALUE_BASES);
    }

    public static function getAmountInField(): Select
    {
        return static::choiceField('amount_in', ReportFilters::AMOUNT_MODES);
    }

    public static function getRateBasisField(): Select
    {
        return static::choiceField('rate_basis', ReportFilters::RATE_BASES);
    }

    public static function getCommitmentStateField(): Select
    {
        return static::hinted(Select::make('commitment_state')
            ->label(__('resources/report/strings.filters.commitment_state'))
            ->options(fn (): array => static::choiceOptions('commitment_state', ReportFilters::COMMITMENT_STATES))
            ->native(false), 'commitment_state');
    }

    public static function getPurchasedCurrencyField(): Select
    {
        return static::masterSelect('purchased_currency_id', __('resources/bankProfile/strings.form.purchased_currency'))
            ->options(fn () => static::localizedOptions(Currency::active()));
    }

    public static function getRequestedCurrencyField(): Select
    {
        return static::masterSelect('currency_id', __('resources/bankProfile/strings.form.requested_currency'))
            ->options(fn () => static::localizedOptions(Currency::active()));
    }

    public static function getOperationsStageField(): Select
    {
        return static::hinted(Select::make('stage')
            ->label(__('resources/report/strings.filters.stage'))
            ->options(fn (): array => collect(OperationsReportService::STAGES)->map(fn (string $label): string => __($label))->all())
            ->native(false), 'stage');
    }

    /**
     * @param  array<int, string>  $choices
     */
    /**
     * @param  array<int, mixed>  $fields
     * @return array<int, mixed>
     */
    protected static function arrangeFilters(array $fields): array
    {
        $groups = ['plain' => [], 'wide' => [], 'toggles' => [], 'sections' => []];

        foreach ($fields as $field) {
            $groups[match (true) {
                $field instanceof Section => 'sections',
                $field instanceof Toggle => 'toggles',
                $field instanceof Select && static::isWideSelect($field) => 'wide',
                default => 'plain',
            }][] = $field;
        }

        return [
            ...$groups['plain'],
            ...array_map(fn (Select $field): Select => $field->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 2]), $groups['wide']),
            ...($groups['toggles'] === [] ? [] : [Grid::make(['default' => 1, 'sm' => 2, 'lg' => min(count($groups['toggles']), 4)])->columnSpanFull()->schema($groups['toggles'])]),
            ...$groups['sections'],
        ];
    }

    protected static function isWideSelect(Select $field): bool
    {
        return $field->isMultiple() || in_array($field->getName(), self::WIDE_SELECTS, true);
    }

    protected static function choiceField(string $name, array $choices): Select
    {
        return static::hinted(Select::make($name)
            ->label(__("resources/report/strings.filters.{$name}"))
            ->options(fn (): array => static::choiceOptions($name, $choices))
            ->placeholder(fn (): string => __("resources/report/strings.filters.{$name}_options.{$choices[0]}"))
            ->native(false), $name);
    }

    /**
     * @template TField of Field
     *
     * @param  TField  $field
     * @return TField
     */
    protected static function hinted(Field $field, string $key)
    {
        $path = "resources/report/strings.filters.hints.{$key}";

        if (! Lang::has($path)) {
            return $field;
        }

        $field->hintIcon(Heroicon::OutlinedInformationCircle, fn (): string => __($path));

        return $field instanceof Select || $field instanceof TextInput
            ? $field->extraInputAttributes(fn (): array => ['x-tooltip.raw' => __($path)], merge: true)
            : $field;
    }

    /**
     * @param  array<int, string>  $choices
     * @return array<string, string>
     */
    protected static function choiceOptions(string $name, array $choices): array
    {
        return collect($choices)->mapWithKeys(fn (string $choice): array => [$choice => __("resources/report/strings.filters.{$name}_options.{$choice}")])->all();
    }

    protected static function masterSelect(string $name, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->native(false)
            ->searchable()
            ->preload();
    }

    protected static function stageOptions(string $englishType): Collection
    {
        $ordered = app(ReportStages::class)->ordered($englishType);

        return $ordered
            ->concat(Status::where('english_type', $englishType)->whereNotIn('id', $ordered->pluck('id')->all())->orderBy('id')->get())
            ->mapWithKeys(fn (Status $status) => [$status->id => $status->localized_name]);
    }

    protected static function localizedOptions($query): Collection
    {
        $column = app()->getLocale() === 'fa' ? 'name' : 'english_name';

        return $query->orderBy($column)->pluck($column, 'id');
    }

    protected static function categoryPaths(): Collection
    {
        $categories = Category::query()->get(['id', 'parent_id', 'name', 'english_name', 'active'])->keyBy('id');

        return $categories
            ->filter(fn (Category $category) => $category->active)
            ->map(fn (Category $category) => static::categoryPath($category, $categories))
            ->sort();
    }

    protected static function categoryPath(Category $category, Collection $categories): string
    {
        $names = [$category->localized_name];

        for ($node = $category; $node->parent_id && $categories->has($node->parent_id) && count($names) < 6;) {
            $node = $categories->get($node->parent_id);
            array_unshift($names, $node->localized_name);
        }

        return implode(' » ', $names);
    }
}
