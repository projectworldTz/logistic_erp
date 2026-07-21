<?php

namespace App\Enums;

enum OnboardingChecklistStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
