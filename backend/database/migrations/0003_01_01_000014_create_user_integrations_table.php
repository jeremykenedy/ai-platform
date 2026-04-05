<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_integrations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('integration_id');
            $table->boolean('is_enabled')->default(false);
            $table->text('credentials')->nullable();
            $table->text('oauth_token')->nullable();
            $table->text('oauth_refresh_token')->nullable();
            $table->timestamp('oauth_expires_at')->nullable();
            $table->json('scopes_granted')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('integration_id')->references('id')->on('integration_definitions')->cascadeOnDelete();
            $table->unique(['user_id', 'integration_id']);
            $table->index('user_id');
            $table->index('integration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_integrations');
    }
};
