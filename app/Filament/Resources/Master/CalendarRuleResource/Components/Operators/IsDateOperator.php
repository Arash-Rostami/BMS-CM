<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Components\Operators;

use Filament\QueryBuilder\Constraints\DateConstraint\Operators\IsDateOperator as BaseOperator;

class IsDateOperator extends BaseOperator
{
    use AdaptiveDateOperator;
}
