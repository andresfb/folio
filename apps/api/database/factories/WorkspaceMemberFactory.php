<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<WorkspaceMember>
 */
final class WorkspaceMemberFactory extends Factory
{
    protected $model = WorkspaceMember::class;

    public function definition(): array
    {
        return [
            'user_id' => $this->faker->word(),
            'role' => $this->faker->word(),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),

            'workspace_id' => Workspace::factory(),
        ];
    }
}
