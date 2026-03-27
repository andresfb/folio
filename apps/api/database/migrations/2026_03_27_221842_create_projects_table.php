<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', static function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Workspace::class)
                ->constrained('workspaces')
                ->onDelete('cascade');
            $table->foreignIdFor(User::class)
                ->constrained('users');
            $table->string('slug');
            $table->string('title');
            $table->string('description')->nullable();
            $table->boolean('active');
            $table->archivedAt();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
