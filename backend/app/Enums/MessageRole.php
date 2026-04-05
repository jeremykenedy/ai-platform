<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case System = 'system';
}
