<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('default_model_id')->nullable();
            $table->ulid('default_persona_id')->nullable();
            $table->string('theme', 20)->default('system');
            $table->unsignedSmallInteger('font_size')->default(14);
            $table->boolean('send_on_enter')->default(true);
            $table->boolean('show_token_counts')->default(false);
            $table->boolean('memory_enabled')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
