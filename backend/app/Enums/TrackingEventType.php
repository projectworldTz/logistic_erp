<?php

namespace App\Enums;

enum TrackingEventType: string
{
    case Booked = 'booked';
    case GateIn = 'gate_in';
    case Loaded = 'loaded';
    case Departed = 'departed';
    case InTransit = 'in_transit';
    case CustomsHold = 'customs_hold';
    case CustomsCleared = 'customs_cleared';
    case Arrived = 'arrived';
    case GateOut = 'gate_out';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Exception = 'exception';
}
