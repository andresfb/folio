<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserAccessEvent;
use App\Models\UserAccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class UserAccessListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(UserAccessEvent $event): void
    {
        UserAccess::create(
            $event->accessItem->toArray(),
        );
    }
}
