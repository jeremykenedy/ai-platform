<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('integration_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('category', 30);
            $table->string('auth_type', 20);
            $table->json('oauth_scopes')->nullable();
            $table->string('icon_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('requires_permission')->nullable();
            $table->string('documentation_url')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_definitions');
    }
};
