<?php

declare(strict_types=1);

namespace App\Enums;

enum BenchmarkCategory: string
{
    case Chat = 'chat';
    case Code = 'code';
    case Reasoning = 'reasoning';
    case Vision = 'vision';
    case Speed = 'speed';
}
