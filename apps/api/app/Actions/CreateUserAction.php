<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dtos\NewUserItem;
use App\Enums\MemberRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

final readonly class CreateUserAction
{
    /**
     * @throws Throwable
     */
    public function handle(
        NewUserItem $input,
        string &$token,
        ?CarbonInterface $verifiedAt = null
    ): User {
        return DB::transaction(function () use ($input, &$token, $verifiedAt): User {
            $user = User::query()->create([
                'name' => $input->name,
                'email' => $input->email,
                'password' => Hash::make($input->password),
            ]);

            $this->createWorkspace($user);
            $token = $user->createToken('auth-token')->plainTextToken;

            if (blank($verifiedAt)) {
                $user->sendEmailVerificationNotification();

                return $user;
            }

            $user->email_verified_at = $verifiedAt;
            $user->save();

            return $user->fresh() ?? $user;
        });
    }

    private function createWorkspace(User $user): void
    {
        $workspace = Workspace::query()
            ->updateOrCreate([
                'user_id' => $user->id,
            ], [
                'name' => "$user->name Workspace",
            ]);

        WorkspaceMember::query()
            ->updateOrCreate([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
            ], [
                'role' => MemberRole::OWNER,
            ]);
    }
}
