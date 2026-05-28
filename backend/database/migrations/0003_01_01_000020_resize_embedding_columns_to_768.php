<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE messages DROP COLUMN IF EXISTS embedding');
        DB::statement('ALTER TABLE messages ADD COLUMN embedding vector(768)');

        DB::statement('ALTER TABLE memories DROP COLUMN IF EXISTS embedding');
        DB::statement('ALTER TABLE memories ADD COLUMN embedding vector(768)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE messages DROP COLUMN IF EXISTS embedding');
        DB::statement('ALTER TABLE messages ADD COLUMN embedding vector(1536)');

        DB::statement('ALTER TABLE memories DROP COLUMN IF EXISTS embedding');
        DB::statement('ALTER TABLE memories ADD COLUMN embedding vector(1536)');
    }
};
