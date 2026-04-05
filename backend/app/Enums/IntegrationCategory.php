<?php

declare(strict_types=1);

namespace App\Enums;

enum IntegrationCategory: string
{
    case Productivity = 'productivity';
    case Developer = 'developer';
    case Design = 'design';
    case Finance = 'finance';
    case Search = 'search';
    case Career = 'career';
    case Legal = 'legal';
    case Entertainment = 'entertainment';
    case Local = 'local';
    case Ai = 'ai';
}
