<?php

declare(strict_types=1);

namespace App\Enums;

enum ConflictResolution: string
{
    case KeepNew = 'keep_new';
    case KeepOld = 'keep_old';
    case Merge = 'merge';
    case Dismiss = 'dismiss';
}
