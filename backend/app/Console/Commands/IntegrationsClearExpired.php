<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserIntegration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class IntegrationsClearExpired extends Command
{
    protected $signature = 'integrations:clear-expired-tokens';

    protected $description = 'Clear or refresh expired OAuth tokens';

    public function handle(): int
    {
        $expired = UserIntegration::where('is_enabled', true)
            ->whereNotNull('oauth_expires_at')
            ->where('oauth_expires_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired OAuth tokens found.');

            return self::SUCCESS;
        }

        $refreshed = 0;
        $disabled = 0;

        foreach ($expired as $integration) {
            if (! empty($integration->oauth_refresh_token)) {
                // Refresh token exists: flag for reconnection (provider-specific refresh
                // logic belongs in each integration service; mark as needing reconnection).
                Log::info('[IntegrationsClearExpired] Integration requires refresh.', [
                    'user_integration_id' => $integration->id,
                    'user_id' => $integration->user_id,
                    'integration_id' => $integration->integration_id,
                ]);

                $integration->update([
                    'last_error' => 'OAuth token expired, reconnection required',
                ]);

                $refreshed++;
            } else {
                // No refresh token: disable the integration entirely.
                $integration->update([
                    'is_enabled' => false,
                    'last_error' => 'OAuth token expired',
                ]);

                $disabled++;
            }
        }

        $total = $expired->count();

        $this->info("Processed {$total} expired token(s). {$refreshed} flagged for refresh, {$disabled} disabled.");

        return self::SUCCESS;
    }
}
