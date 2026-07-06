<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Booked = 'booked';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case Cleared = 'cleared';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
