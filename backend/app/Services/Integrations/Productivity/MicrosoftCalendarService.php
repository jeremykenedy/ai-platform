<?php

declare(strict_types=1);

namespace App\Services\Integrations\Productivity;

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Support\Facades\Http;

class MicrosoftCalendarService extends AbstractIntegrationService
{
    protected string $integrationName = 'microsoft_calendar';

    private const BASE_URL = 'https://graph.microsoft.com/v1.0/me';

    private const TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

    private const AUTH_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';

    private const SCOPES = [
        'https://graph.microsoft.com/Calendars.ReadWrite',
        'https://graph.microsoft.com/Mail.ReadWrite',
        'offline_access',
    ];

    public function getAuthUrl(User $user): ?string
    {
        $params = http_build_query([
            'client_id'     => config('services.microsoft.client_id'),
            'redirect_uri'  => config('services.microsoft.redirect_uri'),
            'response_type' => 'code',
            'scope'         => implode(' ', self::SCOPES),
            'response_mode' => 'query',
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
                'client_id'     => config('services.microsoft.client_id'),
                'client_secret' => config('services.microsoft.client_secret'),
                'redirect_uri'  => config('services.microsoft.redirect_uri'),
                'grant_type'    => 'authorization_code',
                'scope'         => implode(' ', self::SCOPES),
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
                'scopes_granted' => self::SCOPES,
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
                'name'        => 'list_events',
                'description' => 'List Microsoft Calendar events within a date range.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'startDateTime' => ['type' => 'string', 'description' => 'Start of range (ISO8601).'],
                        'endDateTime'   => ['type' => 'string', 'description' => 'End of range (ISO8601).'],
                    ],
                ],
            ],
            [
                'name'        => 'create_event',
                'description' => 'Create a new Microsoft Calendar event.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['subject', 'start', 'end'],
                    'properties' => [
                        'subject' => ['type' => 'string', 'description' => 'Event subject/title.'],
                        'start'   => ['type' => 'string', 'description' => 'Start datetime (ISO8601).'],
                        'end'     => ['type' => 'string', 'description' => 'End datetime (ISO8601).'],
                        'body'    => ['type' => 'string', 'description' => 'Event body/description.'],
                    ],
                ],
            ],
            [
                'name'        => 'update_event',
                'description' => 'Update an existing Microsoft Calendar event.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['eventId'],
                    'properties' => [
                        'eventId' => ['type' => 'string', 'description' => 'Microsoft Calendar event ID.'],
                        'subject' => ['type' => 'string', 'description' => 'New event subject.'],
                        'start'   => ['type' => 'string', 'description' => 'New start datetime (ISO8601).'],
                        'end'     => ['type' => 'string', 'description' => 'New end datetime (ISO8601).'],
                    ],
                ],
            ],
            [
                'name'        => 'delete_event',
                'description' => 'Delete a Microsoft Calendar event.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['eventId'],
                    'properties' => [
                        'eventId' => ['type' => 'string', 'description' => 'Microsoft Calendar event ID.'],
                    ],
                ],
            ],
            [
                'name'        => 'find_free_time',
                'description' => 'Find free/busy time slots for attendees.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['startDateTime', 'endDateTime'],
                    'properties' => [
                        'startDateTime' => ['type' => 'string', 'description' => 'Start of range (ISO8601).'],
                        'endDateTime'   => ['type' => 'string', 'description' => 'End of range (ISO8601).'],
                        'attendees'     => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'Email addresses of attendees to check.',
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'list_calendars',
                'description' => 'List all Microsoft calendars for the user.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [],
                ],
            ],
            [
                'name'        => 'respond_to_event',
                'description' => 'RSVP to a Microsoft Calendar event invitation.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['eventId', 'response'],
                    'properties' => [
                        'eventId'  => ['type' => 'string', 'description' => 'Microsoft Calendar event ID.'],
                        'response' => [
                            'type'        => 'string',
                            'enum'        => ['accept', 'tentativelyAccept', 'decline'],
                            'description' => 'RSVP response.',
                        ],
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
            'list_events'      => $this->listEvents($params, $user),
            'create_event'     => $this->createEvent($params, $user),
            'update_event'     => $this->updateEvent($params, $user),
            'delete_event'     => $this->deleteEvent($params, $user),
            'find_free_time'   => $this->findFreeTime($params, $user),
            'list_calendars'   => $this->listCalendars($user),
            'respond_to_event' => $this->respondToEvent($params, $user),
            default            => throw new \InvalidArgumentException("Unknown tool: {$toolName}"),
        };
    }

    public function testConnection(User $user): bool
    {
        try {
            $this->listCalendars($user);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function listEvents(array $params, User $user): array
    {
        $query = array_filter([
            'startDateTime' => $params['startDateTime'] ?? now()->toIso8601String(),
            'endDateTime'   => $params['endDateTime'] ?? now()->addDays(7)->toIso8601String(),
            '$top'          => 25,
            '$orderby'      => 'start/dateTime',
        ]);

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/calendarView', $query);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function createEvent(array $params, User $user): array
    {
        $body = array_filter([
            'subject' => $params['subject'],
            'start'   => [
                'dateTime' => $params['start'],
                'timeZone' => 'UTC',
            ],
            'end' => [
                'dateTime' => $params['end'],
                'timeZone' => 'UTC',
            ],
            'body' => isset($params['body']) ? [
                'contentType' => 'text',
                'content'     => $params['body'],
            ] : null,
        ]);

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/events', $body);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function updateEvent(array $params, User $user): array
    {
        $eventId = (string) $params['eventId'];

        $body = array_filter([
            'subject' => $params['subject'] ?? null,
            'start'   => isset($params['start']) ? ['dateTime' => $params['start'], 'timeZone' => 'UTC'] : null,
            'end'     => isset($params['end']) ? ['dateTime' => $params['end'], 'timeZone' => 'UTC'] : null,
        ]);

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->patch(self::BASE_URL.'/events/'.$eventId, $body);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function deleteEvent(array $params, User $user): array
    {
        $eventId = (string) $params['eventId'];

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->delete(self::BASE_URL.'/events/'.$eventId);

        $response->throw();

        return ['deleted' => true, 'eventId' => $eventId];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function findFreeTime(array $params, User $user): array
    {
        $attendees = array_map(
            fn (string $email): array => ['type' => 'required', 'emailAddress' => ['address' => $email]],
            (array) ($params['attendees'] ?? []),
        );

        $body = [
            'schedules' => array_map(
                fn (string $email): string => $email,
                (array) ($params['attendees'] ?? []),
            ),
            'startTime' => [
                'dateTime' => $params['startDateTime'],
                'timeZone' => 'UTC',
            ],
            'endTime' => [
                'dateTime' => $params['endDateTime'],
                'timeZone' => 'UTC',
            ],
            'availabilityViewInterval' => 30,
        ];

        unset($attendees);

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/calendar/getSchedule', $body);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function listCalendars(User $user): array
    {
        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/calendars');

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function respondToEvent(array $params, User $user): array
    {
        $eventId = (string) $params['eventId'];
        $responseAction = (string) $params['response'];

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/events/'.$eventId.'/'.$responseAction);

        $response->throw();

        return ['responded' => true, 'eventId' => $eventId, 'response' => $responseAction];
    }

    private function getOauthToken(User $user): string
    {
        $integration = $this->getUserIntegration($user);

        return (string) ($integration?->oauth_token ?? '');
    }
}
