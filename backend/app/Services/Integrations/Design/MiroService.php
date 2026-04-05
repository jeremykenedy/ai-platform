<?php

declare(strict_types=1);

namespace App\Services\Integrations\Design;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MiroService extends AbstractIntegrationService
{
    protected string $integrationName = 'miro';

    private const BASE_URL = 'https://api.miro.com/v2';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'list_boards',
                'description' => 'List all Miro boards accessible to the authenticated user.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of boards to return (default 10).',
                        ],
                        'cursor' => [
                            'type' => 'string',
                            'description' => 'Pagination cursor from a previous response.',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'get_board',
                'description' => 'Retrieve details of a specific Miro board by its ID.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'boardId' => [
                            'type' => 'string',
                            'description' => 'The unique identifier of the Miro board.',
                        ],
                    ],
                    'required' => ['boardId'],
                ],
            ],
            [
                'name' => 'create_board',
                'description' => 'Create a new Miro board with the given name and optional description.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name of the new board.',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Optional description of the board.',
                        ],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'create_sticky_note',
                'description' => 'Create a sticky note item on a Miro board.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'boardId' => [
                            'type' => 'string',
                            'description' => 'The board on which to create the sticky note.',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Text content of the sticky note.',
                        ],
                        'position' => [
                            'type' => 'object',
                            'description' => 'Position on the board with x and y coordinates.',
                            'properties' => [
                                'x' => ['type' => 'number'],
                                'y' => ['type' => 'number'],
                            ],
                        ],
                    ],
                    'required' => ['boardId', 'content'],
                ],
            ],
            [
                'name' => 'list_items',
                'description' => 'List items on a Miro board, optionally filtered by item type.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'boardId' => [
                            'type' => 'string',
                            'description' => 'The board from which to list items.',
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'Optional item type filter (e.g. sticky_note, shape, text).',
                        ],
                    ],
                    'required' => ['boardId'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'list_boards' => $this->listBoards($user, $params),
            'get_board' => $this->getBoard($user, $params),
            'create_board' => $this->createBoard($user, $params),
            'create_sticky_note' => $this->createStickyNote($user, $params),
            'list_items' => $this->listItems($user, $params),
            default => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    public function getAuthUrl(User $user): ?string
    {
        $clientId = config('services.miro.client_id');
        $redirectUri = config('services.miro.redirect_uri');
        $state = bin2hex(random_bytes(16));

        return 'https://miro.com/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    public function handleCallback(User $user, array $params): void
    {
        $code = $params['code'] ?? throw new RuntimeException('Missing OAuth code.');

        $response = Http::timeout(15)->post('https://api.miro.com/v1/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.miro.client_id'),
            'client_secret' => config('services.miro.client_secret'),
            'redirect_uri' => config('services.miro.redirect_uri'),
            'code' => $code,
        ]);

        $response->throw();
        $data = $response->json();

        $integration = $this->getUserIntegration($user);

        if ($integration === null) {
            $definition = $this->getDefinition();

            $user->integrations()->create([
                'integration_id' => $definition->getKey(),
                'is_enabled' => true,
                'oauth_token' => $data['access_token'],
                'oauth_refresh_token' => $data['refresh_token'] ?? null,
                'oauth_expires_at' => isset($data['expires_in'])
                    ? now()->addSeconds((int) $data['expires_in'])
                    : null,
                'scopes_granted' => isset($data['scope'])
                    ? explode(' ', (string) $data['scope'])
                    : [],
            ]);
        } else {
            $integration->update([
                'is_enabled' => true,
                'oauth_token' => $data['access_token'],
                'oauth_refresh_token' => $data['refresh_token'] ?? null,
                'oauth_expires_at' => isset($data['expires_in'])
                    ? now()->addSeconds((int) $data['expires_in'])
                    : null,
                'scopes_granted' => isset($data['scope'])
                    ? explode(' ', (string) $data['scope'])
                    : [],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listBoards(User $user, array $params): array
    {
        $query = [];

        if (isset($params['limit'])) {
            $query['limit'] = (int) $params['limit'];
        }

        if (isset($params['cursor'])) {
            $query['cursor'] = $params['cursor'];
        }

        $response = $this->client($user)
            ->get(self::BASE_URL.'/boards', $query);

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getBoard(User $user, array $params): array
    {
        $boardId = $params['boardId'] ?? throw new RuntimeException('boardId is required.');

        $response = $this->client($user)
            ->get(self::BASE_URL.'/boards/'.urlencode((string) $boardId));

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function createBoard(User $user, array $params): array
    {
        $name = $params['name'] ?? throw new RuntimeException('name is required.');

        $body = ['name' => $name];

        if (isset($params['description'])) {
            $body['description'] = $params['description'];
        }

        $response = $this->client($user)
            ->post(self::BASE_URL.'/boards', $body);

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function createStickyNote(User $user, array $params): array
    {
        $boardId = $params['boardId'] ?? throw new RuntimeException('boardId is required.');
        $content = $params['content'] ?? throw new RuntimeException('content is required.');

        $body = [
            'data' => ['content' => $content],
        ];

        if (isset($params['position']) && is_array($params['position'])) {
            $body['position'] = [
                'x' => (float) ($params['position']['x'] ?? 0),
                'y' => (float) ($params['position']['y'] ?? 0),
                'origin' => 'center',
            ];
        }

        $response = $this->client($user)
            ->post(self::BASE_URL.'/boards/'.urlencode((string) $boardId).'/sticky_notes', $body);

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listItems(User $user, array $params): array
    {
        $boardId = $params['boardId'] ?? throw new RuntimeException('boardId is required.');

        $query = [];

        if (isset($params['type'])) {
            $query['type'] = $params['type'];
        }

        $response = $this->client($user)
            ->get(self::BASE_URL.'/boards/'.urlencode((string) $boardId).'/items', $query);

        $response->throw();

        return $response->json();
    }

    private function getAccessToken(User $user): string
    {
        $integration = $this->getUserIntegration($user);

        if ($integration === null || $integration->oauth_token === null) {
            throw new RuntimeException('Miro integration is not connected for this user.');
        }

        return (string) $integration->oauth_token;
    }

    private function client(User $user): PendingRequest
    {
        return Http::withToken($this->getAccessToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(2, 500)
            ->acceptJson();
    }
}
