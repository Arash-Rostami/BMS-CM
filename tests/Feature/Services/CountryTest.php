<?php

namespace Tests\Feature\Services;

use App\Services\Country;
use Tests\TestCase;

class CountryTest extends TestCase
{
    private string $locale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locale = app()->getLocale();
    }

    protected function tearDown(): void
    {
        app()->setLocale($this->locale);
        parent::tearDown();
    }

    public function test_countries_list_uses_english_names_outside_the_fa_locale(): void
    {
        app()->setLocale('en');

        $this->assertSame('Afghanistan', (new Country)->getCountriesList()['AF']);
    }

    public function test_countries_list_falls_back_to_english_names_for_the_fr_locale(): void
    {
        app()->setLocale('fr');

        $this->assertSame('Afghanistan', (new Country)->getCountriesList()['AF']);
    }

    public function test_countries_list_uses_farsi_names_in_the_fa_locale(): void
    {
        app()->setLocale('fa');

        $this->assertSame('ایران', (new Country)->getCountriesList()['IR']);
    }

    public function test_country_name_lookup_is_case_insensitive(): void
    {
        app()->setLocale('en');

        $this->assertSame('Iran (Islamic Republic of)', (new Country)->getCountryNameByCode('ir'));
    }

    public function test_country_name_lookup_returns_null_for_an_unknown_code(): void
    {
        app()->setLocale('en');

        $this->assertNull((new Country)->getCountryNameByCode('ZZ'));
    }

    public function test_countries_list_covers_every_country_and_is_sorted_by_name(): void
    {
        app()->setLocale('en');

        $list = (new Country)->getCountriesList();

        $this->assertSame(count((new Country)->all()), count($list));

        $values = array_values($list);
        $sorted = $values;
        sort($sorted);
        $this->assertSame($sorted, $values);
    }
}