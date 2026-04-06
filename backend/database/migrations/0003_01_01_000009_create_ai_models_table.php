<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('provider_id');
            $table->string('name');
            $table->string('ollama_model_id')->nullable();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('version')->nullable();
            $table->unsignedInteger('context_window')->nullable();
            $table->unsignedInteger('max_output_tokens')->nullable();
            $table->json('capabilities')->nullable();
            $table->boolean('supports_vision')->default(false);
            $table->boolean('supports_functions')->default(false);
            $table->boolean('supports_streaming')->default(true);
            $table->decimal('input_cost_per_1k', 10, 6)->nullable();
            $table->decimal('output_cost_per_1k', 10, 6)->nullable();
            $table->string('parameter_count', 20)->nullable();
            $table->string('quantization', 20)->nullable();
            $table->string('ollama_digest')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_local')->default(false);
            $table->boolean('update_available')->default(false);
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('provider_id')->references('id')->on('ai_providers')->cascadeOnDelete();
            $table->index('provider_id');
            $table->index('is_active');
            $table->index('is_default');
            $table->index('is_local');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
