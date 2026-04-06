<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('conversation_summaries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('conversation_id');
            $table->text('content');
            $table->json('covers_message_ids');
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_summaries');
    }
};
