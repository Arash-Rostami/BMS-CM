<?php

namespace Tests\Feature\Livewire;

use App\Livewire\LandingPage\Features;
use Livewire\Livewire;
use Tests\TestCase;

class FeaturesTest extends TestCase
{
    public function test_both_sections_lay_out_four_columns_with_eight_distinguishing_cards(): void
    {
        $html = Livewire::test(Features::class, ['isRtl' => false])->html();

        $this->assertCount(8, __('dashboard/strings.app_features.distinguishing.groups'));
        $this->assertSame(2, substr_count($html, 'lg:grid-cols-4'), 'Both section grids must be 4-column (distinguishing = 2 rows x 4 cards).');
        $this->assertSame(12, substr_count($html, 'lp-surface p-4 flex flex-col'), 'Expected 8 distinguishing + 4 standard group cards.');
    }
}