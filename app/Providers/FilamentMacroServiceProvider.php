<?php

namespace App\Providers;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Field;
use Illuminate\Support\ServiceProvider;

class FilamentMacroServiceProvider extends ServiceProvider
{

    public function register()
    {}

    public function boot(): void
    {
        Field::macro("tooltip", function (string $tooltip) {
            return $this->hintAction(
                Action::make('help')
                    ->icon('heroicon-o-information-circle')
                    ->extraAttributes(["class" => "text-gray-500 cursor-help"])
                    ->label("")
                    ->tooltip($tooltip)
            );
        });
    }
}
