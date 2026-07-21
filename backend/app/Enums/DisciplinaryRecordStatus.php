<?php

namespace App\Enums;

enum DisciplinaryRecordStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Acknowledged = 'acknowledged';
    case Appealed = 'appealed';
    case Resolved = 'resolved';
}
