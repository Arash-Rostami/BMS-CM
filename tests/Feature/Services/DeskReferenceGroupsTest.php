<?php

namespace Tests\Feature\Services;

use App\Services\DeskReferenceGroups;
use Tests\TestCase;

class DeskReferenceGroupsTest extends TestCase
{
    private string $locale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locale = app()->getLocale();
        app()->setLocale('en');
    }

    protected function tearDown(): void
    {
        app()->setLocale($this->locale);
        parent::tearDown();
    }

    public function test_all_returns_exactly_the_four_fixed_groups(): void
    {
        $this->assertSame(
            ['request_approval', 'order_processing', 'procurement_payment', 'logistics'],
            array_keys(DeskReferenceGroups::all())
        );
    }

    public function test_every_group_carries_non_empty_content(): void
    {
        foreach (DeskReferenceGroups::all() as $group => $content) {
            $sections = array_map(fn ($section) => $content[$section] ?? [], ['terms', 'process', 'dos', 'donts', 'tips']);
            $filled = array_filter($sections, fn ($section) => ! empty($section));

            $this->assertNotEmpty($filled, "Group [{$group}] is entirely empty.");
        }
    }

    public function test_group_content_switches_with_locale(): void
    {
        $english = DeskReferenceGroups::all()['request_approval'];

        app()->setLocale('fa');

        $farsi = DeskReferenceGroups::all()['request_approval'];

        $this->assertNotSame($english, $farsi);
    }
}