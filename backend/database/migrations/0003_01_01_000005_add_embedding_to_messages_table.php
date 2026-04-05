<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('ALTER TABLE messages ADD COLUMN embedding vector(1536)');
    }

    public function down(): void
    {
        if (Schema::hasColumn('messages', 'embedding')) {
            DB::statement('ALTER TABLE messages DROP COLUMN embedding');
        }
    }
};
