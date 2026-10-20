<?php

namespace Tests\Feature\Providers;

use Filament\Facades\Filament;
use Tests\TestCase;

class DashboardPanelProviderTest extends TestCase
{
    private const ORDER = [
        'operational_first',
        'operational_second',
        'operational_third',
        'operational_fourth',
        'alerts',
        'reports',
        'base',
    ];

    private function labels(): array
    {
        return array_map(
            fn ($group) => $group->getLabel(),
            array_values(Filament::getPanel('dashboard')->getNavigationGroups())
        );
    }

    public function test_navigation_groups_place_reports_directly_above_master_data(): void
    {
        app()->setLocale('en');

        $expected = array_map(fn ($key) => __("resources/dashboard/strings.navigation_group.$key"), self::ORDER);

        $this->assertSame($expected, $this->labels());
    }

    public function test_reports_group_label_carries_its_own_percent_marker_in_every_locale(): void
    {
        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);

            $this->assertStringStartsWith('【%】', $this->labels()[5]);
            $this->assertStringStartsWith('【#】', $this->labels()[6]);
        }
    }
}
