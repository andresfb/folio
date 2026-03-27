<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_accesses', static function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(User::class)
                ->constrained('users')
                ->onDelete('cascade');
            $table->string('type');
            $table->string('ip_address');
            $table->text('agent');
            $table->timestamp('login_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_accesses');
    }
};
