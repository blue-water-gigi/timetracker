<?php

declare(strict_types=1);

namespace App\Enums;

enum SystemRole: string
{
    case ADMINISTRATOR = 'admin';
    case MODERATOR = 'moderator';
    case USER = 'user';
}
