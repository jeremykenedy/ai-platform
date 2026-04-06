<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('message_id');
            $table->string('disk')->default('s3');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->longText('extracted_text')->nullable();
            $table->string('extraction_status', 20)->default('pending');
            $table->unsignedInteger('token_estimate')->nullable();
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
