<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->text('content');
            $table->ulid('source_conversation_id')->nullable();
            $table->ulid('source_message_id')->nullable();
            $table->string('category', 30);
            $table->unsignedSmallInteger('importance')->default(5);
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('source_conversation_id')->references('id')->on('conversations')->nullOnDelete();
            $table->foreign('source_message_id')->references('id')->on('messages')->nullOnDelete();
            $table->index(['user_id', 'is_active', 'category', 'importance']);
            $table->index('source_conversation_id');
            $table->index('source_message_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE memories ADD COLUMN embedding vector(1536)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
