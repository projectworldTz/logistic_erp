<?php

namespace App\Enums;

enum AssetType: string
{
    case Laptop = 'laptop';
    case Phone = 'phone';
    case Vehicle = 'vehicle';
    case Uniform = 'uniform';
    case Tool = 'tool';
    case Other = 'other';
}
