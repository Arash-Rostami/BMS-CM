<?php

namespace App\Services\Imports;

enum ColumnCategory
{
    case Match;
    case Fallback;
    case ManualSet;
}
