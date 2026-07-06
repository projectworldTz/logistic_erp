<?php

namespace App\Enums;

enum TransportMode: string
{
    case Sea = 'sea';
    case Air = 'air';
    case Land = 'land';
}
