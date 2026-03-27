<?php

use App\Models\Project;
use App\Models\ProjectNode;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_nodes', static function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Workspace::class)
                ->constrained('workspaces')
                ->onDelete('cascade');
            $table->foreignIdFor(Project::class)
                ->constrained('projects')
                ->onDelete('cascade');
            $table->foreignIdFor(ProjectNode::class, 'parent_id')
                ->nullable()
                ->constrained('project_nodes')
                ->onDelete('cascade');
            $table->foreignIdFor(User::class)
                ->constrained('users')
                ->onDelete('cascade');
            $table->string('node_type');
            $table->string('slug')->unique();
            $table->string('title');
            $table->float('sort_index')->default(0);
            $table->integer('depth')->default(0);
            $table->archivedAt();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['project_id', 'parent_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_nodes');
    }
};
