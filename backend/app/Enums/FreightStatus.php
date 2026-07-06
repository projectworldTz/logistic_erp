<?php

namespace App\Enums;

enum FreightStatus: string
{
    case Booked = 'booked';
    case CargoReceived = 'cargo_received';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
