<?php

namespace App\Enums;

enum WarehouseItemStatus: string
{
    case Received = 'received';
    case Stored = 'stored';
    case Picked = 'picked';
    case Dispatched = 'dispatched';
    case Damaged = 'damaged';
}
