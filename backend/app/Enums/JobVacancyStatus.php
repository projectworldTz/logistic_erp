<?php

namespace App\Enums;

enum JobVacancyStatus: string
{
    case Open = 'open';
    case OnHold = 'on_hold';
    case Filled = 'filled';
    case Closed = 'closed';
}
