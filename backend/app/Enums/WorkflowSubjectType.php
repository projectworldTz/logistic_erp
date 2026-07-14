<?php

namespace App\Enums;

use App\Models\Expense;

enum WorkflowSubjectType: string
{
    case Expense = 'expense';

    public function modelClass(): string
    {
        return match ($this) {
            self::Expense => Expense::class,
        };
    }
}
