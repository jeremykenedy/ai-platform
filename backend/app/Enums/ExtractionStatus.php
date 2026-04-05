<?php

declare(strict_types=1);

namespace App\Enums;

enum ExtractionStatus: string
{
    case Pending = 'pending';
    case Complete = 'complete';
    case Failed = 'failed';
}
