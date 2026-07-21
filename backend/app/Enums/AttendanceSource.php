<?php

namespace App\Enums;

enum AttendanceSource: string
{
    case Manual = 'manual';
    case Import = 'import';
    case Mobile = 'mobile';
    case Biometric = 'biometric';
    case Gps = 'gps';
}
