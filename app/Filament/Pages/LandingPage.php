<?php

namespace App\Filament\Pages;

use App\Services\DashboardStats;
use Filament\Pages\Page;

class LandingPage extends Page
{
    protected static ?string $title = '  ';

    protected static string $layout = 'layout';

    protected static bool $shouldRegisterNavigation = false;

    public array $counts = [];

    protected string $view = 'filament.landing-page';

    public function mount(): void
    {
        $locale = request()->query('locale');
        if ($locale && in_array($locale, ['en', 'fa', 'fr'])) {
            session()->put('locale', $locale);
            app()->setLocale($locale);
        }

        $this->counts = DashboardStats::get();
    }

    protected function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'counts' => $this->counts,
            'welcome' => __('resources/general/strings.welcome'),
        ]);
    }
}
