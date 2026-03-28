<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dtos\AccessItem;
use App\Dtos\LoginUserItem;
use App\Enums\AccessType;
use App\Events\UserAccessEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;

final class LoginUserAction
{
    private string $error = '';

    public function handle(LoginUserItem $item, string &$token): ?User
    {
        $user = User::query()
            ->where('email', $item->email)
            ->first();

        if (! $user || ! Hash::check($item->password, $user->password)) {
            $this->error = 'Invalid credentials';

            return null;
        }

        $workspace = Workspace::query()
            ->where('user_id', $user->id)
            ->where('active', true)
            ->first();

        if (blank($workspace)) {
            $this->error = 'User has no active Workspaces';

            return null;
        }

        $token = $user->createToken($item->client)->plainTextToken;

        event(new UserAccessEvent(new AccessItem(
            userId: $user->id,
            type: AccessType::LOGIN,
            ipAddress: $item->ipAddress,
            agent: $item->agent,
            loginAt: now(),
        )));

        return $user;
    }

    public function getError(): string
    {
        return $this->error;
    }
}
