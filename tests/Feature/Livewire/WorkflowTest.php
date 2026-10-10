<?php

namespace Tests\Feature\Livewire;

use App\Livewire\LandingPage\Workflow;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetInsightCache();
    }

    protected function tearDown(): void
    {
        $this->forgetInsightCache();
        parent::tearDown();
    }

    private function forgetInsightCache(): void
    {
        foreach (['en', 'fa', 'fr'] as $locale) {
            Cache::forget('desk_reference_insight_groups:'.$locale);
        }
    }

    public function test_stats_normalize_snake_case_counts_and_carry_the_rtl_flag(): void
    {
        $component = Livewire::test(Workflow::class, [
            'counts' => ['registered_orders' => '7', 'customs' => 3],
            'isRtl' => true,
        ]);

        $stats = $component->get('stats');

        $this->assertSame(7, $stats['registeredOrders']);
        $this->assertSame(3, $stats['customs']);
        $this->assertSame(0, $stats['payments']);
        $this->assertSame(array_keys(config('workspace.resources')), array_keys($stats));
        $this->assertTrue($component->get('isRtl'));
    }

    public function test_insight_groups_build_with_a_complete_group_contract_in_every_locale(): void
    {
        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);

            Livewire::test(Workflow::class, ['counts' => [], 'isRtl' => false])->assertSuccessful();

            $groups = Cache::get('desk_reference_insight_groups:'.$locale);
            $this->assertNotEmpty($groups);
            $this->assertEqualsCanonicalizing(
                ['request_approval', 'order_processing', 'procurement_payment', 'logistics'],
                array_column($groups, 'key')
            );

            foreach ($groups as $group) {
                $this->assertContains($group['key'], ['request_approval', 'order_processing', 'procurement_payment', 'logistics']);
                $this->assertNotSame('', $group['title']);
                $this->assertContains($group['accent'], ['blue', 'green', 'yellow', 'red']);
                $this->assertNotEmpty($group['tips']);
                $this->assertNotSame('', $group['route']);

                foreach (['poster', 'audio', 'video'] as $media) {
                    if ($group[$media] !== null) {
                        $this->assertStringContainsString('desk-reference/', $group[$media]);
                    }
                }
            }
        }
    }

    public function test_render_serves_insight_groups_from_the_locale_scoped_cache(): void
    {
        Cache::put('desk_reference_insight_groups:'.app()->getLocale(), [[
            'key' => 'request_approval',
            'title' => 'Sentinel group',
            'scopeLabel' => null,
            'accent' => 'blue',
            'tips' => [],
            'terms' => [],
            'process' => [],
            'dos' => [],
            'donts' => [],
            'poster' => 'sentinel.jpg',
            'audio' => null,
            'video' => null,
            'route' => '/sentinel-route',
        ]]);

        Livewire::test(Workflow::class, ['counts' => [], 'isRtl' => false])
            ->assertSeeHtml('sentinel.jpg');
    }
}
