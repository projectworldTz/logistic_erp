<?php

namespace App\Enums;

enum CustomsAssessmentStatus: string
{
    case Pending = 'pending';
    case Assessed = 'assessed';
    case Objected = 'objected';
    case Released = 'released';
}
