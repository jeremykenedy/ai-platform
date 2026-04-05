<?php

declare(strict_types=1);

namespace App\Enums;

enum ToolCallStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Error = 'error';
}
