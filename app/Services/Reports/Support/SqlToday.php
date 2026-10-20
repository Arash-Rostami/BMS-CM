<?php

namespace App\Services\Reports\Support;

final class SqlToday
{
    public static function literal(): string
    {
        return "'".today()->toDateString()."'";
    }
}
