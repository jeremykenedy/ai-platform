<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_benchmarks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('model_id');
            $table->string('category', 20);
            $table->string('prompt_hash');
            $table->unsignedInteger('ttft_ms');
            $table->decimal('tokens_per_sec', 8, 2);
            $table->unsignedInteger('total_tokens');
            $table->decimal('quality_score', 4, 2)->nullable();
            $table->timestamp('ran_at');
            $table->timestamps();

            $table->foreign('model_id')->references('id')->on('ai_models')->cascadeOnDelete();
            $table->index('model_id');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_benchmarks');
    }
};
