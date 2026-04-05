<?php

declare(strict_types=1);

namespace App\Enums;

enum ProviderType: string
{
    case Local = 'local';
    case Remote = 'remote';
}
