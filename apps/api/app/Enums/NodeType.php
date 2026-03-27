<?php

declare(strict_types=1);

namespace App\Enums;

enum NodeType: string
{
    case FOLDER = 'folder';
    case DOCUMENT = 'document';
}
