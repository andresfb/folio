<?php

namespace Database\Factories;

use Illuminate\Support\Facades\Date;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        return [
            'user_id' => $this->faker->word(),
            'slug' => $this->faker->slug(),
            'name' => $this->faker->name(),
            'personal' => $this->faker->boolean(),
            'active' => $this->faker->boolean(),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ];
    }
}
