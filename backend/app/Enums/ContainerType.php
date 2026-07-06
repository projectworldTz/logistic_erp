<?php

namespace App\Enums;

enum ContainerType: string
{
    case Dry20 = 'dry_20';
    case Dry40 = 'dry_40';
    case Dry40HC = 'dry_40_hc';
    case Reefer20 = 'reefer_20';
    case Reefer40 = 'reefer_40';
    case OpenTop = 'open_top';
    case FlatRack = 'flat_rack';
    case Tank = 'tank';
}
