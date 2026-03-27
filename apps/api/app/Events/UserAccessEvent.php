<?php

declare(strict_types=1);

namespace App\Events;

use App\Dtos\AccessItem;
use Illuminate\Foundation\Events\Dispatchable;

final class UserAccessEvent
{
    use Dispatchable;

    public function __construct(
        public readonly AccessItem $accessItem,
    ) {}
}
