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
        UserAccess::query()->create([
            'user_id' => $event->accessItem->userId,
            'type' => $event->accessItem->type,
            'ip_address' => $event->accessItem->ipAddress,
            'agent' => $event->accessItem->agent,
            'login_at' => $event->accessItem->loginAt,
        ]);
    }
}
