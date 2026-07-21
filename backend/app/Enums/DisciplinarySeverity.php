<?php

namespace App\Enums;

enum DisciplinarySeverity: string
{
    case VerbalWarning = 'verbal_warning';
    case WrittenWarning = 'written_warning';
    case Suspension = 'suspension';
    case Termination = 'termination';
}
