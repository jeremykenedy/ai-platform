<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\UserIntegration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshIntegrationTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var int[] */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly string $userIntegrationId,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        /** @var UserIntegration $integration */
        $integration = UserIntegration::with('definition')->findOrFail($this->userIntegrationId);

        $definition = $integration->definition;

        if ($definition === null) {
            Log::warning('[RefreshIntegrationTokenJob] No integration definition found', [
                'user_integration_id' => $this->userIntegrationId,
            ]);

            return;
        }

        if ($integration->oauth_refresh_token === null) {
            Log::warning('[RefreshIntegrationTokenJob] No refresh token available', [
                'user_integration_id' => $this->userIntegrationId,
                'integration_name' => $definition->name,
            ]);

            $integration->update([
                'is_enabled' => false,
                'last_error' => 'No refresh token available.',
            ]);

            return;
        }

        $tokenEndpoint = $this->resolveTokenEndpoint($definition->name);

        if ($tokenEndpoint === null) {
            Log::warning('[RefreshIntegrationTokenJob] Unknown token endpoint for integration', [
                'integration_name' => $definition->name,
            ]);

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(20)
                ->connectTimeout(10)
                ->post($tokenEndpoint, [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $integration->oauth_refresh_token,
                    'client_id' => config("services.{$definition->name}.client_id"),
                    'client_secret' => config("services.{$definition->name}.client_secret"),
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException(
                    "Token refresh failed with HTTP {$response->status()}: {$response->body()}"
                );
            }

            $data = $response->json();

            $integration->update([
                'oauth_token' => $data['access_token'],
                'oauth_refresh_token' => $data['refresh_token'] ?? $integration->oauth_refresh_token,
                'oauth_expires_at' => isset($data['expires_in'])
                    ? now()->addSeconds((int) $data['expires_in'])
                    : null,
                'last_error' => null,
            ]);

            Log::info('[RefreshIntegrationTokenJob] Token refreshed successfully', [
                'user_integration_id' => $this->userIntegrationId,
                'integration_name' => $definition->name,
            ]);
        } catch (\Throwable $e) {
            Log::error('[RefreshIntegrationTokenJob] Token refresh failed', [
                'user_integration_id' => $this->userIntegrationId,
                'integration_name' => $definition->name,
                'error' => $e->getMessage(),
            ]);

            $integration->update([
                'is_enabled' => false,
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[RefreshIntegrationTokenJob] Job exhausted all retries', [
            'user_integration_id' => $this->userIntegrationId,
            'error' => $exception->getMessage(),
        ]);

        $integration = UserIntegration::find($this->userIntegrationId);

        if ($integration !== null) {
            $integration->update([
                'is_enabled' => false,
                'last_error' => 'Token refresh failed after all retries: '.$exception->getMessage(),
            ]);
        }
    }

    private function resolveTokenEndpoint(string $integrationName): ?string
    {
        /** @var array<string, string> $endpoints */
        $endpoints = [
            'google' => 'https://oauth2.googleapis.com/token',
            'github' => 'https://github.com/login/oauth/access_token',
            'slack' => 'https://slack.com/api/oauth.v2.access',
            'notion' => 'https://api.notion.com/v1/oauth/token',
            'discord' => 'https://discord.com/api/oauth2/token',
            'microsoft' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'spotify' => 'https://accounts.spotify.com/api/token',
            'dropbox' => 'https://api.dropboxapi.com/oauth2/token',
        ];

        return $endpoints[$integrationName] ?? null;
    }
}
