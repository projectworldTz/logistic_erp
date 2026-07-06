<?php

namespace App\Enums;

enum LeadSource: string
{
    case Website = 'website';
    case Referral = 'referral';
    case ColdCall = 'cold_call';
    case Social = 'social';
    case Other = 'other';
}
