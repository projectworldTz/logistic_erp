<?php

namespace App\Enums;

enum SalaryPaymentBatchStatus: string
{
    case Draft = 'draft';
    case Exported = 'exported';
    case Completed = 'completed';
}
