<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MessageCreated;
use Illuminate\Support\Facades\DB;

class UpdateUserLastActive
{
    public function handle(MessageCreated $event): void
    {
        $userId = DB::table('conversations')
            ->where('id', $event->conversationId)
            ->value('user_id');

        if ($userId === null) {
            return;
        }

        DB::table('users')
            ->where('id', $userId)
            ->update(['last_active_at' => now()]);
    }
}
