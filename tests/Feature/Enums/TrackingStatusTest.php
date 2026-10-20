<?php

namespace Tests\Feature\Enums;

use App\Filament\Resources\Operational\ShipmentResource\Enums\TrackingStatus;
use Tests\TestCase;

class TrackingStatusTest extends TestCase
{
    public function test_the_cases_carry_the_production_names_in_stage_order(): void
    {
        $this->assertSame(
            ['Departed From Origin', 'In Transit', 'Arrived At Destination', 'Customs Clearance', 'Out for Delivery', 'Delivered'],
            array_map(fn (TrackingStatus $case) => $case->value, TrackingStatus::cases())
        );
    }

    public function test_the_two_report_meanings_resolve_from_their_status_names(): void
    {
        $this->assertSame(TrackingStatus::InTransit, TrackingStatus::tryFrom('In Transit'));
        $this->assertSame(TrackingStatus::ArrivedAtDestination, TrackingStatus::tryFrom('Arrived At Destination'));
        $this->assertNull(TrackingStatus::tryFrom('in transit'));
    }

    public function test_every_case_has_a_color_an_icon_and_a_translated_label_in_every_locale(): void
    {
        $labels = [];

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);

            foreach (TrackingStatus::cases() as $case) {
                $label = $case->getLabel();

                $this->assertNotEmpty($case->getColor());
                $this->assertStringStartsWith('heroicon-', $case->getIcon());
                $this->assertStringNotContainsString('resources/shipment', $label, "{$locale} {$case->name}");
                $labels[$locale][$case->name] = $label;
            }
        }

        app()->setLocale('en');

        foreach (TrackingStatus::cases() as $case) {
            $this->assertSame($case->value, $labels['en'][$case->name]);
            $this->assertNotSame($labels['en'][$case->name], $labels['fa'][$case->name]);
            $this->assertNotSame($labels['en'][$case->name], $labels['fr'][$case->name]);
        }
    }
}
