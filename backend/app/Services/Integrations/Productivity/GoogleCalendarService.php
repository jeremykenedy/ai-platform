<?php

declare(strict_types=1);

namespace App\Services\Integrations\Productivity;

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Support\Facades\Http;

class GoogleCalendarService extends AbstractIntegrationService
{
    protected string $integrationName = 'google_calendar';

    private const BASE_URL = 'https://www.googleapis.com/calendar/v3';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar.readonly',
        'https://www.googleapis.com/auth/calendar.events',
    ];

    public function getAuthUrl(User $user): ?string
    {
        $params = http_build_query([
            'client_id'     => config('services.google.client_id'),
            'redirect_uri'  => config('services.google.redirect_uri'),
            'response_type' => 'code',
            'scope'         => implode(' ', self::SCOPES),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
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
                'client_id'     => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri'  => config('services.google.redirect_uri'),
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
                'description' => 'List calendar events within a time range.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query'   => ['type' => 'string', 'description' => 'Free-text search query.'],
                        'timeMin' => ['type' => 'string', 'description' => 'Start of time range (RFC3339).'],
                        'timeMax' => ['type' => 'string', 'description' => 'End of time range (RFC3339).'],
                    ],
                ],
            ],
            [
                'name'        => 'create_event',
                'description' => 'Create a new calendar event.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['summary', 'start', 'end'],
                    'properties' => [
                        'summary'     => ['type' => 'string', 'description' => 'Event title.'],
                        'start'       => ['type' => 'string', 'description' => 'Start datetime (RFC3339).'],
                        'end'         => ['type' => 'string', 'description' => 'End datetime (RFC3339).'],
                        'description' => ['type' => 'string', 'description' => 'Event description.'],
                        'attendees'   => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'List of attendee email addresses.',
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'update_event',
                'description' => 'Update an existing calendar event.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['eventId'],
                    'properties' => [
                        'eventId' => ['type' => 'string', 'description' => 'Google Calendar event ID.'],
                        'summary' => ['type' => 'string', 'description' => 'New event title.'],
                        'start'   => ['type' => 'string', 'description' => 'New start datetime (RFC3339).'],
                        'end'     => ['type' => 'string', 'description' => 'New end datetime (RFC3339).'],
                    ],
                ],
            ],
            [
                'name'        => 'delete_event',
                'description' => 'Delete a calendar event.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['eventId'],
                    'properties' => [
                        'eventId' => ['type' => 'string', 'description' => 'Google Calendar event ID.'],
                    ],
                ],
            ],
            [
                'name'        => 'find_free_time',
                'description' => 'Find free time slots within a time range.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['timeMin', 'timeMax'],
                    'properties' => [
                        'timeMin' => ['type' => 'string', 'description' => 'Start of range (RFC3339).'],
                        'timeMax' => ['type' => 'string', 'description' => 'End of range (RFC3339).'],
                    ],
                ],
            ],
            [
                'name'        => 'list_calendars',
                'description' => 'List all calendars in the user\'s account.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [],
                ],
            ],
            [
                'name'        => 'respond_to_event',
                'description' => 'RSVP to a calendar event invitation.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['eventId', 'response'],
                    'properties' => [
                        'eventId'  => ['type' => 'string', 'description' => 'Google Calendar event ID.'],
                        'response' => [
                            'type'        => 'string',
                            'enum'        => ['accepted', 'declined', 'tentative'],
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
            'q'            => $params['query'] ?? null,
            'timeMin'      => $params['timeMin'] ?? null,
            'timeMax'      => $params['timeMax'] ?? null,
            'singleEvents' => 'true',
            'orderBy'      => 'startTime',
        ]);

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/calendars/primary/events', $query);

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
        $attendees = array_map(
            fn (string $email): array => ['email' => $email],
            (array) ($params['attendees'] ?? []),
        );

        $body = array_filter([
            'summary'     => $params['summary'],
            'description' => $params['description'] ?? null,
            'start'       => ['dateTime' => $params['start'], 'timeZone' => 'UTC'],
            'end'         => ['dateTime' => $params['end'], 'timeZone' => 'UTC'],
            'attendees'   => $attendees !== [] ? $attendees : null,
        ]);

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/calendars/primary/events', $body);

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
            'summary' => $params['summary'] ?? null,
            'start'   => isset($params['start']) ? ['dateTime' => $params['start'], 'timeZone' => 'UTC'] : null,
            'end'     => isset($params['end']) ? ['dateTime' => $params['end'], 'timeZone' => 'UTC'] : null,
        ]);

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->patch(self::BASE_URL.'/calendars/primary/events/'.$eventId, $body);

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
            ->delete(self::BASE_URL.'/calendars/primary/events/'.$eventId);

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
        $body = [
            'timeMin' => $params['timeMin'],
            'timeMax' => $params['timeMax'],
            'items'   => [['id' => 'primary']],
        ];

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/freeBusy', $body);

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
            ->get(self::BASE_URL.'/users/me/calendarList');

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
        $responseStatus = (string) $params['response'];

        $event = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/calendars/primary/events/'.$eventId)
            ->throw()
            ->json();

        $attendees = $event['attendees'] ?? [];
        $userEmail = $this->getUserEmail($user);

        foreach ($attendees as &$attendee) {
            if (($attendee['self'] ?? false) || ($attendee['email'] ?? '') === $userEmail) {
                $attendee['responseStatus'] = $responseStatus;
                break;
            }
        }

        unset($attendee);

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->patch(self::BASE_URL.'/calendars/primary/events/'.$eventId, ['attendees' => $attendees]);

        $response->throw();

        return $response->json() ?? [];
    }

    private function getOauthToken(User $user): string
    {
        $integration = $this->getUserIntegration($user);

        return (string) ($integration?->oauth_token ?? '');
    }

    private function getUserEmail(User $user): string
    {
        /** @var string */
        return $user->email ?? '';
    }
}
