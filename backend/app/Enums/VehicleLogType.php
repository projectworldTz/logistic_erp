<?php

namespace App\Enums;

enum VehicleLogType: string
{
    case Maintenance = 'maintenance';
    case Fuel = 'fuel';
    case Insurance = 'insurance';
    case Trip = 'trip';
}
