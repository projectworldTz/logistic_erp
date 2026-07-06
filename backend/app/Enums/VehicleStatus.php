<?php

namespace App\Enums;

enum VehicleStatus: string
{
    case Active = 'active';
    case InMaintenance = 'in_maintenance';
    case OutOfService = 'out_of_service';
    case Retired = 'retired';
}
