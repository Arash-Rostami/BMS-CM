<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Components;

use App\Filament\Resources\Master\CalendarRuleResource\Components\Operators\IsAfterOperator;
use App\Filament\Resources\Master\CalendarRuleResource\Components\Operators\IsBeforeOperator;
use App\Filament\Resources\Master\CalendarRuleResource\Components\Operators\IsDateOperator;
use Filament\QueryBuilder\Constraints\DateConstraint;

class AdaptiveDateConstraint extends DateConstraint
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->pushOperators([IsAfterOperator::class, IsBeforeOperator::class, IsDateOperator::class]);
    }
}
