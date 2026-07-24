<?php

namespace App\Filament\Widgets;

use Filament\Widgets\AccountWidget as BaseWidget;

class AccountWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.account-widget';
}
