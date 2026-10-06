<?php

namespace Tests\Feature\Traits;

use App\Models\Bank;
use App\Models\Company;
use App\Models\Status;
use App\Models\Traits\General\Localization;
use Tests\TestCase;

/**
 * App\Models\Traits\General\Localization provides getLocalizedNameAttribute() (fa ->
 * `name`, else -> `english_name`, empty-string fallback never null) and the protected
 * localeColumn() it's built on. Composed on 7 unrelated models (Bank, Department,
 * Currency, Company, Product, Category, Status) — cross-cutting, no existing coverage.
 * Pure accessor logic on unsaved instances; no DB round trip needed.
 */
class LocalizationTest extends TestCase
{
    protected function tearDown(): void
    {
        app()->setLocale('en');
        parent::tearDown();
    }

    public function test_bank_composes_the_trait(): void
    {
        $this->assertContains(Localization::class, class_uses_recursive(Bank::class));
    }

    public function test_localized_name_returns_the_persian_name_when_locale_is_fa(): void
    {
        app()->setLocale('fa');
        $bank = new Bank(['name' => 'بانک ملی', 'english_name' => 'National Bank']);

        $this->assertSame('بانک ملی', $bank->localized_name);
    }

    public function test_localized_name_returns_the_english_name_for_any_non_fa_locale(): void
    {
        $company = new Company(['name' => 'شرکت نمونه', 'english_name' => 'Sample Co']);

        app()->setLocale('en');
        $this->assertSame('Sample Co', $company->localized_name);

        app()->setLocale('fr');
        $this->assertSame('Sample Co', $company->localized_name);
    }

    public function test_localized_name_falls_back_to_an_empty_string_never_null(): void
    {
        app()->setLocale('fa');
        $status = new Status(['english_name' => 'Pending']);

        $this->assertSame('', $status->localized_name);
        $this->assertNotNull($status->localized_name);
    }
}
