<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('system_prompt');
            $table->string('model_name')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->decimal('top_p', 3, 2)->default(0.90);
            $table->unsignedSmallInteger('top_k')->default(40);
            $table->decimal('repeat_penalty', 3, 2)->default(1.10);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
