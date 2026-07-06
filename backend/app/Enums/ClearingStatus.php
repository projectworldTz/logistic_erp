<?php

namespace App\Enums;

enum ClearingStatus: string
{
    case Pending = 'pending';
    case DocumentsReceived = 'documents_received';
    case UnderClearance = 'under_clearance';
    case CustomsHold = 'customs_hold';
    case Cleared = 'cleared';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
