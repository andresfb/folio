<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use RuntimeException;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\error;
use function Laravel\Prompts\form;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

final class CreateUserCommand extends Command
{
    protected $signature = 'create:user';

    protected $description = 'Creates a new User';

    public function handle(CreateNewUser $action): void
    {
        try {
            clear();
            intro('Create a new User');

            $results = form()
                ->text(
                    label: 'Name',
                    default: Config::string('constants.admin_name'),
                    required: true,
                    name: 'name',
                )
                ->text(
                    label: 'Email',
                    default: Config::string('constants.admin_email'),
                    required: true,
                    validate: 'string|email|max:255',
                    name: 'email',
                )
                ->password(
                    label: 'Password',
                    required: true,
                    validate: 'string|min:8|max:255',
                    name: 'password',
                )->password(
                    label: 'Confirm Password',
                    required: true,
                    validate: 'string|min:8|max:255',
                    name: 'password_confirmation',
                )
                ->submit();

            if (User::where('email', $results['email'])->exists()) {
                throw new RuntimeException('User with that email exists');
            }

            if ($results['password'] !== $results['password_confirmation']) {
                throw new RuntimeException('Password does not match');
            }

            $user = $action->create($results);

            $user->email_verified_at = now()->toDateTimeString();
            $user->save();
        } catch (Exception $e) {
            error($e->getMessage());
        } finally {
            $this->newLine();
            outro('Done');
        }
    }
}
