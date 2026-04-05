<?php

declare(strict_types=1);

namespace App\Enums;

enum HealthStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unavailable = 'unavailable';
}
