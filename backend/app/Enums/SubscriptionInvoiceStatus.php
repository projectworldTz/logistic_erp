<?php

namespace App\Enums;

enum SubscriptionInvoiceStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';
}
