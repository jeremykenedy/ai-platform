<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('memory_conflicts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('memory_id');
            $table->ulid('conflicts_with');
            $table->boolean('resolved')->default(false);
            $table->string('resolution', 20)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('memory_id')->references('id')->on('memories')->cascadeOnDelete();
            $table->foreign('conflicts_with')->references('id')->on('memories')->cascadeOnDelete();
            $table->index('user_id');
            $table->index('memory_id');
            $table->index('conflicts_with');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_conflicts');
    }
};
