<?php

namespace App\Enums;

enum ExitType: string
{
    case Resignation = 'resignation';
    case Termination = 'termination';
    case Retirement = 'retirement';
    case EndOfContract = 'end_of_contract';
    case Redundancy = 'redundancy';
}
