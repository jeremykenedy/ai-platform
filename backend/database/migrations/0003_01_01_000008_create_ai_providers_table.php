<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('type', 20);
            $table->string('base_url')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_configured')->default(false);
            $table->string('health_status', 20)->default('unavailable');
            $table->timestamp('last_health_check_at')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
