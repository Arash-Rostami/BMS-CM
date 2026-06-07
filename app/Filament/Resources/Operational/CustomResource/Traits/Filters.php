<?php

namespace App\Filament\Resources\Operational\CustomResource\Traits;

use App\Models\Custom;
use App\Services\SmartCacheManager;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait Filters
{
    public static function getRegisteredOrderFilter(): SelectFilter
    {
        return SelectFilter::make('registered_order_id')
            ->label(__('resources/custom/strings.filters.registered_order'))
            ->relationship('registeredOrder', 'ro_number')
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->formatted_name_without_date)
            ->searchable()
            ->preload();
    }

    public static function getClearanceStatusFilter(): SelectFilter
    {
        return SelectFilter::make('clearance_status_id')
            ->label(__('resources/custom/strings.filters.clearance_status'))
            ->relationship(
                'clearanceStatus',
                app()->getLocale() === 'fa' ? 'name' : 'english_name',
                fn($query) => $query->where('english_type', Custom::TYPE_CLEARANCE_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getCommitmentStatusFilter(): SelectFilter
    {
        return SelectFilter::make('commitment_status_id')
            ->label(__('resources/custom/strings.filters.commitment_status'))
            ->relationship(
                'commitmentStatus',
                app()->getLocale() === 'fa' ? 'name' : 'english_name',
                fn($query) => $query->where('english_type', Custom::TYPE_COMMITMENT_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getClearanceTypeFilter(): SelectFilter
    {
        return SelectFilter::make('clearance_type')
            ->label(__('resources/custom/strings.form.clearance_type'))
            ->options([ '90_percent' => '90%', '10_percent' => '10%']);
    }

    public static function getContractNoFilter(): SelectFilter
    {
        return SelectFilter::make('contract_no')
            ->label(__('resources/custom/strings.filters.contract_no'))
            ->options(fn() => SmartCacheManager::remember(
                'Custom',
                ['filter' => 'contract_no'],
                300,
                fn() => Custom::query()
                    ->whereNotNull('contract_no')
                    ->distinct()
                    ->pluck('contract_no', 'contract_no')
                    ->toArray()
            ))
            ->searchable();
    }

    public static function getBankGuaranteeStatusFilter(): SelectFilter
    {
        return SelectFilter::make('bank_guarantee_status_id')
            ->label(__('resources/custom/strings.filters.bank_guarantee_status'))
            ->relationship(
                'bankGuaranteeStatus',
                app()->getLocale() === 'fa' ? 'name' : 'english_name',
                fn($query) => $query->where('english_type', Custom::TYPE_BANK_GUARANTEE_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getCreationDateFilter(): Filter
    {
        return Filter::make('created_at')
            ->schema([
                DatePicker::make('created_from')
                    ->label(__('resources/custom/strings.filters.created_from'))
                    ->native(false)
                    ->adaptive(),
                DatePicker::make('created_until')
                    ->label(__('resources/custom/strings.filters.created_until'))
                    ->native(false)
                    ->adaptive(),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when($data['created_from'], fn(Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                    ->when($data['created_until'], fn(Builder $query, $date) => $query->whereDate('created_at', '<=', $date));
            });
    }

    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }
}
