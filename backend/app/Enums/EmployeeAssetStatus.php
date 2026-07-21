<?php

namespace App\Enums;

enum EmployeeAssetStatus: string
{
    case Assigned = 'assigned';
    case Returned = 'returned';
    case Lost = 'lost';
    case Damaged = 'damaged';
}
