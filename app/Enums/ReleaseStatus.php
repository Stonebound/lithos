<?php

declare(strict_types=1);

namespace App\Enums;

enum ReleaseStatus: string
{
    case Draft = 'draft';
    case Prepared = 'prepared';
    case Deployed = 'deployed';
}
