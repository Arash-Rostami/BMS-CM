<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Components\Operators;

use Filament\Forms\Components\DateTimePicker;
use Filament\QueryBuilder\Constraints\DateConstraint;
use Illuminate\Support\Str;

trait AdaptiveDateOperator
{
    public function getSummary(): string
    {
        $constraint = $this->getConstraint();
        $settings = $this->getSettings();
        $hasTime = $constraint instanceof DateConstraint && $constraint->hasTime();
        $relative = ($settings['mode'] ?? null) === 'relative';
        $date = $relative ? $this->resolveRelativeDate($settings, $hasTime) : $this->getDateSetting('date');

        return __(
            'filament-query-builder::query-builder.operators.date.'.Str::snake($this->getName()).'.summary.'.($this->isInverse() ? 'inverse' : 'direct'),
            [
                'attribute' => $constraint->getAttributeLabel(),
                'date' => $date === null ? null : adaptiveDate($date, $hasTime && (! $relative || $this->isTimeBasedFilter($settings))),
            ],
        );
    }

    public function getFormSchema(): array
    {
        return array_map(
            fn (mixed $component): mixed => $component instanceof DateTimePicker ? maybeJalali($component) : $component,
            parent::getFormSchema(),
        );
    }
}
