<?php

declare(strict_types=1);

namespace App\Events;

use App\Dtos\AccessItem;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class UserAccessEvent
{
    use Dispatchable;

    public function __construct(
        public AccessItem $accessItem,
    ) {}
}
