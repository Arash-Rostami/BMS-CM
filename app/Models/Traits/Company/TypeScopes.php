<?php

namespace App\Models\Traits\Company;

trait TypeScopes
{
    const TYPE_SELLER = 'seller';
    const TYPE_BUYER = 'buyer';
    const TYPE_SUPPLIER = 'supplier';
    const TYPE_DISTRIBUTOR = 'distributor';
    const TYPE_MANUFACTURER = 'manufacturer';
    const TYPE_RETAILER = 'retailer';
    const TYPE_WHOLESALER = 'wholesaler';
    const TYPE_SERVICE_PROVIDER = 'service_provider';

    const TYPE_SERVICE_ALL_SELLERS = ['supplier', 'manufacturer', 'seller'];

    public static function getAvailableTypes(): array
    {
        return app()->getLocale() == 'fa'
            ? [
                self::TYPE_SELLER => 'فروشنده',
                self::TYPE_BUYER => 'خریدار',
                self::TYPE_SUPPLIER => 'تامین‌کننده',
                self::TYPE_DISTRIBUTOR => 'توزیع‌کننده',
                self::TYPE_MANUFACTURER => 'تولیدکننده',
                self::TYPE_RETAILER => 'خرده‌فروش',
                self::TYPE_WHOLESALER => 'عمده‌فروش',
                self::TYPE_SERVICE_PROVIDER => 'ارائه‌دهنده خدمات'
            ]
            : [
                self::TYPE_SELLER => 'Seller',
                self::TYPE_BUYER => 'Buyer',
                self::TYPE_SUPPLIER => 'Supplier',
                self::TYPE_DISTRIBUTOR => 'Distributor',
                self::TYPE_MANUFACTURER => 'Manufacturer',
                self::TYPE_RETAILER => 'Retailer',
                self::TYPE_WHOLESALER => 'Wholesaler',
                self::TYPE_SERVICE_PROVIDER => 'Service Provider',
            ];
    }

    public function getFormattedTypesAttribute(): array
    {
        if (!$this->types) return [];

        $availableTypes = self::getAvailableTypes();
        return array_map(function ($type) use ($availableTypes) {
            return $availableTypes[$type] ?? ucfirst(str_replace('_', ' ', $type));
        }, $this->types);
    }

    /**
     * Scope to get buyers
     */
    public function scopeBuyers($query)
    {
        return $query->ofType(self::TYPE_BUYER);
    }

    /**
     * Scope to get distributors
     */
    public function scopeDistributors($query)
    {
        return $query->ofType(self::TYPE_DISTRIBUTOR);
    }

    public function scopeHasAnyType($query, string ...$types)
    {
        if (empty($types)) {
            return $query;
        }

        return $query->ofAnyType($types);
    }

    /**
     * Scope to get manufacturers
     */
    public function scopeManufacturers($query)
    {
        return $query->ofType(self::TYPE_MANUFACTURER);
    }

    /**
     * Scope to filter companies by all of the given types
     */
    public function scopeOfAllTypes($query, array $types)
    {
        foreach ($types as $type) {
            $query->whereJsonContains('types', $type);
        }
        return $query;
    }

    /**
     * Scope to filter companies by any of the given types
     */
    public function scopeOfAnyType($query, array $types)
    {
        return $query->where(function ($q) use ($types) {
            foreach ($types as $type) {
                $q->orWhereJsonContains('types', $type);
            }
        });
    }

    public function scopeOfType($query, $type)
    {
        return $query->whereJsonContains('types', $type);
    }

    /**
     * Scope to get retailers
     */
    public function scopeRetailers($query)
    {
        return $query->ofType(self::TYPE_RETAILER);
    }

    /**
     * Scope to get sellers
     */
    public function scopeSellers($query)
    {
        return $query->ofType(self::TYPE_SELLER);
    }

    /**
     * Scope to get service providers
     */
    public function scopeServiceProviders($query)
    {
        return $query->ofType(self::TYPE_SERVICE_PROVIDER);
    }

    /**
     * Scope to get suppliers
     */
    public function scopeSuppliers($query)
    {
        return $query->ofType(self::TYPE_SUPPLIER);
    }

    /**
     * Scope to get supply chain companies
     */
    public function scopeSupplyChainCompanies($query)
    {
        return $query->ofAnyType([
            self::TYPE_SUPPLIER,
            self::TYPE_DISTRIBUTOR,
            self::TYPE_MANUFACTURER,
            self::TYPE_WHOLESALER
        ]);
    }

    /**
     * Scope to get trading companies (sellers, buyers, or both)
     */
    public function scopeTradingCompanies($query)
    {
        return $query->ofAnyType([self::TYPE_SELLER, self::TYPE_BUYER]);
    }

    /**
     * Scope to get wholesalers
     */
    public function scopeWholesalers($query)
    {
        return $query->ofType(self::TYPE_WHOLESALER);
    }

}
