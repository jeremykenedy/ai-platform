<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('training_jobs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('dataset_id');
            $table->ulid('base_model_id');
            $table->string('output_model_name');
            $table->json('config')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('progress')->default(0);
            $table->text('log_output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('dataset_id')->references('id')->on('training_datasets')->cascadeOnDelete();
            $table->foreign('base_model_id')->references('id')->on('ai_models');
            $table->index(['user_id', 'status']);
            $table->index('dataset_id');
            $table->index('base_model_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_jobs');
    }
};
