<?php

namespace App\Providers;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\ServiceProvider;

class FilamentMacroServiceProvider extends ServiceProvider
{
    public function register() {}

    public function boot(): void
    {
        Field::macro('tooltip', function (string $tooltip) {
            return $this->hintAction(
                Action::make('help')
                    ->icon('heroicon-o-information-circle')
                    ->extraAttributes(['class' => 'text-gray-500 cursor-help'])
                    ->label('')
                    ->tooltip($tooltip)
            );
        });

        DatePicker::macro('adaptive', function (): static {
            return isJalaliCalendar()
                ? $this->jalali()
                : $this;
        });

        $adaptiveDateFormatState = static function ($state, bool $withTime): ?string {
            return blank($state) ? null : adaptiveDate($state, $withTime);
        };

        foreach ([TextColumn::class, TextEntry::class] as $dateComponent) {
            $dateComponent::macro('adaptiveDate', function (?string $format = null) use ($adaptiveDateFormatState): static {
                if ($format !== null) {
                    return isJalaliCalendar() ? $this->jalaliDate($format) : $this->date($format);
                }

                return $this->formatStateUsing(fn ($state) => $adaptiveDateFormatState($state, false));
            });

            $dateComponent::macro('adaptiveDateTime', function (?string $format = null) use ($adaptiveDateFormatState): static {
                if ($format !== null) {
                    return isJalaliCalendar() ? $this->jalaliDateTime($format) : $this->dateTime($format);
                }

                return $this->formatStateUsing(fn ($state) => $adaptiveDateFormatState($state, true));
            });
        }
    }
}
