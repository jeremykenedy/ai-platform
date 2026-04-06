<?php

declare(strict_types=1);

namespace App\Services\Integrations\Productivity;

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Support\Facades\Http;

class SlackService extends AbstractIntegrationService
{
    protected string $integrationName = 'slack';

    private const BASE_URL = 'https://slack.com/api';

    private const TOKEN_URL = 'https://slack.com/api/oauth.v2.access';

    private const AUTH_URL = 'https://slack.com/oauth/v2/authorize';

    private const SCOPES = [
        'channels:read',
        'chat:write',
        'users:read',
        'search:read',
        'channels:history',
    ];

    public function getAuthUrl(User $user): ?string
    {
        $params = http_build_query([
            'client_id'    => config('services.slack.client_id'),
            'redirect_uri' => config('services.slack.redirect_uri'),
            'scope'        => implode(',', self::SCOPES),
            'state'        => $user->getKey(),
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
                'client_id'     => config('services.slack.client_id'),
                'client_secret' => config('services.slack.client_secret'),
                'redirect_uri'  => config('services.slack.redirect_uri'),
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
                'is_enabled'     => true,
                'oauth_token'    => $data['access_token'] ?? null,
                'scopes_granted' => self::SCOPES,
                'metadata'       => [
                    'team_id'     => $data['team']['id'] ?? null,
                    'team_name'   => $data['team']['name'] ?? null,
                    'bot_user_id' => $data['bot_user_id'] ?? null,
                ],
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
                'name'        => 'list_channels',
                'description' => 'List all Slack channels the bot has access to.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [],
                ],
            ],
            [
                'name'        => 'read_messages',
                'description' => 'Read recent messages from a Slack channel.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['channel'],
                    'properties' => [
                        'channel' => ['type' => 'string', 'description' => 'Channel ID or name.'],
                        'limit'   => ['type' => 'integer', 'description' => 'Number of messages to retrieve (default 20).'],
                    ],
                ],
            ],
            [
                'name'        => 'send_message',
                'description' => 'Send a message to a Slack channel.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['channel', 'text'],
                    'properties' => [
                        'channel' => ['type' => 'string', 'description' => 'Channel ID or name.'],
                        'text'    => ['type' => 'string', 'description' => 'Message text to send.'],
                    ],
                ],
            ],
            [
                'name'        => 'search_messages',
                'description' => 'Search for messages across Slack.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['query'],
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search query string.'],
                    ],
                ],
            ],
            [
                'name'        => 'list_users',
                'description' => 'List users in the Slack workspace.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [],
                ],
            ],
            [
                'name'        => 'get_channel_info',
                'description' => 'Get detailed information about a Slack channel.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['channel'],
                    'properties' => [
                        'channel' => ['type' => 'string', 'description' => 'Channel ID.'],
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
            'list_channels'    => $this->listChannels($user),
            'read_messages'    => $this->readMessages($params, $user),
            'send_message'     => $this->sendMessage($params, $user),
            'search_messages'  => $this->searchMessages($params, $user),
            'list_users'       => $this->listUsers($user),
            'get_channel_info' => $this->getChannelInfo($params, $user),
            default            => throw new \InvalidArgumentException("Unknown tool: {$toolName}"),
        };
    }

    public function testConnection(User $user): bool
    {
        try {
            $response = Http::withToken($this->getOauthToken($user))
                ->timeout(30)
                ->connectTimeout(10)
                ->get(self::BASE_URL.'/auth.test');

            return $response->successful() && ($response->json()['ok'] ?? false) === true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function listChannels(User $user): array
    {
        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/conversations.list', [
                'types'            => 'public_channel,private_channel',
                'limit'            => 100,
                'exclude_archived' => 'true',
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function readMessages(array $params, User $user): array
    {
        $channel = (string) $params['channel'];
        $limit = (int) ($params['limit'] ?? 20);

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/conversations.history', [
                'channel' => $channel,
                'limit'   => $limit,
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function sendMessage(array $params, User $user): array
    {
        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/chat.postMessage', [
                'channel' => (string) $params['channel'],
                'text'    => (string) $params['text'],
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function searchMessages(array $params, User $user): array
    {
        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/search.messages', [
                'query' => (string) $params['query'],
                'count' => 20,
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function listUsers(User $user): array
    {
        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/users.list', ['limit' => 100]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getChannelInfo(array $params, User $user): array
    {
        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/conversations.info', [
                'channel' => (string) $params['channel'],
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    private function getOauthToken(User $user): string
    {
        $integration = $this->getUserIntegration($user);

        return (string) ($integration?->oauth_token ?? '');
    }
}
