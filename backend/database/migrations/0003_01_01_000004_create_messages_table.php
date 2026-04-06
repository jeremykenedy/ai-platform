<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('conversation_id');
            $table->string('role', 20);
            $table->text('content');
            $table->unsignedInteger('tokens_used')->nullable();
            $table->string('finish_reason', 30)->nullable();
            $table->string('model_version')->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
