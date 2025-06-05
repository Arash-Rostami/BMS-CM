<?php

namespace App\Enums;

enum AllowedDomain: string
{
    case MAIN_COMPANY = '@persolco.com';
    case SECOND_COMPANY = '@anothercompany.org';

    /**
     * Return all raw suffix strings.
     */
    public static function values(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }
}
