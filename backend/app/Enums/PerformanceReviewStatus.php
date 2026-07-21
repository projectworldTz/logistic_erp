<?php

namespace App\Enums;

enum PerformanceReviewStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
}
