<?php

namespace App\Enums;

enum PreferredPaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case MobileMoney = 'mobile_money';
    case Cash = 'cash';
    case Cheque = 'cheque';
}
