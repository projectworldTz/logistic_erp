<?php

namespace App\Enums;

enum ClearingDirection: string
{
    case Import = 'import';
    case Export = 'export';
}
