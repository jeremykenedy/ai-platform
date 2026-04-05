<?php

declare(strict_types=1);

namespace App\Enums;

enum MemoryCategory: string
{
    case Preference = 'preference';
    case Fact = 'fact';
    case Instruction = 'instruction';
    case Context = 'context';
    case Personality = 'personality';
}
