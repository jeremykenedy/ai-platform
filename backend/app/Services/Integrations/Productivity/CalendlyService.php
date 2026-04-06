<?php

declare(strict_types=1);

namespace App\Services\Integrations\Productivity;

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Support\Facades\Http;

class CalendlyService extends AbstractIntegrationService
{
    protected string $integrationName = 'calendly';

    private const BASE_URL = 'https://api.calendly.com';

    private const TOKEN_URL = 'https://auth.calendly.com/oauth/token';

    private const AUTH_URL = 'https://auth.calendly.com/oauth/authorize';

    public function getAuthUrl(User $user): ?string
    {
        $params = http_build_query([
            'client_id'     => config('services.calendly.client_id'),
            'redirect_uri'  => config('services.calendly.redirect_uri'),
            'response_type' => 'code',
            'state'         => $user->getKey(),
        ]);

        return self::AUTH_URL.'?'.$params;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function handleCallback(User $user, array $params): void
    {
        $code = (string) ($params['code'] ?? '');

        $response = Http::asForm()
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::TOKEN_URL, [
                'code'          => $code,
                'client_id'     => config('services.calendly.client_id'),
                'client_secret' => config('services.calendly.client_secret'),
                'redirect_uri'  => config('services.calendly.redirect_uri'),
                'grant_type'    => 'authorization_code',
            ]);

        $response->throw();

        $data = $response->json();

        $definition = $this->getDefinition();

        UserIntegration::updateOrCreate(
            [
                'user_id'        => $user->getKey(),
                'integration_id' => $definition->getKey(),
            ],
            [
                'is_enabled'          => true,
                'oauth_token'         => $data['access_token'] ?? null,
                'oauth_refresh_token' => $data['refresh_token'] ?? null,
                'oauth_expires_at'    => isset($data['expires_in'])
                    ? now()->addSeconds((int) $data['expires_in'])
                    : null,
            ],
        );
    }

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name'        => 'list_event_types',
                'description' => 'List all Calendly event types for the user.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [],
                ],
            ],
            [
                'name'        => 'get_availability',
                'description' => 'Get available time slots for a Calendly event type.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['eventTypeUri'],
                    'properties' => [
                        'eventTypeUri' => ['type' => 'string', 'description' => 'Calendly event type URI.'],
                    ],
                ],
            ],
            [
                'name'        => 'create_scheduling_link',
                'description' => 'Create a single-use Calendly scheduling link.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['eventTypeUri'],
                    'properties' => [
                        'eventTypeUri' => ['type' => 'string', 'description' => 'Calendly event type URI.'],
                    ],
                ],
            ],
            [
                'name'        => 'list_events',
                'description' => 'List scheduled Calendly events.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'status' => [
                            'type'        => 'string',
                            'enum'        => ['active', 'canceled'],
                            'description' => 'Filter by event status.',
                        ],
                        'minStartTime' => ['type' => 'string', 'description' => 'Minimum start time (ISO8601).'],
                        'maxStartTime' => ['type' => 'string', 'description' => 'Maximum start time (ISO8601).'],
                    ],
                ],
            ],
            [
                'name'        => 'cancel_event',
                'description' => 'Cancel a scheduled Calendly event.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['eventUri'],
                    'properties' => [
                        'eventUri' => ['type' => 'string', 'description' => 'Calendly event URI.'],
                        'reason'   => ['type' => 'string', 'description' => 'Reason for cancellation.'],
                    ],
                ],
            ],
            [
                'name'        => 'list_invitees',
                'description' => 'List invitees for a Calendly event.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['eventUri'],
                    'properties' => [
                        'eventUri' => ['type' => 'string', 'description' => 'Calendly event URI.'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'list_event_types'       => $this->listEventTypes($user),
            'get_availability'       => $this->getAvailability($params, $user),
            'create_scheduling_link' => $this->createSchedulingLink($params, $user),
            'list_events'            => $this->listEvents($params, $user),
            'cancel_event'           => $this->cancelEvent($params, $user),
            'list_invitees'          => $this->listInvitees($params, $user),
            default                  => throw new \InvalidArgumentException("Unknown tool: {$toolName}"),
        };
    }

    public function testConnection(User $user): bool
    {
        try {
            $this->getCurrentUser($user);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getCurrentUser(User $user): array
    {
        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/users/me');

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function listEventTypes(User $user): array
    {
        $me = $this->getCurrentUser($user);
        $userUri = $me['resource']['uri'] ?? '';

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/event_types', [
                'user'   => $userUri,
                'active' => 'true',
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getAvailability(array $params, User $user): array
    {
        $eventTypeUri = (string) $params['eventTypeUri'];

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/event_type_available_times', [
                'event_type' => $eventTypeUri,
                'start_time' => now()->toIso8601String(),
                'end_time'   => now()->addDays(14)->toIso8601String(),
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function createSchedulingLink(array $params, User $user): array
    {
        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/scheduling_links', [
                'max_event_count' => 1,
                'owner'           => (string) $params['eventTypeUri'],
                'owner_type'      => 'EventType',
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function listEvents(array $params, User $user): array
    {
        $me = $this->getCurrentUser($user);
        $userUri = $me['resource']['uri'] ?? '';

        $query = array_filter([
            'user'           => $userUri,
            'status'         => $params['status'] ?? null,
            'min_start_time' => $params['minStartTime'] ?? null,
            'max_start_time' => $params['maxStartTime'] ?? null,
            'count'          => 20,
        ]);

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/scheduled_events', $query);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function cancelEvent(array $params, User $user): array
    {
        $eventUri = (string) $params['eventUri'];
        $eventUuid = basename($eventUri);

        $body = array_filter([
            'reason' => $params['reason'] ?? null,
        ]);

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/scheduled_events/'.$eventUuid.'/cancellation', $body);

        $response->throw();

        return $response->json() ?? ['cancelled' => true];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function listInvitees(array $params, User $user): array
    {
        $eventUri = (string) $params['eventUri'];
        $eventUuid = basename($eventUri);

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/scheduled_events/'.$eventUuid.'/invitees');

        $response->throw();

        return $response->json() ?? [];
    }

    private function getOauthToken(User $user): string
    {
        $integration = $this->getUserIntegration($user);

        return (string) ($integration?->oauth_token ?? '');
    }
}
