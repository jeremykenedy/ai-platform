<?php

declare(strict_types=1);

namespace App\Enums;

enum FinishReason: string
{
    case Stop = 'stop';
    case Length = 'length';
    case Error = 'error';
    case Interrupted = 'interrupted';
}
