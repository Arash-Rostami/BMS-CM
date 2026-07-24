<?php

namespace App\Services;

use Carbon\Carbon;

class GreetingService
{
    public function getGreeting(string $name, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        $now = Carbon::now();
        $key = $this->getTimeOfDay($now).'_'.$this->getDayOfWeek($now);

        $messages = $this->messagesFor($key, $locale);

        if (empty($messages)) {
            return $name;
        }

        return str_replace('{name}', $name, $messages[array_rand($messages)]);
    }

    private function messagesFor(string $key, string $locale): array
    {
        foreach (array_unique([$locale, config('app.fallback_locale'), 'en']) as $loc) {
            $messages = trans("resources/general/strings.greetings.{$key}", [], $loc);

            if (is_array($messages) && ! empty($messages)) {
                return $messages;
            }
        }

        return [];
    }

    private function getTimeOfDay(Carbon $now): string
    {
        return match (true) {
            $now->hour >= 4 && $now->hour < 12 => 'morning',
            $now->hour >= 12 && $now->hour < 17 => 'afternoon',
            $now->hour >= 17 && $now->hour < 21 => 'evening',
            default => 'night',
        };
    }

    private function getDayOfWeek(Carbon $now): string
    {
        $now = $now->copy();

        if ($now->hour < 4) {
            $now->subDay();
        }

        return match ($now->dayOfWeek) {
            Carbon::SATURDAY => 'saturday',
            Carbon::SUNDAY => 'sunday',
            Carbon::MONDAY => 'monday',
            Carbon::TUESDAY => 'tuesday',
            Carbon::WEDNESDAY => 'wednesday',
            Carbon::THURSDAY => 'thursday',
            default => 'friday',
        };
    }
}
