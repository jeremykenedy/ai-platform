<?php

declare(strict_types=1);

namespace App\Enums;

enum DatasetFormat: string
{
    case ShareGpt = 'sharegpt';
    case Alpaca = 'alpaca';
}
