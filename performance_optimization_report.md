# Performance Optimization Report: Eager Loading

While scanning the repository, it's evident that standard queries in `getEloquentQuery()` for most main resource tables are already eager loading relationships effectively (e.g. `PurchaseRequestResource` or `ProductResource`). However, a major hidden performance leak lies within Filament's **Global Search** capabilities.

When accessing relationship fields inside `getGlobalSearchResultDetails()` (which renders the little subtitles in global search results), many resources are failing to eager load those relations in `getGlobalSearchEloquentQuery()`. This causes an N+1 query issue, firing a new database query for every single matched global search result.

Here is the list of crucial locations that need a minimal change to yield significant performance boosts during global searches:

### 1. `app/Filament/Resources/CustomResource.php`
- **Issue:** `getGlobalSearchResultDetails()` accesses `$record->shipment->shipment_no` and `$record->clearanceStatus->localized_name`.
- **Solution:** Add `getGlobalSearchEloquentQuery()` override to eager load these relations:
  ```php
  public static function getGlobalSearchEloquentQuery(): Builder
  {
      return parent::getGlobalSearchEloquentQuery()->with(['shipment', 'clearanceStatus']);
  }
  ```

### 2. `app/Filament/Resources/PaymentResource.php`
- **Issue:** `getGlobalSearchResultDetails()` accesses `$record->payor`, `$record->payee`, and `$record->payment_date`.
- **Solution:** Add `getGlobalSearchEloquentQuery()` override:
  ```php
  public static function getGlobalSearchEloquentQuery(): Builder
  {
      return parent::getGlobalSearchEloquentQuery()->with(['payor', 'payee']);
  }
  ```

### 3. `app/Filament/Resources/BankProfileResource.php`
- **Issue:** `getGlobalSearchResultDetails()` accesses `$record->bank`, `$record->company`, and `$record->status`.
- **Solution:** Add `getGlobalSearchEloquentQuery()` override:
  ```php
  public static function getGlobalSearchEloquentQuery(): Builder
  {
      return parent::getGlobalSearchEloquentQuery()->with(['bank', 'company', 'status']);
  }
  ```

### 4. `app/Filament/Resources/PurchaseOrderResource.php`
- **Issue:** `getGlobalSearchResultDetails()` accesses `$record->sellerCompanyExclusive` and `$record->status`.
- **Current state:** `getGlobalSearchEloquentQuery()` already eager loads `['sellerCompanyExclusive']` but misses `status`.
- **Solution:** Update existing `getGlobalSearchEloquentQuery()`:
  ```php
  public static function getGlobalSearchEloquentQuery(): Builder
  {
      return parent::getGlobalSearchEloquentQuery()->with(['sellerCompanyExclusive', 'status']);
  }
  ```

### 5. `app/Filament/Resources/ProformaInvoiceResource.php`
- **Issue:** `getGlobalSearchResultDetails()` accesses `$record->sellerCompany` and `$record->buyerCompany`.
- **Current state:** `getGlobalSearchEloquentQuery()` eager loads `['sellerCompany']` but misses `buyerCompany`.
- **Solution:** Update existing `getGlobalSearchEloquentQuery()`:
  ```php
  public static function getGlobalSearchEloquentQuery(): Builder
  {
      return parent::getGlobalSearchEloquentQuery()->with(['sellerCompany', 'buyerCompany']);
  }
  ```

### 6. `app/Filament/Resources/ShipmentResource.php`
- **Issue:** `getGlobalSearchResultDetails()` accesses `$record->carrier` and `$record->status`.
- **Solution:** Add `getGlobalSearchEloquentQuery()` override:
  ```php
  public static function getGlobalSearchEloquentQuery(): Builder
  {
      return parent::getGlobalSearchEloquentQuery()->with(['carrier', 'status']);
  }
  ```

### 7. `app/Filament/Resources/RegisteredOrderResource.php`
- **Issue:** `getGlobalSearchResultDetails()` accesses `$record->sellerCompanyExclusive` and `$record->status`.
- **Current state:** `getGlobalSearchEloquentQuery()` eager loads `['sellerCompanyExclusive']` but misses `status`.
- **Solution:** Update existing `getGlobalSearchEloquentQuery()`:
  ```php
  public static function getGlobalSearchEloquentQuery(): Builder
  {
      return parent::getGlobalSearchEloquentQuery()->with(['sellerCompanyExclusive', 'status']);
  }
  ```

### Summary of Boost
Because Global Search debounces keystrokes and queries multiple models concurrently, fixing these missing `->with()` clauses will drastically reduce database load from N+1 query proliferation on every search keystroke in the application header.
