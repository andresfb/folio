<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectNode;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ProjectNodeFactory extends Factory
{
    protected $model = ProjectNode::class;

    public function definition(): array
    {
        return [
            'node_type' => $this->faker->word(),
            'slug' => $this->faker->slug(),
            'title' => $this->faker->word(),
            'sort_index' => $this->faker->randomFloat(),
            'depth' => $this->faker->randomNumber(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'workspace_id' => Workspace::factory(),
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'parent_id' => Project::factory(),
        ];
    }
}
