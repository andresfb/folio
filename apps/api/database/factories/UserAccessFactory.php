<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserAccess;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAccess>
 */
final class UserAccessFactory extends Factory
{
    protected $model = UserAccess::class;

    public function definition(): array
    {
        return [
            'ip_address' => $this->faker->ipv4(),
            'agent' => $this->faker->word(),
            'login_at' => $this->faker->word(),
            'type' => $this->faker->word(),

            'user_id' => User::factory(),
        ];
    }
}
