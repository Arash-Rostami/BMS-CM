<?php

namespace Tests\Feature\Services;

use App\Services\GreetingService;
use Carbon\Carbon;
use Tests\TestCase;

class GreetingServiceTest extends TestCase
{
    private string $locale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locale = app()->getLocale();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app()->setLocale($this->locale);
        parent::tearDown();
    }

    private function pin(string $day, int $hour): void
    {
        Carbon::setTestNow(now()->modify("next {$day}")->setTime($hour, 0));
    }

    private function expected(string $key, string $name): array
    {
        return array_map(
            fn (string $template) => str_replace('{name}', $name, $template),
            (array) trans("resources/general/strings.greetings.{$key}", [], 'en')
        );
    }

    public function test_greeting_picks_the_template_matching_the_current_time_bucket(): void
    {
        $this->pin('monday', 10);

        $greeting = (new GreetingService)->getGreeting('Arash', 'en');

        $this->assertContains($greeting, $this->expected('morning_monday', 'Arash'));
    }

    public function test_greeting_picks_the_afternoon_bucket(): void
    {
        $this->pin('monday', 14);

        $greeting = (new GreetingService)->getGreeting('Arash', 'en');

        $this->assertContains($greeting, $this->expected('afternoon_monday', 'Arash'));
    }

    public function test_hours_before_4am_still_count_as_the_previous_day(): void
    {
        $this->pin('monday', 2);

        $greeting = (new GreetingService)->getGreeting('Arash', 'en');

        $this->assertContains($greeting, $this->expected('night_sunday', 'Arash'));
    }

    public function test_greeting_falls_back_to_english_for_an_untranslated_locale(): void
    {
        $this->pin('monday', 10);

        $greeting = (new GreetingService)->getGreeting('Arash', 'zz');

        $this->assertContains($greeting, $this->expected('morning_monday', 'Arash'));
    }

    public function test_greeting_interpolates_the_display_name(): void
    {
        $this->pin('monday', 10);

        $greeting = (new GreetingService)->getGreeting('Arash', 'en');

        $this->assertStringContainsString('Arash', $greeting);
    }
}