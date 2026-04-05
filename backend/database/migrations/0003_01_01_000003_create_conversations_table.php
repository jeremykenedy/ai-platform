<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('project_id')->nullable();
            $table->ulid('persona_id')->nullable();
            $table->string('title')->nullable();
            $table->string('model_name')->nullable();
            $table->unsignedInteger('context_window_used')->default(0);
            $table->json('enabled_integrations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('persona_id')->references('id')->on('personas')->nullOnDelete();
            $table->index(['user_id', 'created_at']);
            $table->index('project_id');
            $table->index('persona_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
