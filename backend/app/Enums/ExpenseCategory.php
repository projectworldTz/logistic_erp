<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case CustomsDuty = 'customs_duty';
    case Trucking = 'trucking';
    case PortFees = 'port_fees';
    case Documentation = 'documentation';
    case Warehousing = 'warehousing';
    case Insurance = 'insurance';
    case Utilities = 'utilities';
    case OfficeSupplies = 'office_supplies';
    case Other = 'other';
}
