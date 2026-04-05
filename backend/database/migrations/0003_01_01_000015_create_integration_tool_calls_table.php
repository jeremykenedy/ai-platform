<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_tool_calls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('conversation_id');
            $table->ulid('message_id');
            $table->ulid('integration_id');
            $table->string('tool_name');
            $table->json('input');
            $table->json('output')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
            $table->foreign('integration_id')->references('id')->on('integration_definitions')->cascadeOnDelete();
            $table->index('user_id');
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_tool_calls');
    }
};
