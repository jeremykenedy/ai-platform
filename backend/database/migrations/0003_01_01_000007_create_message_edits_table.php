<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('message_edits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('message_id');
            $table->text('original_content');
            $table->timestamp('edited_at');
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_edits');
    }
};
