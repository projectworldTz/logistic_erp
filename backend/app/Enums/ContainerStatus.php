<?php

namespace App\Enums;

enum ContainerStatus: string
{
    case AtPort = 'at_port';
    case InTransit = 'in_transit';
    case AtWarehouse = 'at_warehouse';
    case Delivered = 'delivered';
    case Returned = 'returned';
    case EmptyReturn = 'empty_return';
}
