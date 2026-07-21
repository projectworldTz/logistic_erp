<?php

namespace App\Enums;

enum ExitRecordStatus: string
{
    case Initiated = 'initiated';
    case InProgress = 'in_progress';
    case Cleared = 'cleared';
    case Completed = 'completed';
}
