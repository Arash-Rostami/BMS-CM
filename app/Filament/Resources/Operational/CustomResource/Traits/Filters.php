<?php

namespace App\Filament\Resources\Operational\CustomResource\Traits;

use App\Filament\Resources\General\FilterComponents;
use App\Models\Custom;
use App\Services\SmartCacheManager;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Model;

trait Filters
{
    public static function getRegisteredOrderFilter(): SelectFilter
    {
        return SelectFilter::make('registered_order_id')
            ->label(__('resources/custom/strings.filters.registered_order'))
            ->relationship('registeredOrder', 'ro_number')
            ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->formatted_name_without_date)
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
                fn ($query) => $query->where('english_type', Custom::TYPE_CLEARANCE_STATUS)
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
                fn ($query) => $query->where('english_type', Custom::TYPE_COMMITMENT_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getClearanceTypeFilter(): SelectFilter
    {
        return SelectFilter::make('clearance_type')
            ->label(__('resources/custom/strings.filters.clearance_type'))
            ->options(__('resources/custom/strings.general.clearance_types'));
    }

    public static function getContractNoFilter(): SelectFilter
    {
        return SelectFilter::make('contract_no')
            ->label(__('resources/custom/strings.filters.contract_no'))
            ->options(fn () => SmartCacheManager::remember(
                'Custom',
                ['filter' => 'contract_no'],
                300,
                fn () => Custom::query()
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
                fn ($query) => $query->where('english_type', Custom::TYPE_BANK_GUARANTEE_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getCreationDateFilter(): Filter
    {
        return FilterComponents::dateRangeFilter(
            'created_at',
            'created_at',
            'created_from',
            'created_until',
            __('resources/custom/strings.filters.created_from'),
            __('resources/custom/strings.filters.created_until'),
        );
    }

    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }
}
