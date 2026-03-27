<?php

declare(strict_types=1);

namespace App\Enums;

enum AccessType: string
{
    case LOGIN = 'login';
    case LOGOUT = 'logout';
}
