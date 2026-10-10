<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Components\Operators;

use Filament\QueryBuilder\Constraints\DateConstraint\Operators\IsBeforeOperator as BaseOperator;

class IsBeforeOperator extends BaseOperator
{
    use AdaptiveDateOperator;
}
