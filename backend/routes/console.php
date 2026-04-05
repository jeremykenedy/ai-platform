<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('memory:decay')->dailyAt('03:00');
Schedule::command('activity:prune')->weeklyOn(0, '02:00');
Schedule::command('integrations:clear-expired-tokens')->dailyAt('04:00');
Schedule::command('models:sync')->dailyAt('05:00');
Schedule::command('horizon:snapshot')->everyFiveMinutes();
