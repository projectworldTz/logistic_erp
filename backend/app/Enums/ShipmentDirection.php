<?php

namespace App\Enums;

enum ShipmentDirection: string
{
    case Import = 'import';
    case Export = 'export';
}
