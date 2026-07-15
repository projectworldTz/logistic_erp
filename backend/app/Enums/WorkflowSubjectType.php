<?php

namespace App\Enums;

use App\Models\Expense;
use App\Models\Quotation;

enum WorkflowSubjectType: string
{
    case Expense = 'expense';
    case Quotation = 'quotation';

    public function modelClass(): string
    {
        return match ($this) {
            self::Expense => Expense::class,
            self::Quotation => Quotation::class,
        };
    }
}
