<?php

declare(strict_types=1);

namespace App\Enums;

enum AuthType: string
{
    case OAuth2 = 'oauth2';
    case ApiKey = 'api_key';
    case Pat = 'pat';
    case None = 'none';
}
