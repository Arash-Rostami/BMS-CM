<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Components\Operators;

use Filament\QueryBuilder\Constraints\DateConstraint\Operators\IsAfterOperator as BaseOperator;

class IsAfterOperator extends BaseOperator
{
    use AdaptiveDateOperator;
}
